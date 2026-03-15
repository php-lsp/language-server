<?php

declare(strict_types=1);

namespace App\Module\Indexing;

use Lsp\Workspace\File\FileFactoryInterface;
use Lsp\Workspace\File\FilesystemReader\FilesystemReaderFactoryInterface;
use Lsp\Workspace\File\VirtualFileInterface;
use Lsp\Workspace\Project\Project;
use Lsp\Workspace\Uri\Uri;

class IndexerFileCollector
{
    /**
     * Default directories skipped during indexing.
     */
    private const array DEFAULT_IGNORED_DIRS = [
        'node_modules',
        '.git',
        '.idea',
        'config',
        'resources',
        'runtime',
        'vendor',
        'tests',
    ];

    /**
     * @var list<string>
     */
    private array $ignoredDirs;

    public function __construct(
        private readonly FilesystemReaderFactoryInterface $filesystemReaderFactory,
        private readonly FileFactoryInterface $files,
    ) {}

    /**
     * @param list<string> $extraIgnoredDirs
     */
    public function setIgnoredDirs(array $extraIgnoredDirs = []): void
    {
        $this->ignoredDirs = array_unique([...self::DEFAULT_IGNORED_DIRS, ...$extraIgnoredDirs]);
    }

    /**
     * @return list<string>
     */
    public function getIgnoredDirs(): array
    {
        return $this->ignoredDirs ?? self::DEFAULT_IGNORED_DIRS;
    }

    /**
     * @return list<VirtualFileInterface>
     */
    public function collect(Project $project): array
    {
        $files = [];
        foreach ($project as $file) {
            $this->collectInternal($file, $files);
        }

        $realpath = (string) realpath(__DIR__ . '/../../../resources/php-stubs');
        $uri = Uri::createLocal('file://' . $realpath);
        $stubs = $this->files->create($uri->path, $this->filesystemReaderFactory);
        $this->collectInternal($stubs, $files);

        return $files;
    }

    /**
     * @param list<VirtualFileInterface> $files
     */
    private function collectInternal(VirtualFileInterface $file, array &$files): void
    {
        if (in_array($file->name, $this->getIgnoredDirs(), strict: true)) {
            return;
        }

        if ($file->count() === 0) {
            $files[] = $file;

            return;
        }

        foreach ($file as $child) {
            $this->collectInternal($child, $files);
        }
    }
}
