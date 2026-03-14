<?php

declare(strict_types=1);

namespace App\Module\Indexing;

use Amp\Future;
use App\Module\Indexing\Storage\StorageInterface;
use App\Module\Parallel\Task\FileIndexTask;
use App\Module\Parallel\WorkerPoolFactory;
use Lsp\Workspace\File\FileFactoryInterface;
use Lsp\Workspace\File\FilesystemReader\FilesystemReaderFactoryInterface;
use Lsp\Workspace\File\VirtualFileInterface;
use Lsp\Workspace\Project\Project;
use Lsp\Workspace\Uri\Uri;
use Psr\Log\LoggerInterface;

/**
 * Parallel-aware indexer that offloads PHP file parsing to worker processes.
 *
 * Collects PHP files in batches and submits them to the worker pool
 * for parallel parsing + index extraction. Non-PHP files fall back
 * to sequential indexing.
 */
final class ParallelIndexer
{
    private const BATCH_SIZE = 20;

    public function __construct(
        private readonly StorageInterface $storage,
        private readonly LoggerInterface $logger,
        private readonly FilesystemReaderFactoryInterface $filesystemReaderFactory,
        private readonly FileFactoryInterface $files,
        private readonly WorkerPoolFactory $workerPoolFactory,
    ) {}

    public function index(Project $project): void
    {
        $this->logger->info('Parallel indexing project: ' . $project->uri);
        $startTime = hrtime(true);

        $phpFiles = [];

        foreach ($project as $file) {
            $this->collectFiles($file, $phpFiles, 0);
        }

        $realpath = realpath(__DIR__ . '/../../../resources/php-stubs');
        $uri = Uri::createLocal('file://' . $realpath);
        $stubs = $this->files->create($uri->path, $this->filesystemReaderFactory);
        $this->collectFiles($stubs, $phpFiles, 0);

        $this->logger->info(sprintf('Collected %d PHP files for parallel indexing', count($phpFiles)));

        $this->indexFilesInParallel($phpFiles);

        $elapsed = (hrtime(true) - $startTime) / 1_000_000;
        $this->logger->info(sprintf('Parallel indexing finished in %.2f ms', $elapsed));
    }

    /**
     * @param list<array{uri: string, content: string}> $phpFiles
     */
    private function collectFiles(VirtualFileInterface $file, array &$phpFiles, int $level): void
    {
        if ($this->isIgnored($file->name)) {
            return;
        }

        if ($file->count() === 0 && $file->extension === 'php') {
            $content = $this->readFileContent($file);
            if ($content !== null) {
                $phpFiles[] = ['uri' => (string) $file->uri, 'content' => $content];
            }
        }

        foreach ($file as $child) {
            $this->collectFiles($child, $phpFiles, $level + 1);
        }
    }

    /**
     * @param list<array{uri: string, content: string}> $phpFiles
     */
    private function indexFilesInParallel(array $phpFiles): void
    {
        $pool = $this->workerPoolFactory->getPool();
        $batches = array_chunk($phpFiles, self::BATCH_SIZE);

        foreach ($batches as $batch) {
            $futures = [];

            foreach ($batch as $fileData) {
                $futures[$fileData['uri']] = $pool->submit(
                    new FileIndexTask($fileData['uri'], $fileData['content']),
                )->getFuture();
            }

            $results = Future\await($futures);

            foreach ($results as $uri => $indexData) {
                foreach ($indexData as $indexKey => $entries) {
                    if ($entries === []) {
                        continue;
                    }

                    $this->storage->write($indexKey, $entries, $uri);
                }
            }
        }
    }

    private function readFileContent(VirtualFileInterface $file): ?string
    {
        $path = (string) $file->uri;

        // Strip file:// prefix if present
        if (str_starts_with($path, 'file://')) {
            $path = substr($path, 7);
        }

        if (!is_file($path) || !is_readable($path)) {
            return null;
        }

        $content = file_get_contents($path);

        return $content !== false ? $content : null;
    }

    private function isIgnored(string $name): bool
    {
        return in_array(
            $name,
            [
                'node_modules',
                '.git',
                '.idea',
                'config',
                'resources',
                'runtime',
                'psalm',
                'rector',
                'thecodingmachine',
                'aerospike',
                'tests',
                'mongodb',
                'meta',
                'rdkafka',
                'intl',
                'swoole',
                'wincache',
                'couchbase',
                'couchbase_v2',
                'relay',
                'redis',
                'imagick',
            ],
            true,
        );
    }
}
