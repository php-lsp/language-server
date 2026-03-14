<?php

declare(strict_types=1);

namespace App\Tests\Support;

use Lsp\Workspace\File\VirtualFileInterface;
use Lsp\Workspace\Uri\Uri;

final class VirtualFileStub implements VirtualFileInterface, \IteratorAggregate
{
    public readonly string $name;
    public readonly string $path;
    public readonly ?string $extension;
    public readonly ?string $nameWithoutExtension;
    public readonly Uri $uri;

    private function __construct(string $name, ?string $extension)
    {
        $this->name = $name;
        $this->path = '/tmp/' . $name;
        $this->extension = $extension;
        $this->nameWithoutExtension = pathinfo($name, PATHINFO_FILENAME);
        $this->uri = Uri::createLocal('/tmp/' . $name);
    }

    public static function create(string $filename): self
    {
        $ext = pathinfo($filename, PATHINFO_EXTENSION);
        return new self($filename, $ext ?: null);
    }

    public function refresh(): void {}

    public function getIterator(): \Traversable
    {
        return new \ArrayIterator([]);
    }

    public function count(): int
    {
        return 0;
    }

    public function __toString(): string
    {
        return $this->path;
    }
}
