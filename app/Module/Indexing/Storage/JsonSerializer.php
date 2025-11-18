<?php

namespace App\Module\Indexing\Storage;

class JsonSerializer implements SerializerInterface
{
    public function serialize(Entry $entry): string
    {
        return json_encode([
            'indexKey' => $entry->indexKey,
            'value' => $entry->value,
            'uri' => $entry->uri,
        ]);
    }

    public function deserialize(string $value): Entry
    {
        $decoded = json_decode($value, true);

        return new Entry(
            $decoded['indexKey'],
            $decoded['value'],
            $decoded['uri'],
        );
    }
}
