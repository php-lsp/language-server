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
        $this->assertArrayHasKey('Foo::bar', $results);
        $this->assertInstanceOf(MethodData::class, $results['Foo::bar']);
        $this->assertSame('bar', $results['Foo::bar']->name);
        $this->assertSame('Foo', $results['Foo::bar']->className);
        $this->assertSame(Visibility::Public, $results['Foo::bar']->visibility);
        $this->assertSame('void', $results['Foo::bar']->returnType);
        $this->assertFalse($results['Foo::bar']->isStatic);
        $this->assertFalse($results['Foo::bar']->isAbstract);
    }

    public function testIndexPrivateMethod(): void
    {
        $code = '<?php class Foo { private function secret() {} }';
        $results = IndexerTestHelper::runIndexer(new ClassMethodIndexer($this->createMock(\App\Module\PsiFile\InMemoryPsiFileManager::class)), $code);

        $this->assertSame(Visibility::Private, $results['Foo::secret']->visibility);
    }

    public function testIndexProtectedMethod(): void
    {
        $code = '<?php class Foo { protected function internal() {} }';
        $results = IndexerTestHelper::runIndexer(new ClassMethodIndexer($this->createMock(\App\Module\PsiFile\InMemoryPsiFileManager::class)), $code);

        $this->assertSame(Visibility::Protected, $results['Foo::internal']->visibility);
    }

    public function testIndexStaticMethod(): void
    {
        $code = '<?php class Foo { public static function create(): self {} }';
        $results = IndexerTestHelper::runIndexer(new ClassMethodIndexer($this->createMock(\App\Module\PsiFile\InMemoryPsiFileManager::class)), $code);

        $this->assertTrue($results['Foo::create']->isStatic);
    }

    public function testIndexAbstractMethod(): void
    {
        $code = '<?php abstract class Foo { abstract public function run(): void; }';
        $results = IndexerTestHelper::runIndexer(new ClassMethodIndexer($this->createMock(\App\Module\PsiFile\InMemoryPsiFileManager::class)), $code);

        $this->assertTrue($results['Foo::run']->isAbstract);
    }

    public function testIndexMethodWithParameters(): void
    {
        $code = '<?php class Foo { public function bar(string $name, int $age = 0) {} }';
        $results = IndexerTestHelper::runIndexer(new ClassMethodIndexer($this->createMock(\App\Module\PsiFile\InMemoryPsiFileManager::class)), $code);

        $params = $results['Foo::bar']->parameters;
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

        $this->assertArrayHasKey('Foo::bar', $results);
        $this->assertSame('bar', $results['Foo::bar']->name);
    }

    public function testIndexTraitMethods(): void
    {
        $code = '<?php trait Foo { public function bar(): void {} }';
        $results = IndexerTestHelper::runIndexer(new ClassMethodIndexer($this->createMock(\App\Module\PsiFile\InMemoryPsiFileManager::class)), $code);

        $this->assertArrayHasKey('Foo::bar', $results);
        $this->assertSame('bar', $results['Foo::bar']->name);
    }

    public function testIndexMultipleMethodsInClass(): void
    {
        $code = '<?php class Foo { public function a() {} private function b() {} protected function c() {} }';
        $results = IndexerTestHelper::runIndexer(new ClassMethodIndexer($this->createMock(\App\Module\PsiFile\InMemoryPsiFileManager::class)), $code);

        $this->assertCount(3, $results);
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

        $this->assertArrayHasKey('App\\Foo::bar', $results);
    }
}
