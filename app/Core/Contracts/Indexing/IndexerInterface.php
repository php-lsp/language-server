<?php

namespace App\Core\Contracts\Indexing;

use Lsp\Workspace\File\VirtualFileInterface;

/**
 * @template TValue
 */
interface IndexerInterface
{
    public static function getKey(): string;

    public function supports(VirtualFileInterface $file): bool;

    /**
     * @return list<TValue>
     */
    public function index(VirtualFileInterface $file): array;
}
