<?php

declare(strict_types=1);

namespace App\Module\TypeSystem;

use App\Core\UriHelper;
use App\Module\PsiFile\InMemoryPsiFileManager;
use App\Module\PsiFile\SourceFileRoot;
use App\Module\PsiFile\Tree;
use Lsp\Extension\DocumentManager\Editor\EditorInterface;
use Lsp\Protocol\Type\Position;
use Lsp\Protocol\Type\TextDocumentIdentifier;
use Override;
use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Type\Type;
use Psr\Log\LoggerInterface;

final class TypeResolver implements TypeResolverInterface
{
    public function __construct(
        private readonly PHPStanBootstrap $phpstan,
        private readonly InMemoryPsiFileManager $fileManager,
        private readonly LoggerInterface $logger,
    ) {}

    #[Override]
    public function resolveAtPosition(
        EditorInterface $editor,
        TextDocumentIdentifier $textDocumentIdentifier,
        Position $position,
    ): ?TypeResult {
        $psiFile = $this->fileManager->findPsiFile($editor, $textDocumentIdentifier);
        if ($psiFile === null) {
            return null;
        }

        $targetNode = $psiFile->findLastAtPosition($position);
        if ($targetNode === null) {
            return null;
        }

        $filePath = UriHelper::toFilePath($textDocumentIdentifier->uri);
        if ($filePath === null) {
            return null;
        }

        return $this->resolveNodeInFile($targetNode, $psiFile->ast, $filePath);
    }

    public function resolveNodeInFile(Node $targetNode, SourceFileRoot $ast, string $filePath): ?TypeResult
    {
        $targetStartPos = $targetNode->getStartFilePos();
        $targetEndPos = $targetNode->getEndFilePos();

        $foundType = null;
        $foundScope = null;
        $foundNode = null;

        /** @var array<Node\Stmt> $stmts */
        $stmts = $ast->children;

        try {
            $nodeScopeResolver = $this->phpstan->getNodeScopeResolver();
            $scope = $this->phpstan->createScopeForFile($filePath);

            $nodeScopeResolver->setAnalysedFiles([$filePath]);

            $nodeScopeResolver->processNodes(
                $stmts,
                $scope,
                static function (Node $node, Scope $scope) use (
                    $targetStartPos,
                    $targetEndPos,
                    &$foundType,
                    &$foundScope,
                    &$foundNode,
                ): void {
                    if ($node->getStartFilePos() > $targetEndPos) {
                        return;
                    }

                    if (
                        $node->getStartFilePos() <= $targetStartPos
                        && $targetEndPos <= $node->getEndFilePos()
                        && $node instanceof Node\Expr
                    ) {
                        $foundType = $scope->getType($node);
                        $foundScope = $scope;
                        $foundNode = $node;
                    }
                },
            );
        } catch (\Throwable $e) {
            $this->logger->warning('PHPStan type resolution failed: {error}', [
                'error' => $e->getMessage(),
                'file' => $filePath,
            ]);

            return null;
        }

        if ($foundType === null || $foundScope === null) {
            return null;
        }

        return new TypeResult(
            type: $foundType,
            scope: $foundScope,
            node: $foundNode,
        );
    }

    #[Override]
    public function resolveVariableAtPosition(
        EditorInterface $editor,
        TextDocumentIdentifier $textDocumentIdentifier,
        Position $position,
        string $variableName,
    ): ?Type {
        $psiFile = $this->fileManager->findPsiFile($editor, $textDocumentIdentifier);
        if ($psiFile === null) {
            return null;
        }

        $filePath = UriHelper::toFilePath($textDocumentIdentifier->uri);
        if ($filePath === null) {
            return null;
        }

        $targetLine = Tree::toParserLine($position->line);
        $foundType = null;

        /** @var array<Node\Stmt> $stmts */
        $stmts = $psiFile->getChildren();

        try {
            $nodeScopeResolver = $this->phpstan->getNodeScopeResolver();
            $scope = $this->phpstan->createScopeForFile($filePath);

            $nodeScopeResolver->setAnalysedFiles([$filePath]);

            $nodeScopeResolver->processNodes(
                $stmts,
                $scope,
                static function (Node $node, Scope $scope) use ($targetLine, $variableName, &$foundType): void {
                    if ($node->getStartLine() <= $targetLine && $scope->hasVariableType($variableName)->yes()) {
                        $foundType = $scope->getVariableType($variableName);
                    }
                },
            );
        } catch (\Throwable $e) {
            $this->logger->warning('PHPStan variable resolution failed: {error}', [
                'error' => $e->getMessage(),
            ]);

            return null;
        }

        return $foundType;
    }
}
