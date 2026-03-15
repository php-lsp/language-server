<?php

declare(strict_types=1);

namespace App\Tests\Unit\Module\Indexing\Data;

use App\Module\Indexing\Data\NodeTypeExtractor;
use App\Module\Indexing\Data\ParameterData;
use App\Module\Indexing\Data\Visibility;
use App\Tests\TestCase;
use App\Tests\Unit\Module\Indexing\IndexerTestHelper;
use PHPUnit\Framework\Attributes\Group;

#[Group('unit')]
final class NodeTypeExtractorTest extends TestCase
{
    public function testTypeToStringNull(): void
    {
        $this->assertNull(NodeTypeExtractor::typeToString(null));
    }

    public function testTypeToStringIdentifier(): void
    {
        $psi = IndexerTestHelper::parsePhp('<?php function foo(): int {}');
        $fn = $psi->ast->children[0];
        $this->assertSame('int', NodeTypeExtractor::typeToString($fn->returnType));
    }

    public function testTypeToStringNullable(): void
    {
        $psi = IndexerTestHelper::parsePhp('<?php function foo(): ?string {}');
        $fn = $psi->ast->children[0];
        $this->assertSame('?string', NodeTypeExtractor::typeToString($fn->returnType));
    }

    public function testTypeToStringUnion(): void
    {
        $psi = IndexerTestHelper::parsePhp('<?php function foo(): int|string {}');
        $fn = $psi->ast->children[0];
        $this->assertSame('int|string', NodeTypeExtractor::typeToString($fn->returnType));
    }

    public function testTypeToStringIntersection(): void
    {
        $psi = IndexerTestHelper::parsePhp('<?php function foo(): A&B {}');
        $fn = $psi->ast->children[0];
        $this->assertSame('A&B', NodeTypeExtractor::typeToString($fn->returnType));
    }

    public function testTypeToStringName(): void
    {
        $psi = IndexerTestHelper::parsePhp('<?php function foo(): DateTime {}');
        $fn = $psi->ast->children[0];
        $this->assertSame('DateTime', NodeTypeExtractor::typeToString($fn->returnType));
    }

    public function testExtractVisibilityPublic(): void
    {
        $psi = IndexerTestHelper::parsePhp('<?php class Foo { public function bar() {} }');
        $class = $psi->ast->children[0];
        $method = $class->stmts[0];
        $this->assertSame(Visibility::Public, NodeTypeExtractor::extractVisibility($method));
    }

    public function testExtractVisibilityPrivate(): void
    {
        $psi = IndexerTestHelper::parsePhp('<?php class Foo { private function bar() {} }');
        $class = $psi->ast->children[0];
        $method = $class->stmts[0];
        $this->assertSame(Visibility::Private, NodeTypeExtractor::extractVisibility($method));
    }

    public function testExtractVisibilityProtected(): void
    {
        $psi = IndexerTestHelper::parsePhp('<?php class Foo { protected function bar() {} }');
        $class = $psi->ast->children[0];
        $method = $class->stmts[0];
        $this->assertSame(Visibility::Protected, NodeTypeExtractor::extractVisibility($method));
    }

    public function testExtractVisibilityFromProperty(): void
    {
        $psi = IndexerTestHelper::parsePhp('<?php class Foo { private string $bar; }');
        $class = $psi->ast->children[0];
        $prop = $class->stmts[0];
        $this->assertSame(Visibility::Private, NodeTypeExtractor::extractVisibility($prop));
    }

    public function testExtractParameters(): void
    {
        $psi = IndexerTestHelper::parsePhp('<?php function foo(string $name, int $age = 0, bool ...$flags) {}');
        $fn = $psi->ast->children[0];
        $params = NodeTypeExtractor::extractParameters($fn);

        $this->assertCount(3, $params);

        $this->assertInstanceOf(ParameterData::class, $params[0]);
        $this->assertSame('$name', $params[0]->name);
        $this->assertSame('string', $params[0]->type);
        $this->assertFalse($params[0]->hasDefault);
        $this->assertFalse($params[0]->isVariadic);
        $this->assertFalse($params[0]->isPromoted);

        $this->assertSame('$age', $params[1]->name);
        $this->assertTrue($params[1]->hasDefault);

        $this->assertSame('$flags', $params[2]->name);
        $this->assertTrue($params[2]->isVariadic);
    }

    public function testExtractParametersNullableType(): void
    {
        $psi = IndexerTestHelper::parsePhp('<?php function foo(?string $name) {}');
        $fn = $psi->ast->children[0];
        $params = NodeTypeExtractor::extractParameters($fn);

        $this->assertTrue($params[0]->isNullable);
        $this->assertSame('?string', $params[0]->type);
    }

    public function testExtractParametersPromoted(): void
    {
        $psi = IndexerTestHelper::parsePhp('<?php class Foo { public function __construct(public string $name) {} }');
        $class = $psi->ast->children[0];
        $method = $class->stmts[0];
        $params = NodeTypeExtractor::extractParameters($method);

        $this->assertTrue($params[0]->isPromoted);
    }

    public function testExtractParametersNoParams(): void
    {
        $psi = IndexerTestHelper::parsePhp('<?php function foo() {}');
        $fn = $psi->ast->children[0];
        $params = NodeTypeExtractor::extractParameters($fn);

        $this->assertSame([], $params);
    }
}
