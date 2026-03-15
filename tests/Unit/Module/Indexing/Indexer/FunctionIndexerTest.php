<?php

declare(strict_types=1);

namespace App\Tests\Unit\Module\Indexing\Indexer;

use App\Module\Indexing\Data\FunctionData;
use App\Module\Indexing\Data\ParameterData;
use App\Module\Indexing\Indexer\FunctionIndexer;
use App\Tests\TestCase;
use App\Tests\Unit\Module\Indexing\IndexerTestHelper;
use PHPUnit\Framework\Attributes\Group;

#[Group('unit')]
final class FunctionIndexerTest extends TestCase
{
    public function testGetKey(): void
    {
        $this->assertSame('php.functions.fqn', FunctionIndexer::getKey());
    }

    public function testIndexSimpleFunction(): void
    {
        $code = '<?php function hello() {}';
        $results = IndexerTestHelper::runIndexer(new FunctionIndexer($this->createMock(\App\Module\PsiFile\InMemoryPsiFileManager::class)), $code);

        $this->assertCount(1, $results);
        $this->assertArrayHasKey('hello', $results);
        $this->assertInstanceOf(FunctionData::class, $results['hello']);
        $this->assertSame('hello', $results['hello']->fqn);
        $this->assertNull($results['hello']->returnType);
        $this->assertSame([], $results['hello']->parameters);
    }

    public function testIndexNamespacedFunction(): void
    {
        $code = '<?php namespace App\\Utils; function helper() {}';
        $results = IndexerTestHelper::runIndexer(new FunctionIndexer($this->createMock(\App\Module\PsiFile\InMemoryPsiFileManager::class)), $code);

        $this->assertArrayHasKey('App\\Utils\\helper', $results);
    }

    public function testIndexFunctionWithReturnType(): void
    {
        $code = '<?php function getNumber(): int {}';
        $results = IndexerTestHelper::runIndexer(new FunctionIndexer($this->createMock(\App\Module\PsiFile\InMemoryPsiFileManager::class)), $code);

        $this->assertSame('int', $results['getNumber']->returnType);
    }

    public function testIndexFunctionWithNullableReturnType(): void
    {
        $code = '<?php function maybeString(): ?string {}';
        $results = IndexerTestHelper::runIndexer(new FunctionIndexer($this->createMock(\App\Module\PsiFile\InMemoryPsiFileManager::class)), $code);

        $this->assertSame('?string', $results['maybeString']->returnType);
    }

    public function testIndexFunctionWithUnionReturnType(): void
    {
        $code = '<?php function mixed(): int|string {}';
        $results = IndexerTestHelper::runIndexer(new FunctionIndexer($this->createMock(\App\Module\PsiFile\InMemoryPsiFileManager::class)), $code);

        $this->assertSame('int|string', $results['mixed']->returnType);
    }

    public function testIndexFunctionWithParameters(): void
    {
        $code = '<?php function greet(string $name, int $age = 0) {}';
        $results = IndexerTestHelper::runIndexer(new FunctionIndexer($this->createMock(\App\Module\PsiFile\InMemoryPsiFileManager::class)), $code);

        $params = $results['greet']->parameters;
        $this->assertCount(2, $params);

        $this->assertInstanceOf(ParameterData::class, $params[0]);
        $this->assertSame('$name', $params[0]->name);
        $this->assertSame('string', $params[0]->type);
        $this->assertFalse($params[0]->hasDefault);
        $this->assertFalse($params[0]->isVariadic);

        $this->assertSame('$age', $params[1]->name);
        $this->assertSame('int', $params[1]->type);
        $this->assertTrue($params[1]->hasDefault);
    }

    public function testIndexFunctionWithVariadicParam(): void
    {
        $code = '<?php function sum(int ...$numbers) {}';
        $results = IndexerTestHelper::runIndexer(new FunctionIndexer($this->createMock(\App\Module\PsiFile\InMemoryPsiFileManager::class)), $code);

        $this->assertTrue($results['sum']->parameters[0]->isVariadic);
    }

    public function testIndexMultipleFunctions(): void
    {
        $code = '<?php function a() {} function b() {} function c() {}';
        $results = IndexerTestHelper::runIndexer(new FunctionIndexer($this->createMock(\App\Module\PsiFile\InMemoryPsiFileManager::class)), $code);

        $this->assertCount(3, $results);
    }

    public function testPositionsAreRecorded(): void
    {
        $code = '<?php function foo() {}';
        $results = IndexerTestHelper::runIndexer(new FunctionIndexer($this->createMock(\App\Module\PsiFile\InMemoryPsiFileManager::class)), $code);

        $this->assertGreaterThanOrEqual(0, $results['foo']->startPosition);
        $this->assertGreaterThan($results['foo']->startPosition, $results['foo']->endPosition);
    }
}
