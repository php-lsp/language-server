<?php

namespace App\Module\Indexing\Storage;

class JsonSerializer implements SerializerInterface
{
    public function serialize(Entry $entry): string
    {
        return json_encode([
            'key' => $entry->key,
            'value' => $entry->value,
            'uri' => $entry->uri,
        ]);
    }

    public function deserialize(string $value): Entry
    {
        $decoded = json_decode($value, true);

        return new Entry(
            $decoded['key'],
            $decoded['value'],
            $decoded['uri'],
        );
    }
}
