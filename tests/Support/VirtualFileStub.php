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

    /** @var list<VirtualFileInterface> */
    private array $children;

    /**
     * @param list<VirtualFileInterface> $children
     */
    private function __construct(string $name, ?string $extension, array $children = [])
    {
        $this->name = $name;
        $this->path = '/tmp/' . $name;
        $this->extension = $extension;
        $this->nameWithoutExtension = pathinfo($name, PATHINFO_FILENAME);
        $this->uri = Uri::createLocal('/tmp/' . $name);
        $this->children = $children;
    }

    public static function create(string $filename): self
    {
        $ext = pathinfo($filename, PATHINFO_EXTENSION);
        return new self($filename, $ext ?: null);
    }

    /**
     * @param list<VirtualFileInterface> $children
     */
    public static function createDirectory(string $name, array $children): self
    {
        return new self($name, null, $children);
    }

    public function refresh(): void {}

    public function getIterator(): \Traversable
    {
        return new \ArrayIterator($this->children);
    }

    public function count(): int
    {
        return \count($this->children);
    }

    public function __toString(): string
    {
        return $this->path;
    }
}
