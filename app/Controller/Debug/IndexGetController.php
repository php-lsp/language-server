<?php

declare(strict_types=1);

namespace App\Controller\Debug;

use App\Module\Indexing\Storage\DebugStorageInterface;
use Lsp\Kernel\Attribute\AsController;
use Lsp\Router\Attribute\Route;

/**
 * Gets a single entry by index key and entry key.
 *
 * Request:  { "method": "debug/index/get", "params": { "index": "php.classes.fqn", "key": "App\\Foo" } }
 * Response: { "key": "App\\Foo", "value": "App\\Foo", "uri": "file:///path/to/Foo.php" }
 */
#[AsController, Route('debug/index/get')]
final class IndexGetController
{
    public function __construct(
        private readonly DebugStorageInterface $storage,
    ) {}

    /**
     * @param object{index: string, key: string} $params
     * @return array<string, mixed>
     */
    public function __invoke(object $params): array
    {
        $indexKeys = $this->storage->getIndexKeys();

        if (!\in_array($params->index, $indexKeys, true)) {
            return [
                'error' => "Index '{$params->index}' not found",
                'available' => $indexKeys,
            ];
        }

        $entry = $this->storage->find($params->index, $params->key);

        if ($entry === null) {
            return ['error' => "Key '{$params->key}' not found in index '{$params->index}'"];
        }

        return [
            'key' => $entry->key,
            'value' => $this->serializeValue($entry->value),
            'uri' => $entry->uri,
        ];
    }

    private function serializeValue(mixed $value): mixed
    {
        if (\is_scalar($value) || $value === null) {
            return $value;
        }

        if (\is_array($value)) {
            return \array_map($this->serializeValue(...), $value);
        }

        if (\is_object($value)) {
            return $this->objectToArray($value);
        }

        return '(' . \get_debug_type($value) . ')';
    }

    /**
     * @return array<string, mixed>
     */
    private function objectToArray(object $object): array
    {
        $result = ['__class' => $object::class];

        $ref = new \ReflectionObject($object);

        foreach ($ref->getProperties() as $property) {
            $result[$property->getName()] = $this->serializeValue($property->getValue($object));
        }

        return $result;
    }
}
