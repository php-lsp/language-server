<?php

declare(strict_types=1);

namespace App\Tests\Benchmark;

use App\Module\Indexing\Data\ClassData;
use App\Module\Indexing\Indexer\ClassIndexer;
use App\Module\Indexing\Storage\GoIndexerClient;
use App\Module\PsiFile\PHPPsiFile;
use App\Module\PsiFile\PHPPsiFileParser;
use App\Tests\TestCase;
use Lsp\Extension\DocumentManager\Editor\Document\Document;
use Lsp\Extension\DocumentManager\Editor\Document\Uri;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;

/**
 * Benchmark comparing PHP-native indexing vs Go-based indexing.
 *
 * Run with: php vendor/bin/phpunit tests/Benchmark/ --testdox
 *
 * This is a PHPUnit test that measures and reports timing — not a proper
 * microbenchmark framework, but sufficient for MVP comparison.
 */
#[Group('benchmark')]
final class IndexingBenchmark extends TestCase
{
    private const int FILE_COUNT = 200;
    private const int ITERATIONS = 3;

    private static string $fixtureDir = '';

    public static function setUpBeforeClass(): void
    {
        self::$fixtureDir = sys_get_temp_dir() . '/lsp-indexing-benchmark-' . getmypid();
        self::generateFixtures(self::$fixtureDir, self::FILE_COUNT);
    }

    public static function tearDownAfterClass(): void
    {
        self::removeDir(self::$fixtureDir);
    }

    #[TestDox('Benchmark: PHP ClassIndexer on ' . self::FILE_COUNT . ' files')]
    public function testPhpIndexerBenchmark(): void
    {
        $parser = new PHPPsiFileParser();
        $times = [];

        for ($iter = 0; $iter < self::ITERATIONS; $iter++) {
            $start = hrtime(true);
            $totalClasses = 0;

            $files = glob(self::$fixtureDir . '/src/**/*.php');
            foreach ($files as $filePath) {
                $source = file_get_contents($filePath);
                $document = new Document(
                    uri: new Uri('file://' . $filePath),
                    text: $source,
                );
                $sourceRoot = $parser->parse($document);
                $psiFile = new PHPPsiFile($sourceRoot);

                // Use reflection to call indexInternal directly.
                $indexer = (new \ReflectionClass(ClassIndexer::class))->newInstanceWithoutConstructor();
                $method = new \ReflectionMethod(ClassIndexer::class, 'indexInternal');

                $results = $method->invoke($indexer, $psiFile);
                $totalClasses += count($results);
            }

            $elapsed = (hrtime(true) - $start) / 1_000_000; // ms
            $times[] = $elapsed;
        }

        $avg = array_sum($times) / count($times);
        $min = min($times);
        $max = max($times);

        echo sprintf(
            "\n  PHP ClassIndexer: avg=%.1fms, min=%.1fms, max=%.1fms (%d files, %d iterations)\n",
            $avg,
            $min,
            $max,
            self::FILE_COUNT,
            self::ITERATIONS,
        );

        // This test always passes — it's for measurement only.
        $this->assertGreaterThan(0, $avg);
    }

    #[TestDox('Benchmark: Go indexer on ' . self::FILE_COUNT . ' files')]
    public function testGoIndexerBenchmark(): void
    {
        $binaryPath = dirname(__DIR__, 2) . '/bin/go-indexer';
        if (!file_exists($binaryPath)) {
            $this->markTestSkipped('Go indexer binary not built. Run: cd go-indexer && go build -o ../bin/go-indexer .');
        }

        $client = new GoIndexerClient($binaryPath);
        $times = [];

        for ($iter = 0; $iter < self::ITERATIONS; $iter++) {
            $start = hrtime(true);

            $result = $client->indexWorkspace(self::$fixtureDir);

            $elapsed = (hrtime(true) - $start) / 1_000_000; // ms
            $times[] = $elapsed;
            $client->clear();
        }

        $avg = array_sum($times) / count($times);
        $min = min($times);
        $max = max($times);

        $lastResult = $client->indexWorkspace(self::$fixtureDir);

        echo sprintf(
            "\n  Go indexer: avg=%.1fms, min=%.1fms, max=%.1fms (%d files, %d classes, %d iterations)\n",
            $avg,
            $min,
            $max,
            $lastResult['file_count'],
            count($lastResult['classes']),
            self::ITERATIONS,
        );

        $this->assertGreaterThan(0, $avg);
    }

    #[TestDox('Benchmark: comparison summary')]
    public function testComparisonSummary(): void
    {
        $binaryPath = dirname(__DIR__, 2) . '/bin/go-indexer';
        if (!file_exists($binaryPath)) {
            $this->markTestSkipped('Go indexer binary not built.');
        }

        // Measure PHP.
        $parser = new PHPPsiFileParser();
        $phpStart = hrtime(true);
        $files = glob(self::$fixtureDir . '/src/**/*.php');
        foreach ($files as $filePath) {
            $source = file_get_contents($filePath);
            $document = new Document(uri: new Uri('file://' . $filePath), text: $source);
            $sourceRoot = $parser->parse($document);
            $psiFile = new PHPPsiFile($sourceRoot);
            $indexer = (new \ReflectionClass(ClassIndexer::class))->newInstanceWithoutConstructor();
            $method = new \ReflectionMethod(ClassIndexer::class, 'indexInternal');
            $method->invoke($indexer, $psiFile);
        }
        $phpTime = (hrtime(true) - $phpStart) / 1_000_000;

        // Measure Go.
        $client = new GoIndexerClient($binaryPath);
        $goStart = hrtime(true);
        $client->indexWorkspace(self::$fixtureDir);
        $goTime = (hrtime(true) - $goStart) / 1_000_000;

        $speedup = $phpTime / max($goTime, 0.001);

        echo sprintf(
            "\n  === COMPARISON (%d files) ===\n  PHP: %.1fms\n  Go:  %.1fms\n  Speedup: %.1fx\n",
            self::FILE_COUNT,
            $phpTime,
            $goTime,
            $speedup,
        );

        $this->assertGreaterThan(0, $speedup);
    }

    private static function generateFixtures(string $dir, int $count): void
    {
        $srcDir = $dir . '/src/Generated';
        if (!is_dir($srcDir)) {
            mkdir($srcDir, 0o755, true);
        }

        for ($i = 0; $i < $count; $i++) {
            $className = 'GeneratedClass' . $i;
            $content = <<<PHP
<?php

namespace App\\Generated;

use App\\Base\\AbstractEntity;
use App\\Contracts\\SerializableInterface;

/**
 * Generated class {$className} for benchmarking.
 *
 * @template T
 */
final class {$className} extends AbstractEntity implements SerializableInterface
{
    private string \$name;
    private int \$id;
    protected array \$data = [];

    public function __construct(string \$name, int \$id)
    {
        \$this->name = \$name;
        \$this->id = \$id;
    }

    public function getName(): string
    {
        return \$this->name;
    }

    public function getId(): int
    {
        return \$this->id;
    }

    public function serialize(): array
    {
        return ['name' => \$this->name, 'id' => \$this->id, 'data' => \$this->data];
    }

    public function process(callable \$callback): mixed
    {
        return \$callback(\$this);
    }
}
PHP;
            file_put_contents($srcDir . '/' . $className . '.php', $content);
        }
    }

    private static function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($items as $item) {
            if ($item->isDir()) {
                rmdir($item->getPathname());
            } else {
                unlink($item->getPathname());
            }
        }
        rmdir($dir);
    }
}
