<?php

declare(strict_types=1);

namespace App\Module\Documentation;

use App\Core\Contracts\Documentation\AsDocumentationContributor;
use App\Core\Contracts\Documentation\DocumentationConsumer;
use App\Core\Contracts\Documentation\DocumentationContext;
use App\Core\Contracts\Documentation\DocumentationContributor;
use App\Module\PsiFile\InMemoryPsiFileManager;
use PhpParser\Node;

#[AsDocumentationContributor]
final class NodesTraceDocumentationContributor implements DocumentationContributor
{
    public function __construct(
        private InMemoryPsiFileManager $fileManager,
    )
    {
    }

    public function contribute(DocumentationContext $context, DocumentationConsumer $consumer): void
    {
        $psiFile = $this->fileManager->findPsiFile($context->editor, $context->textDocumentIdentifier);

        if ($psiFile !== null) {
            $nodes = $psiFile->findAtPosition($context->position);

            $nodesClasses = implode(" => ", array_map(fn(Node $node) => $node::class, array_reverse($nodes)));

            if (!empty($nodesClasses)) {
                $consumer(sprintf('Node classes %s', $nodesClasses));
            }
        }
    }
}
