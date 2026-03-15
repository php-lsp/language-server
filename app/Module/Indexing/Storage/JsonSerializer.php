<?php

declare(strict_types=1);

namespace App\Module\Indexing\Storage;

use Override;

class JsonSerializer implements SerializerInterface
{
    #[Override]
    public function serialize(Entry $entry): string
    {
        return json_encode([
            'key' => $entry->key,
            'value' => $entry->value,
            'uri' => $entry->uri,
        ]);
    }

    #[Override]
    public function deserialize(string $value): Entry
    {
        $decoded = json_decode($value, associative: true);

        return new Entry(
            $decoded['key'],
            $decoded['value'],
            $decoded['uri'],
        );
    }
}
