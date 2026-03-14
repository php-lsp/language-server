<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Module\Indexing\IndexLookup;
use App\Module\Indexing\Storage\Entry;
use App\Module\Indexing\Storage\InMemoryStorage;

final class IndexTestHelper
{
    public static function createLookup(array $entries = []): IndexLookup
    {
        $storage = new InMemoryStorage();
        foreach ($entries as $indexKey => $values) {
            foreach ($values as $uri => $keyValues) {
                $storage->write($indexKey, $keyValues, $uri);
            }
        }

        return new IndexLookup($storage);
    }
}
