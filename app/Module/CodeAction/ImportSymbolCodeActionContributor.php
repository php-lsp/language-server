<?php

declare(strict_types=1);

namespace App\Module\CodeAction;

use App\Core\Contracts\CodeAction\AsCodeActionContributor;
use App\Core\Contracts\CodeAction\CodeActionConsumer;
use App\Core\Contracts\CodeAction\CodeActionContext;
use App\Core\Contracts\CodeAction\CodeActionContributor;
use App\Module\Indexing\Indexer\ClassIndexer;
use App\Module\Indexing\Indexer\FunctionIndexer;
use App\Module\Indexing\Indexer\InterfaceIndexer;
use App\Module\Indexing\Indexer\TraitIndexer;
use App\Module\Indexing\IndexLookup;
use App\Module\PsiFile\InMemoryPsiFileManager;
use App\Module\PsiFile\Tree;
use Lsp\Protocol\Type\CodeAction;
use Lsp\Protocol\Type\CodeActionKind;
use Lsp\Protocol\Type\Position;
use Lsp\Protocol\Type\Range;
use Lsp\Protocol\Type\TextEdit;
use Lsp\Protocol\Type\WorkspaceEdit;
use Override;
use PhpParser\Node;

#[AsCodeActionContributor]
final class ImportSymbolCodeActionContributor implements CodeActionContributor
{
    public function __construct(
        private readonly IndexLookup $indexLookup,
        private readonly InMemoryPsiFileManager $fileManager,
    ) {}

    #[Override]
    public function contribute(CodeActionContext $context, CodeActionConsumer $consumer): void
    {
        $file = $this->fileManager->findPsiFile($context->editor, $context->textDocumentIdentifier);
        if ($file === null) {
            return;
        }

        $element = $file->findLastAtPosition($context->range->start);
        if ($element === null) {
            return;
        }

        if (!$element instanceof Node\Name) {
            return;
        }

        $originalName = $element->getAttribute('originalName');
        if ($originalName instanceof Node\Name) {
            $name = $originalName->toString();
        } else {
            $name = $element->getLast();
        }

        if (str_contains($name, '\\')) {
            return;
        }

        $existingUses = $this->getExistingUseStatements($file);
        foreach ($existingUses as $useFqn) {
            if ($this->getShortName($useFqn) === $name) {
                return;
            }
        }

        $insertPosition = $this->findUseInsertPosition($file);

        $matchingFqns = $this->findMatchingSymbols($name);

        foreach ($matchingFqns as $fqn) {
            $shortName = $this->getShortName($fqn);
            if ($shortName !== $name) {
                continue;
            }

            $useStatement = "use {$fqn};\n";

            $consumer(new CodeAction(
                title: "Import {$fqn}",
                kind: CodeActionKind::QuickFix,
                edit: new WorkspaceEdit(
                    changes: [
                        $context->textDocumentIdentifier->uri => [
                            new TextEdit(
                                range: new Range($insertPosition, $insertPosition),
                                newText: $useStatement,
                            ),
                        ],
                    ],
                ),
            ));
        }
    }

    /**
     * @return list<string>
     */
    private function getExistingUseStatements(\App\Module\PsiFile\PHPPsiFile $file): array
    {
        $uses = [];
        $useNodes = Tree::childrenOfType($file->ast, Node\UseItem::class);

        foreach ($useNodes as $use) {
            $uses[] = $use->name->toString();
        }

        return $uses;
    }

    private function findUseInsertPosition(\App\Module\PsiFile\PHPPsiFile $file): Position
    {
        $useNodes = Tree::childrenOfType($file->ast, Node\Stmt\Use_::class);

        if ($useNodes !== []) {
            $lastUse = end($useNodes);

            return new Position(Tree::nodeEndLine($lastUse) + 1, 0);
        }

        $namespaceNodes = Tree::childrenOfType($file->ast, Node\Stmt\Namespace_::class);
        if ($namespaceNodes !== []) {
            return new Position(Tree::nodeStartLine($namespaceNodes[0]) + 1, 0);
        }

        return new Position(1, 0);
    }

    /**
     * @return list<string>
     */
    private function findMatchingSymbols(string $shortName): array
    {
        $matches = [];
        $indexers = [ClassIndexer::class, InterfaceIndexer::class, TraitIndexer::class, FunctionIndexer::class];

        foreach ($indexers as $indexerClass) {
            foreach ($this->indexLookup->findByKey($indexerClass) as $entry) {
                $fqn = $entry->value->fqn;
                if ($this->getShortName($fqn) === $shortName) {
                    $matches[] = $fqn;
                }
            }
        }

        return $matches;
    }

    private function getShortName(string $fqn): string
    {
        $parts = explode('\\', $fqn);

        return end($parts);
    }
}
