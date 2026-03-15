<?php

declare(strict_types=1);

namespace App\Tests\Unit\Module\PsiFile;

use App\Module\PsiFile\PHPPsiFileParser;
use App\Module\PsiFile\SourceFileRoot;
use App\Tests\Support\PsiFileFactory;
use App\Tests\TestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;

#[Group('unit')]
final class PHPPsiFileParserTest extends TestCase
{
    #[TestDox('parses valid PHP code')]
    public function testParsesValidCode(): void
    {
        $parser = new PHPPsiFileParser();
        $document = PsiFileFactory::document('<?php class Foo {}');

        $result = $parser->parse($document);

        $this->assertInstanceOf(SourceFileRoot::class, $result);
        $this->assertNotEmpty($result->children);
        $this->assertEmpty($result->errors);
    }

    #[TestDox('collects errors for invalid code')]
    public function testCollectsErrors(): void
    {
        $parser = new PHPPsiFileParser();
        $document = PsiFileFactory::document('<?php class {}');

        $result = $parser->parse($document);

        $this->assertInstanceOf(SourceFileRoot::class, $result);
        $this->assertNotEmpty($result->errors);
    }
}
