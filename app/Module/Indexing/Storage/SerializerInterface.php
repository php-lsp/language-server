<?php

declare(strict_types=1);

namespace App\Module\Indexing\Storage;

interface SerializerInterface
{
    public function serialize(Entry $entry): string;

    public function deserialize(string $value): Entry;
}
