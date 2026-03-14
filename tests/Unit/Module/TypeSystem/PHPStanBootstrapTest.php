<?php

declare(strict_types=1);

namespace App\Tests\Unit\Module\TypeSystem;

use App\Tests\Support\TypeResolverTestHelper;
use App\Tests\TestCase;
use PHPStan\Analyser\MutatingScope;
use PHPStan\Analyser\NodeScopeResolver;
use PHPStan\Analyser\ScopeFactory;
use PHPStan\Reflection\ReflectionProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;

#[Group('unit')]
final class PHPStanBootstrapTest extends TestCase
{
    #[TestDox('returns ScopeFactory instance')]
    public function testReturnsScopeFactory(): void
    {
        $this->assertInstanceOf(ScopeFactory::class, TypeResolverTestHelper::bootstrap()->getScopeFactory());
    }

    #[TestDox('returns NodeScopeResolver instance')]
    public function testReturnsNodeScopeResolver(): void
    {
        $this->assertInstanceOf(NodeScopeResolver::class, TypeResolverTestHelper::bootstrap()->getNodeScopeResolver());
    }

    #[TestDox('returns ReflectionProvider instance')]
    public function testReturnsReflectionProvider(): void
    {
        $this->assertInstanceOf(ReflectionProvider::class, TypeResolverTestHelper::bootstrap()->getReflectionProvider());
    }

    #[TestDox('creates scope for file')]
    public function testCreatesScopeForFile(): void
    {
        $scope = TypeResolverTestHelper::bootstrap()->createScopeForFile('/tmp/test.php');

        $this->assertInstanceOf(MutatingScope::class, $scope);
    }

    #[TestDox('caches container across multiple calls')]
    public function testCachesContainer(): void
    {
        $bootstrap = TypeResolverTestHelper::bootstrap();

        $resolver1 = $bootstrap->getNodeScopeResolver();
        $resolver2 = $bootstrap->getNodeScopeResolver();

        $this->assertSame($resolver1, $resolver2);
    }
}
