<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Module\Indexing\Indexer\AbstractPhpIndexer;
use App\Module\PsiFile\PHPPsiFile;

final class IndexerTestHelper
{
    /**
     * Call the protected indexInternal method on an indexer without constructor.
     *
     * @template T of AbstractPhpIndexer
     * @param class-string<T> $indexerClass
     */
    public static function indexInternal(string $indexerClass, PHPPsiFile $psiFile): array
    {
        $instance = (new \ReflectionClass($indexerClass))->newInstanceWithoutConstructor();
        $method = new \ReflectionMethod($indexerClass, 'indexInternal');

        return $method->invoke($instance, $psiFile);
    }
}
