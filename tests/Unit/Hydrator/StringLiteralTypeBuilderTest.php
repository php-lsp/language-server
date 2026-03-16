<?php

declare(strict_types=1);

namespace App\Tests\Unit\Hydrator;

use App\Hydrator\StringLiteralType;
use App\Hydrator\StringLiteralTypeBuilder;
use App\Tests\Support\MockHelper;
use App\Tests\TestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;
use TypeLang\Mapper\Runtime\Parser\TypeParserInterface;
use TypeLang\Mapper\Runtime\Repository\TypeRepositoryInterface;
use TypeLang\Parser\Node\Literal\StringLiteralNode;
use TypeLang\Parser\Node\Name;
use TypeLang\Parser\Node\Stmt\NamedTypeNode;

#[Group('unit')]
final class StringLiteralTypeBuilderTest extends TestCase
{
    #[TestDox('isSupported returns true for StringLiteralNode')]
    public function testIsSupportedReturnsTrueForStringLiteralNode(): void
    {
        $builder = new StringLiteralTypeBuilder();
        $node = new StringLiteralNode('test');

        $this->assertTrue($builder->isSupported($node));
    }

    #[TestDox('isSupported returns false for other TypeStatement types')]
    public function testIsSupportedReturnsFalseForOtherTypes(): void
    {
        $builder = new StringLiteralTypeBuilder();
        $node = new NamedTypeNode(new Name('string'));

        $this->assertFalse($builder->isSupported($node));
    }

    #[TestDox('build creates StringLiteralType with correct value')]
    public function testBuildCreatesStringLiteralTypeWithCorrectValue(): void
    {
        $builder = new StringLiteralTypeBuilder();
        $node = new StringLiteralNode('my-value');
        $types = MockHelper::mock(TypeRepositoryInterface::class);
        $parser = MockHelper::mock(TypeParserInterface::class);

        $result = $builder->build($node, $types, $parser);

        $this->assertInstanceOf(StringLiteralType::class, $result);

        // Verify the built type matches the correct value
        $context = MockHelper::mock(\TypeLang\Mapper\Runtime\Context::class);
        $this->assertTrue($result->match('my-value', $context));
        $this->assertFalse($result->match('other', $context));
    }
}
