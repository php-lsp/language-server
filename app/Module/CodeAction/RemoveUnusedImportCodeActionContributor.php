<?php

declare(strict_types=1);

namespace App\Module\CodeAction;

use App\Core\Contracts\CodeAction\AsCodeActionContributor;
use App\Core\Contracts\CodeAction\CodeActionConsumer;
use App\Core\Contracts\CodeAction\CodeActionContext;
use App\Core\Contracts\CodeAction\CodeActionContributor;
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
use PhpParser\NodeFinder;

#[AsCodeActionContributor]
final class RemoveUnusedImportCodeActionContributor implements CodeActionContributor
{
    public function __construct(
        private readonly InMemoryPsiFileManager $fileManager,
    ) {}

    #[Override]
    public function contribute(CodeActionContext $context, CodeActionConsumer $consumer): void
    {
        $file = $this->fileManager->findPsiFile($context->editor, $context->textDocumentIdentifier);
        if ($file === null) {
            return;
        }

        $useStatements = Tree::childrenOfType($file, Node\Stmt\Use_::class);
        if ($useStatements === []) {
            return;
        }

        $finder = new NodeFinder();
        $allNames = $finder->findInstanceOf($file->getChildren(), Node\Name::class);

        $usedNames = [];
        foreach ($allNames as $name) {
            $parent = $name->getAttribute('parent');
            if ($parent instanceof Node\UseItem) {
                continue;
            }

            $originalName = $name->getAttribute('originalName');
            if ($originalName instanceof Node\Name) {
                $parts = $originalName->getParts();
                $usedNames[$parts[0]] = true;
            } else {
                $parts = $name->getParts();
                $usedNames[$parts[0]] = true;
            }
        }

        foreach ($useStatements as $useStatement) {
            foreach ($useStatement->uses as $use) {
                $alias = $use->getAlias()->toString();
                if (array_key_exists($alias, $usedNames)) {
                    continue;
                }

                $startLine = Tree::nodeStartLine($useStatement);
                // Go one line past the end to include the trailing newline
                $endLine = Tree::nodeEndLine($useStatement) + 1;

                $consumer(new CodeAction(
                    title: 'Remove unused import: ' . $use->name->toString(),
                    kind: CodeActionKind::QuickFix,
                    edit: new WorkspaceEdit(
                        changes: [
                            $context->textDocumentIdentifier->uri => [
                                new TextEdit(
                                    range: new Range(
                                        new Position($startLine, 0),
                                        new Position($endLine, 0),
                                    ),
                                    newText: '',
                                ),
                            ],
                        ],
                    ),
                ));
            }
        }
    }
}
