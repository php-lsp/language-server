<?php

declare(strict_types=1);

namespace App\Module\Documentation;

use App\Core\Contracts\Documentation\AsDocumentationContributor;
use App\Core\Contracts\Documentation\DocumentationConsumer;
use App\Core\Contracts\Documentation\DocumentationContext;
use App\Core\Contracts\Documentation\DocumentationContributor;
use App\Module\PsiFile\InMemoryPsiFileManager;
use App\Module\PsiFile\Tree;
use Override;
use PhpParser\Node;

#[AsDocumentationContributor]
final class NodesTraceDocumentationContributor implements DocumentationContributor
{
    public function __construct(
        private InMemoryPsiFileManager $fileManager,
    ) {}

    #[Override]
    public function contribute(DocumentationContext $context, DocumentationConsumer $consumer): void
    {
        $psiFile = $this->fileManager->findPsiFile($context->editor, $context->textDocumentIdentifier);

        if ($psiFile !== null) {
            $node = $psiFile->findLastAtPosition($context->position);

            if ($node !== null) {
                $nodesClasses = implode(' => ', array_map(
                    static fn(Node $node) => $node::class,
                    iterator_to_array(Tree::getParentNodesIncluding($node)),
                ));

                if ($nodesClasses !== '') {
                    $consumer(sprintf('Node classes %s', $nodesClasses));
                }
            }
        }
    }
}
