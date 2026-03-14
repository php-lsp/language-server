<?php

namespace App\Controller;

use App\Module\PsiFile\InMemoryPsiFileManager;
use App\Module\Workspace\ProjectManager;
use Lsp\Extension\DocumentManager\Editor\EditorInterface;
use Lsp\Protocol\Type\Position;
use Lsp\Protocol\Type\TextDocumentIdentifier;
use PhpParser\Node;
use PHPStan\Analyser\NodeScopeResolver;
use PHPStan\Analyser\ScopeContext;
use PHPStan\Analyser\ScopeFactory;
use PHPStan\DependencyInjection\Container;
use PHPStan\DependencyInjection\ContainerFactory;
use PHPStan\PhpDoc\TypeNodeResolver;
use PHPStan\Type\Type;

class PHPStanAnalyzer
{
    private ?Container $phpstanContainer = null;
    private ScopeFactory $scopeFactory;
    private NodeScopeResolver $nodeScopeResolver;
    private TypeNodeResolver $typeNodeResolver;

    public function __construct(
        //        private NodeScopeResolver $scopeResolver,
        private InMemoryPsiFileManager $fileManager,
        private ProjectManager $projectManager,
    ) {}

    public function getTypeAtPosition(EditorInterface $editor, TextDocumentIdentifier $textDocumentIdentifier, Position $position): ?Type
    {
        $project = $this->projectManager->getProject();
        if ($this->phpstanContainer === null) {
            $containerFactory = new ContainerFactory($project->path);
            $this->phpstanContainer = $containerFactory->create(
                tempDirectory: sys_get_temp_dir() . '/phpstan',
                additionalConfigFiles: [],
                analysedPaths: [],
            );
        }
        $this->scopeFactory = $this->phpstanContainer->getByType(ScopeFactory::class);
        $this->nodeScopeResolver = $this->phpstanContainer->getByType(NodeScopeResolver::class);
        $this->typeNodeResolver = $this->phpstanContainer->getByType(TypeNodeResolver::class);

        $psiFile = $this->fileManager->findPsiFile($editor, $textDocumentIdentifier);

        if ($psiFile === null) {
            return null;
        }

        $nodes = $psiFile->findAtPosition($position);

        $scope = $this->scopeFactory->create(
            ScopeContext::create($this->uriToPath($textDocumentIdentifier->uri))
        );

        $result = null;
        foreach ($nodes as $node) {
            if ($node instanceof Node\Expr) {
                $type = $scope->getType($node);
                if ($type !== null) {
                    $result = $type;
                }
                //                dump($type, $node);
            }
        }

        return $result;
    }

    private function uriToPath(string $uri): string
    {
        return str_replace('file://', '', $uri);
    }
}
