<?php

declare(strict_types=1);

namespace App\Tests\Unit\Module\TypeSystem;

use App\Module\TypeSystem\PHPStanBootstrap;
use App\Module\Workspace\ProjectManager;
use App\Tests\Support\MockHelper;
use App\Tests\TestCase;
use Lsp\Workspace\File\FilesystemReader\FilesystemReaderInterface;
use Lsp\Workspace\Project\Project;
use Lsp\Workspace\Uri\Uri;
use PHPStan\Analyser\MutatingScope;
use PHPStan\Analyser\NodeScopeResolver;
use PHPStan\Analyser\ScopeFactory;
use PHPStan\Reflection\ReflectionProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;

#[Group('unit')]
final class PHPStanBootstrapTest extends TestCase
{
    private static ?PHPStanBootstrap $bootstrap = null;

    private static function bootstrap(): PHPStanBootstrap
    {
        if (self::$bootstrap !== null) {
            return self::$bootstrap;
        }

        $fs = MockHelper::mock(FilesystemReaderInterface::class);
        $fs->method('count')->willReturn(0);

        $project = new Project(
            name: 'test',
            uri: new Uri(sys_get_temp_dir(), null),
            filesystem: $fs,
        );

        $projectManager = new ProjectManager();
        $projectManager->setProject($project);
        self::$bootstrap = new PHPStanBootstrap($projectManager);

        return self::$bootstrap;
    }

    #[TestDox('returns ScopeFactory instance')]
    public function testReturnsScopeFactory(): void
    {
        $this->assertInstanceOf(ScopeFactory::class, self::bootstrap()->getScopeFactory());
    }

    #[TestDox('returns NodeScopeResolver instance')]
    public function testReturnsNodeScopeResolver(): void
    {
        $this->assertInstanceOf(NodeScopeResolver::class, self::bootstrap()->getNodeScopeResolver());
    }

    #[TestDox('returns ReflectionProvider instance')]
    public function testReturnsReflectionProvider(): void
    {
        $this->assertInstanceOf(ReflectionProvider::class, self::bootstrap()->getReflectionProvider());
    }

    #[TestDox('creates scope for file')]
    public function testCreatesScopeForFile(): void
    {
        $scope = self::bootstrap()->createScopeForFile('/tmp/test.php');

        $this->assertInstanceOf(MutatingScope::class, $scope);
    }

    #[TestDox('caches container across multiple calls')]
    public function testCachesContainer(): void
    {
        $bootstrap = self::bootstrap();

        $resolver1 = $bootstrap->getNodeScopeResolver();
        $resolver2 = $bootstrap->getNodeScopeResolver();

        $this->assertSame($resolver1, $resolver2);
    }
}
