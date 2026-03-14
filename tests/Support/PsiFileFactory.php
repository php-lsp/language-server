<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Module\PsiFile\PHPPsiFile;
use App\Module\PsiFile\PHPPsiFileParser;
use Lsp\Extension\DocumentManager\Editor\Document\Document;
use Lsp\Extension\DocumentManager\Editor\Document\Uri;

final class PsiFileFactory
{
    private static ?PHPPsiFileParser $parser = null;

    public static function getParser(): PHPPsiFileParser
    {
        return self::$parser ??= new PHPPsiFileParser();
    }

    public static function fromCode(string $code, string $uriString = 'file:///test.php'): PHPPsiFile
    {
        $document = self::document($code, $uriString);
        $root = self::getParser()->parse($document);

        return new PHPPsiFile($root);
    }

    public static function document(string $code = '<?php ', string $uriString = 'file:///test.php'): Document
    {
        return new Document(new Uri($uriString), $code, 1);
    }
}
