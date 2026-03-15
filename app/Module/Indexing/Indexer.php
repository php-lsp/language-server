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
    ) {
        $this->indexers = iterator_to_array($indexers);
    }

    public function index(Project $project): void
    {
        $this->logger->info('Indexing project: ' . $project->uri);
        foreach ($project as $file) {
            $this->walkFilesInternal($file, 0);
        }

        $realpath = realpath(__DIR__ . '/../../../resources/php-stubs');
        $uri = Uri::createLocal('file://' . $realpath);
        $stubs = $this->files->create($uri->path, $this->filesystemReaderFactory);

        $this->walkFilesInternal($stubs, 0);
        $this->logger->info('Indexing finished');
    }

    private function walkFilesInternal(VirtualFileInterface $file, int $level): void
    {
        $ignored = [
            'node_modules',
            '.git',
            '.idea',
            'config',
            'resources',
            'runtime',
            //            'vendor',
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
            'tests',
        ];
        if (in_array($file->name, $ignored, strict: true)) {
            //            echo str_repeat('  ', $level) . '- ' . $file . " --- skipping ---\n";
            return;
        }

        $this->logger->info(str_repeat(' ', $level) . $file);

        if ($file->count() === 0) {
            $this->runIndexers($file);
        }

        foreach ($file as $child) {
            $this->walkFilesInternal($child, $level + 1);
        }
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
