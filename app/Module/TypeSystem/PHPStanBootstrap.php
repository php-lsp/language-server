<?php

declare(strict_types=1);

namespace App\Module\TypeSystem;

use App\Module\Workspace\ProjectManager;
use PHPStan\Analyser\MutatingScope;
use PHPStan\Analyser\NodeScopeResolver;
use PHPStan\Analyser\ScopeContext;
use PHPStan\Analyser\ScopeFactory;
use PHPStan\DependencyInjection\Container;
use PHPStan\DependencyInjection\ContainerFactory;
use PHPStan\Reflection\ReflectionProvider;

final class PHPStanBootstrap
{
    private ?Container $container = null;

    /** @var list<string> */
    private array $analysedPaths = [];

    public function __construct(
        private readonly ProjectManager $projectManager,
    ) {}

    /**
     * @param list<string> $paths
     */
    public function setAnalysedPaths(array $paths): void
    {
        if ($paths !== $this->analysedPaths) {
            $this->analysedPaths = $paths;
            $this->container = null;
        }
    }

    public function getScopeFactory(): ScopeFactory
    {
        return $this->boot()->getByType(ScopeFactory::class);
    }

    public function getNodeScopeResolver(): NodeScopeResolver
    {
        return $this->boot()->getByType(NodeScopeResolver::class);
    }

    public function getReflectionProvider(): ReflectionProvider
    {
        return $this->boot()->getByType(ReflectionProvider::class);
    }

    public function createScopeForFile(string $filePath): MutatingScope
    {
        return $this->getScopeFactory()->create(ScopeContext::create($filePath));
    }

    private function boot(): Container
    {
        if ($this->container !== null) {
            return $this->container;
        }

        $projectPath = $this->projectManager->getProject()->path;

        $containerFactory = new ContainerFactory($projectPath);
        $this->container = $containerFactory->create(
            tempDirectory: sys_get_temp_dir() . '/phpstan-lsp',
            additionalConfigFiles: [],
            analysedPaths: $this->analysedPaths,
        );

        return $this->container;
    }
}
