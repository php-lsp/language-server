<?php

declare(strict_types=1);

namespace App\Tests\Unit\Module\Indexing;

use App\Module\Indexing\Indexer\AbstractPhpIndexer;
use App\Module\PsiFile\PHPPsiFile;
use App\Module\PsiFile\PHPPsiFileParser;
use Lsp\Extension\DocumentManager\Editor\Document\Document;
use Lsp\Extension\DocumentManager\Editor\Document\Uri;

final class IndexerTestHelper
{
    private static ?PHPPsiFileParser $parser = null;

    public static function parsePhp(string $code): PHPPsiFile
    {
        if (self::$parser === null) {
            self::$parser = new PHPPsiFileParser();
        }

        $document = new Document(
            uri: new Uri('file:///test.php'),
            text: $code,
        );

        return new PHPPsiFile(self::$parser->parse($document));
    }

    /**
     * @template T
     * @param AbstractPhpIndexer<T> $indexer
     * @return array<T>
     */
    public static function runIndexer(AbstractPhpIndexer $indexer, string $phpCode): array
    {
        $psiFile = self::parsePhp($phpCode);

        $reflection = new \ReflectionMethod($indexer, 'indexInternal');

        return $reflection->invoke($indexer, $psiFile);
    }
}
