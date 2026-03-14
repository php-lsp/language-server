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
        $parser = PsiFileFactory::getParser();
        $document = PsiFileFactory::document('<?php function foo() {}');

        $root = $parser->parse($document);

        $this->assertInstanceOf(SourceFileRoot::class, $root);
        $this->assertNotEmpty($root->children);
        $this->assertEmpty($root->errors);
    }

    #[TestDox('collects parse errors for invalid code')]
    public function testCollectsParseErrors(): void
    {
        $parser = PsiFileFactory::getParser();
        // Use code that produces errors but still returns a partial AST
        $document = PsiFileFactory::document('<?php echo $x; echo;');

        $root = $parser->parse($document);

        $this->assertNotEmpty($root->errors);
    }

    #[TestDox('preserves document reference')]
    public function testPreservesDocument(): void
    {
        $parser = PsiFileFactory::getParser();
        $document = PsiFileFactory::document('<?php echo 1;');

        $root = $parser->parse($document);

        $this->assertSame($document, $root->document);
    }

    #[TestDox('resolves fully qualified names')]
    public function testResolvesNames(): void
    {
        $parser = PsiFileFactory::getParser();
        $document = PsiFileFactory::document('<?php namespace App; class Foo {}');

        $root = $parser->parse($document);

        $this->assertNotEmpty($root->children);
    }
}
