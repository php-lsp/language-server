<?php

namespace App\Module\Indexing\Storage;

interface SerializerInterface
{
    public function serialize(Entry $entry): string;

    public function deserialize(string $value): Entry;
}
