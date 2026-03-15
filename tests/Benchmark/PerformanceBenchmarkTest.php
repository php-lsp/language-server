<?php

declare(strict_types=1);

namespace App\Tests\Benchmark;

use App\Core\Contracts\Completion\CompletionConsumer;
use App\Module\Indexing\Storage\Entry;
use App\Module\Indexing\Storage\InMemoryStorage;
use App\Module\PsiFile\FifoCache;
use App\Module\PsiFile\SourceFileRoot;
use App\Module\PsiFile\Tree;
use Lsp\Extension\DocumentManager\Editor\Document\Document;
use PhpParser\Node;
use PhpParser\ParserFactory;
use PHPUnit\Framework\TestCase;

/**
 * Performance benchmarks for identified bottlenecks.
 *
 * Run with: php vendor/bin/phpunit tests/Benchmark/ --testdox
 */
final class PerformanceBenchmarkTest extends TestCase
{
    // =========================================================================
    // Benchmark 1: Tree::childrenOfType — array_merge in recursion
    // =========================================================================

    public function test_benchmark_tree_children_of_type(): void
    {
        $code = $this->generateLargePhpFile(200);
        $ast = $this->parseCode($code);
        $doc = $this->createDocumentStub($code);
        $root = new SourceFileRoot($ast, $doc, []);

        // Warm up
        Tree::childrenOfType($root, Node\Stmt\Class_::class);

        $iterations = 50;
        $memBefore = memory_get_usage();
        $start = hrtime(true);

        for ($i = 0; $i < $iterations; $i++) {
            Tree::childrenOfType($root, Node\Stmt\Class_::class);
        }

        $elapsed = (hrtime(true) - $start) / 1e6;
        $memAfter = memory_get_peak_usage();
        $memDelta = $memAfter - $memBefore;

        $this->addBenchmarkResult('Tree::childrenOfType (200 classes)', $iterations, $elapsed, $memDelta);
        $this->assertTrue(true);
    }

    public function test_benchmark_tree_children_of_types(): void
    {
        $code = $this->generateLargePhpFile(200);
        $ast = $this->parseCode($code);
        $doc = $this->createDocumentStub($code);
        $root = new SourceFileRoot($ast, $doc, []);

        // Warm up
        Tree::childrenOfTypes($root, Node\Stmt\Class_::class, Node\Stmt\Function_::class);

        $iterations = 50;
        $memBefore = memory_get_usage();
        $start = hrtime(true);

        for ($i = 0; $i < $iterations; $i++) {
            Tree::childrenOfTypes($root, Node\Stmt\Class_::class, Node\Stmt\Function_::class);
        }

        $elapsed = (hrtime(true) - $start) / 1e6;
        $memAfter = memory_get_peak_usage();
        $memDelta = $memAfter - $memBefore;

        $this->addBenchmarkResult('Tree::childrenOfTypes (200 classes)', $iterations, $elapsed, $memDelta);
        $this->assertTrue(true);
    }

    // =========================================================================
    // Benchmark 2: InMemoryStorage linear scan
    // =========================================================================

    public function test_benchmark_storage_linear_scan_10k(): void
    {
        $storage = new InMemoryStorage();
        $targetClass = 'App\\Target\\MyClass';

        // Populate with 10,000 method entries
        for ($i = 0; $i < 10_000; $i++) {
            $className = $i === 5000 ? $targetClass : 'App\\Other\\Class' . $i;
            $storage->write('php.methods.fqn', [
                "method_{$i}" => (object) [
                    'name' => "method_{$i}",
                    'className' => $className,
                    'visibility' => 'public',
                    'returnType' => 'void',
                    'parameters' => [],
                ],
            ], "file:///src/Class{$i}.php");
        }

        // Benchmark: find methods of one class (linear scan)
        $iterations = 100;
        $memBefore = memory_get_usage();
        $start = hrtime(true);

        for ($i = 0; $i < $iterations; $i++) {
            $found = 0;
            foreach ($storage->read('php.methods.fqn') as $entry) {
                if ($entry->value->className === $targetClass) {
                    $found++;
                }
            }
        }

        $elapsed = (hrtime(true) - $start) / 1e6;
        $memAfter = memory_get_peak_usage();
        $memDelta = $memAfter - $memBefore;

        $this->addBenchmarkResult('Storage linear scan (10K entries)', $iterations, $elapsed, $memDelta);

        // Now benchmark readByField (secondary index)
        $iterations2 = 100;
        $memBefore2 = memory_get_usage();
        $start2 = hrtime(true);

        for ($i = 0; $i < $iterations2; $i++) {
            $found2 = 0;
            foreach ($storage->readByField('php.methods.fqn', 'className', $targetClass) as $entry) {
                $found2++;
            }
        }

        $elapsed2 = (hrtime(true) - $start2) / 1e6;
        $memAfter2 = memory_get_peak_usage();
        $memDelta2 = $memAfter2 - $memBefore2;

        $this->addBenchmarkResult('Storage readByField (10K entries)', $iterations2, $elapsed2, $memDelta2);
        $this->assertTrue(true);
    }

    public function test_benchmark_storage_linear_scan_50k(): void
    {
        $storage = new InMemoryStorage();
        $targetClass = 'App\\Target\\MyClass';

        for ($i = 0; $i < 50_000; $i++) {
            $className = $i % 100 === 0 ? $targetClass : 'App\\Other\\Class' . $i;
            $storage->write('php.methods.fqn', [
                "method_{$i}" => (object) [
                    'name' => "method_{$i}",
                    'className' => $className,
                    'visibility' => 'public',
                    'returnType' => 'void',
                    'parameters' => [],
                ],
            ], "file:///src/Class{$i}.php");
        }

        $iterations = 20;
        $memBefore = memory_get_usage();
        $start = hrtime(true);

        for ($i = 0; $i < $iterations; $i++) {
            $found = 0;
            foreach ($storage->read('php.methods.fqn') as $entry) {
                if ($entry->value->className === $targetClass) {
                    $found++;
                }
            }
        }

        $elapsed = (hrtime(true) - $start) / 1e6;
        $memAfter = memory_get_peak_usage();
        $memDelta = $memAfter - $memBefore;

        $this->addBenchmarkResult('Storage linear scan (50K entries)', $iterations, $elapsed, $memDelta);

        // Now benchmark readByField (secondary index)
        $iterations2 = 20;
        $memBefore2 = memory_get_usage();
        $start2 = hrtime(true);

        for ($i = 0; $i < $iterations2; $i++) {
            $found2 = 0;
            foreach ($storage->readByField('php.methods.fqn', 'className', $targetClass) as $entry) {
                $found2++;
            }
        }

        $elapsed2 = (hrtime(true) - $start2) / 1e6;
        $memAfter2 = memory_get_peak_usage();
        $memDelta2 = $memAfter2 - $memBefore2;

        $this->addBenchmarkResult('Storage readByField (50K entries)', $iterations2, $elapsed2, $memDelta2);
        $this->assertTrue(true);
    }

    // =========================================================================
    // Benchmark 3: FifoCache::remove — array_diff + array_values
    // =========================================================================

    public function test_benchmark_fifo_cache_remove(): void
    {
        $iterations = 1000;
        $cacheSize = 300;

        $memBefore = memory_get_usage();
        $start = hrtime(true);

        for ($i = 0; $i < $iterations; $i++) {
            $cache = new FifoCache($cacheSize);

            // Fill
            for ($j = 0; $j < $cacheSize; $j++) {
                $cache->set("key_{$j}", "value_{$j}");
            }

            // Remove 50 random keys
            for ($j = 0; $j < 50; $j++) {
                $cache->remove("key_{$j}");
            }
        }

        $elapsed = (hrtime(true) - $start) / 1e6;
        $memAfter = memory_get_peak_usage();
        $memDelta = $memAfter - $memBefore;

        $this->addBenchmarkResult('FifoCache::remove (50 removals from 300)', $iterations, $elapsed, $memDelta);
        $this->assertTrue(true);
    }

    // =========================================================================
    // Benchmark 4: FifoCache::evict — on full cache
    // =========================================================================

    public function test_benchmark_fifo_cache_eviction(): void
    {
        $iterations = 500;
        $cacheSize = 300;

        $memBefore = memory_get_usage();
        $start = hrtime(true);

        for ($i = 0; $i < $iterations; $i++) {
            $cache = new FifoCache($cacheSize);

            // Fill past capacity (triggers eviction)
            for ($j = 0; $j < $cacheSize + 100; $j++) {
                $cache->set("key_{$j}", "value_{$j}");
            }
        }

        $elapsed = (hrtime(true) - $start) / 1e6;
        $memAfter = memory_get_peak_usage();
        $memDelta = $memAfter - $memBefore;

        $this->addBenchmarkResult('FifoCache::eviction (300+100 inserts)', $iterations, $elapsed, $memDelta);
        $this->assertTrue(true);
    }

    // =========================================================================
    // Benchmark 5: CompletionConsumer — microtime() overhead
    // =========================================================================

    public function test_benchmark_completion_consumer_1000_items(): void
    {
        $iterations = 50;

        $memBefore = memory_get_usage();
        $start = hrtime(true);

        for ($i = 0; $i < $iterations; $i++) {
            $consumer = new CompletionConsumer();
            for ($j = 0; $j < 1000; $j++) {
                $consumer(new \Lsp\Protocol\Type\CompletionItem(
                    label: "item_{$j}",
                ));
            }
        }

        $elapsed = (hrtime(true) - $start) / 1e6;
        $memAfter = memory_get_peak_usage();
        $memDelta = $memAfter - $memBefore;

        $this->addBenchmarkResult('CompletionConsumer (1000 items)', $iterations, $elapsed, $memDelta);
        $this->assertTrue(true);
    }

    // =========================================================================
    // Benchmark 6: Tree::toLineColumn — repeated line counting
    // =========================================================================

    public function test_benchmark_to_line_column_repeated(): void
    {
        $code = $this->generateLargePhpFile(100);
        $document = $this->createDocumentStub($code);
        $positions = [];

        // Generate 200 random positions in the file
        $len = strlen($code);
        for ($i = 0; $i < 200; $i++) {
            $positions[] = (int) ($len * $i / 200);
        }

        $iterations = 100;
        $memBefore = memory_get_usage();
        $start = hrtime(true);

        for ($i = 0; $i < $iterations; $i++) {
            foreach ($positions as $pos) {
                Tree::toLineColumn($document, $pos);
            }
        }

        $elapsed = (hrtime(true) - $start) / 1e6;
        $memAfter = memory_get_peak_usage();
        $memDelta = $memAfter - $memBefore;

        $this->addBenchmarkResult('Tree::toLineColumn (200 pos x 100 iter)', $iterations, $elapsed, $memDelta);
        $this->assertTrue(true);
    }

    // =========================================================================
    // Benchmark 7: InMemoryStorage memory consumption
    // =========================================================================

    public function test_benchmark_storage_memory_10k_entries(): void
    {
        $memBefore = memory_get_usage(true);

        $storage = new InMemoryStorage();
        for ($i = 0; $i < 10_000; $i++) {
            $storage->write('php.classes.fqn', [
                "Class{$i}" => (object) [
                    'fqn' => "App\\Namespace{$i}\\Class{$i}",
                    'name' => "Class{$i}",
                    'isAbstract' => false,
                    'isFinal' => true,
                    'extends' => null,
                    'implements' => [],
                ],
            ], "file:///src/Class{$i}.php");
        }

        $memAfter = memory_get_usage(true);
        $memDelta = $memAfter - $memBefore;

        $this->addBenchmarkResult('Storage memory (10K entries)', 1, 0, $memDelta);
        $this->assertTrue(true);
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function generateLargePhpFile(int $classCount): string
    {
        $code = "<?php\n\nnamespace App\\Generated;\n\n";
        for ($i = 0; $i < $classCount; $i++) {
            $code .= "class GeneratedClass{$i} {\n";
            for ($j = 0; $j < 5; $j++) {
                $code .= "    public function method{$j}(): void {}\n";
            }
            $code .= "}\n\n";
        }

        return $code;
    }

    /**
     * @return array<Node\Stmt>
     */
    private function parseCode(string $code): array
    {
        $parser = (new ParserFactory())->createForNewestSupportedVersion();

        return $parser->parse($code) ?? [];
    }

    private function createDocumentStub(string $content): Document
    {
        return new Document(
            uri: new \Lsp\Extension\DocumentManager\Editor\Document\Uri('file:///test.php'),
            text: $content,
            version: 1,
        );
    }

    private function addBenchmarkResult(string $name, int $iterations, float $elapsedMs, int $memBytes): void
    {
        $perIter = $iterations > 0 ? $elapsedMs / $iterations : 0;
        $memKb = $memBytes / 1024;
        $memMb = $memKb / 1024;

        fwrite(STDERR, sprintf(
            "\n  [BENCH] %-45s | %8.2f ms total | %8.3f ms/iter | %8.1f KB (%.1f MB)\n",
            $name,
            $elapsedMs,
            $perIter,
            $memKb,
            $memMb,
        ));
    }
}
