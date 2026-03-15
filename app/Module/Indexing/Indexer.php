<?php

declare(strict_types=1);

namespace App\Module\Indexing;

use App\Core\Contracts\Indexing\IndexerInterface;
use App\Module\Indexing\Storage\StorageInterface;
use Lsp\Workspace\File\FileFactoryInterface;
use Lsp\Workspace\File\FilesystemReader\FilesystemReaderFactoryInterface;
use Lsp\Workspace\File\VirtualFileInterface;
use Lsp\Workspace\Project\Project;
use Lsp\Workspace\Uri\Uri;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

use function React\Async\async;
use function React\Async\await;

final class Indexer
{
    /** @var IndexerInterface[] */
    private array $indexers;

    public function __construct(
        #[AutowireIterator('lsp.indexers')]
        iterable $indexers,
        private StorageInterface $storage,
        private LoggerInterface $logger,
        private FilesystemReaderFactoryInterface $filesystemReaderFactory,
        private FileFactoryInterface $files,
        private readonly IndexingStatus $indexingStatus,
    ) {
        $this->indexers = iterator_to_array($indexers);
    }

    public function index(Project $project): void
    {
        $this->logger->info('Indexing project: {uri}', ['uri' => (string) $project->uri]);

        $this->indexingStatus->start();

        $fileCount = 0;
        foreach ($project as $file) {
            $fileCount += $this->walkFilesInternal($file);
        }

        $realpath = realpath(__DIR__ . '/../../../resources/php-stubs');
        $uri = Uri::createLocal('file://' . $realpath);
        $stubs = $this->files->create($uri->path, $this->filesystemReaderFactory);

        $fileCount += $this->walkFilesInternal($stubs);

        $this->indexingStatus->finish();

        $this->logger->info('Indexing finished: {count} files indexed', ['count' => $fileCount]);
    }

    /**
     * Maximum file size (bytes) to index. Files larger than this are skipped
     * to prevent memory exhaustion on auto-generated code.
     */
    private const int MAX_FILE_SIZE = 500_000;

    /**
     * Directories skipped during indexing.
     */
    private const array IGNORED_DIRS = [
        'node_modules',
        '.git',
        '.idea',
        'config',
        'resources',
        'runtime',
        'vendor',
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
    ];

    private function walkFilesInternal(VirtualFileInterface $file): int
    {
        if (in_array($file->name, self::IGNORED_DIRS, strict: true)) {
            return 0;
        }

        $count = 0;

        if ($file->count() === 0) {
            $this->runIndexers($file);
            $this->indexingStatus->fileIndexed();
            $count = 1;
        }

        foreach ($file as $child) {
            $count += $this->walkFilesInternal($child);
        }

        return $count;
    }

    private function runIndexers(VirtualFileInterface $file): void
    {
        foreach ($this->indexers as $indexer) {
            if (!$indexer->supports($file)) {
                continue;
            }

            await(
                async(function () use ($file, $indexer) {
                    $key = $indexer::getKey();
                    $map = $indexer->index($file);

                    $this->storage->write($key, $map, (string) $file->uri);
                })(),
            );
        }
    }
}
