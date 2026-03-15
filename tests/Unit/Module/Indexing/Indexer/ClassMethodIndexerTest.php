<?php

declare(strict_types=1);

namespace App\Tests\Unit\Module\Indexing\Indexer;

use App\Module\Indexing\Data\MethodData;
use App\Module\Indexing\Data\Visibility;
use App\Module\Indexing\Indexer\ClassMethodIndexer;
use App\Tests\TestCase;
use App\Tests\Unit\Module\Indexing\IndexerTestHelper;
use PHPUnit\Framework\Attributes\Group;

#[Group('unit')]
final class ClassMethodIndexerTest extends TestCase
{
    public function testGetKey(): void
    {
        $this->assertSame('php.classMethods.fqn', ClassMethodIndexer::getKey());
    }

    public function testIndexClassMethods(): void
    {
        $code = '<?php class Foo { public function bar(): void {} }';
        $results = IndexerTestHelper::runIndexer(new ClassMethodIndexer($this->createMock(\App\Module\PsiFile\InMemoryPsiFileManager::class)), $code);

        $this->assertCount(1, $results);
        $this->assertArrayHasKey('Foo', $results);
        $this->assertCount(1, $results['Foo']);
        $this->assertInstanceOf(MethodData::class, $results['Foo'][0]);
        $this->assertSame('bar', $results['Foo'][0]->name);
        $this->assertSame('Foo', $results['Foo'][0]->className);
        $this->assertSame(Visibility::Public, $results['Foo'][0]->visibility);
        $this->assertSame('void', $results['Foo'][0]->returnType);
        $this->assertFalse($results['Foo'][0]->isStatic);
        $this->assertFalse($results['Foo'][0]->isAbstract);
    }

    public function testIndexPrivateMethod(): void
    {
        $code = '<?php class Foo { private function secret() {} }';
        $results = IndexerTestHelper::runIndexer(new ClassMethodIndexer($this->createMock(\App\Module\PsiFile\InMemoryPsiFileManager::class)), $code);

        $this->assertSame(Visibility::Private, $results['Foo'][0]->visibility);
    }

    public function testIndexProtectedMethod(): void
    {
        $code = '<?php class Foo { protected function internal() {} }';
        $results = IndexerTestHelper::runIndexer(new ClassMethodIndexer($this->createMock(\App\Module\PsiFile\InMemoryPsiFileManager::class)), $code);

        $this->assertSame(Visibility::Protected, $results['Foo'][0]->visibility);
    }

    public function testIndexStaticMethod(): void
    {
        $code = '<?php class Foo { public static function create(): self {} }';
        $results = IndexerTestHelper::runIndexer(new ClassMethodIndexer($this->createMock(\App\Module\PsiFile\InMemoryPsiFileManager::class)), $code);

        $this->assertTrue($results['Foo'][0]->isStatic);
    }

    public function testIndexAbstractMethod(): void
    {
        $code = '<?php abstract class Foo { abstract public function run(): void; }';
        $results = IndexerTestHelper::runIndexer(new ClassMethodIndexer($this->createMock(\App\Module\PsiFile\InMemoryPsiFileManager::class)), $code);

        $this->assertTrue($results['Foo'][0]->isAbstract);
    }

    public function testIndexMethodWithParameters(): void
    {
        $code = '<?php class Foo { public function bar(string $name, int $age = 0) {} }';
        $results = IndexerTestHelper::runIndexer(new ClassMethodIndexer($this->createMock(\App\Module\PsiFile\InMemoryPsiFileManager::class)), $code);

        $params = $results['Foo'][0]->parameters;
        $this->assertCount(2, $params);
        $this->assertSame('$name', $params[0]->name);
        $this->assertSame('string', $params[0]->type);
        $this->assertSame('$age', $params[1]->name);
        $this->assertTrue($params[1]->hasDefault);
    }

    public function testIndexInterfaceMethods(): void
    {
        $code = '<?php interface Foo { public function bar(): void; }';
        $results = IndexerTestHelper::runIndexer(new ClassMethodIndexer($this->createMock(\App\Module\PsiFile\InMemoryPsiFileManager::class)), $code);

        $this->assertArrayHasKey('Foo', $results);
        $this->assertSame('bar', $results['Foo'][0]->name);
    }

    public function testIndexTraitMethods(): void
    {
        $code = '<?php trait Foo { public function bar(): void {} }';
        $results = IndexerTestHelper::runIndexer(new ClassMethodIndexer($this->createMock(\App\Module\PsiFile\InMemoryPsiFileManager::class)), $code);

        $this->assertArrayHasKey('Foo', $results);
        $this->assertSame('bar', $results['Foo'][0]->name);
    }

    public function testIndexMultipleMethodsInClass(): void
    {
        $code = '<?php class Foo { public function a() {} private function b() {} protected function c() {} }';
        $results = IndexerTestHelper::runIndexer(new ClassMethodIndexer($this->createMock(\App\Module\PsiFile\InMemoryPsiFileManager::class)), $code);

        $this->assertCount(3, $results['Foo']);
    }

    public function testEmptyClassSkipped(): void
    {
        $code = '<?php class Foo {}';
        $results = IndexerTestHelper::runIndexer(new ClassMethodIndexer($this->createMock(\App\Module\PsiFile\InMemoryPsiFileManager::class)), $code);

        $this->assertCount(0, $results);
    }

    public function testNamespacedClassMethods(): void
    {
        $code = '<?php namespace App; class Foo { public function bar() {} }';
        $results = IndexerTestHelper::runIndexer(new ClassMethodIndexer($this->createMock(\App\Module\PsiFile\InMemoryPsiFileManager::class)), $code);

        $this->assertArrayHasKey('App\\Foo', $results);
    }
}
