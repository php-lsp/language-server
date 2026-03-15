<?php

declare(strict_types=1);

namespace App\Tests\Unit\Module\Indexing\Storage;

use App\Module\Indexing\Storage\Entry;
use App\Module\Indexing\Storage\JsonSerializer;
use App\Tests\TestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;

#[Group('unit')]
final class JsonSerializerTest extends TestCase
{
    #[TestDox('serialize produces valid JSON')]
    public function testSerialize(): void
    {
        $serializer = new JsonSerializer();
        $entry = new Entry('key', 'value', 'file:///test.php');

        $json = $serializer->serialize($entry);
        $decoded = json_decode($json, true);

        $this->assertSame('key', $decoded['key']);
        $this->assertSame('value', $decoded['value']);
        $this->assertSame('file:///test.php', $decoded['uri']);
    }

    #[TestDox('deserialize restores Entry')]
    public function testDeserialize(): void
    {
        $serializer = new JsonSerializer();
        $json = json_encode(['key' => 'k', 'value' => 'v', 'uri' => 'file:///a.php']);

        $entry = $serializer->deserialize($json);

        $this->assertInstanceOf(Entry::class, $entry);
        $this->assertSame('k', $entry->key);
        $this->assertSame('v', $entry->value);
        $this->assertSame('file:///a.php', $entry->uri);
    }

    #[TestDox('round-trip preserves data')]
    public function testRoundTrip(): void
    {
        $serializer = new JsonSerializer();
        $original = new Entry('test', ['nested' => true], 'file:///b.php');

        $restored = $serializer->deserialize($serializer->serialize($original));

        $this->assertSame($original->key, $restored->key);
        $this->assertSame($original->value, $restored->value);
        $this->assertSame($original->uri, $restored->uri);
    }
}
