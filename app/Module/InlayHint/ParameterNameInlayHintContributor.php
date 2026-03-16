<?php

declare(strict_types=1);

namespace App\Module\InlayHint;

use App\Core\Contracts\InlayHint\AsInlayHintContributor;
use App\Core\Contracts\InlayHint\InlayHintConsumer;
use App\Core\Contracts\InlayHint\InlayHintContext;
use App\Core\Contracts\InlayHint\InlayHintContributor;
use App\Module\Indexing\IndexLookup;
use App\Module\PsiFile\InMemoryPsiFileManager;
use App\Module\PsiFile\Tree;
use Override;
use PhpParser\Node;
use PhpParser\NodeFinder;

#[AsInlayHintContributor]
final class ParameterNameInlayHintContributor implements InlayHintContributor
{
    private readonly CallParameterResolver $resolver;

    public function __construct(
        private readonly InMemoryPsiFileManager $fileManager,
        IndexLookup $indexLookup,
    ) {
        $this->resolver = new CallParameterResolver($indexLookup);
    }

    #[Override]
    public function contribute(InlayHintContext $context, InlayHintConsumer $consumer): void
    {
        $file = $this->fileManager->findPsiFile($context->editor, $context->textDocumentIdentifier);
        if ($file === null) {
            return;
        }

        $document = $file->ast->document;
        $rangeStart = $context->range->start->line;
        $rangeEnd = $context->range->end->line;

        $callNodes = new NodeFinder()->find(
            $file->ast->children,
            static fn(Node $node): bool => (
                self::isCallNode($node)
                && Tree::toLspLine($node->getEndLine()) >= $rangeStart
                && Tree::toLspLine($node->getStartLine()) <= $rangeEnd
            ),
        );

        foreach ($callNodes as $callNode) {
            /** @var Node\Expr\FuncCall|Node\Expr\MethodCall|Node\Expr\StaticCall|Node\Expr\New_ $callNode */
            $args = $callNode->args;
            $parameters = $args !== [] ? $this->resolver->resolve($callNode) : null;
            if ($parameters === null) {
                continue;
            }

            for ($i = 0; $i < count($args); $i++) {
                $arg = $args[$i];
                if (!$arg instanceof Node\Arg) {
                    continue;
                }
                $hint = InlayHintFactory::parameterHint($arg, $parameters[$i] ?? null, $document);
                if ($hint !== null) {
                    $consumer($hint);
                }
            }
        }
    }

    private static function isCallNode(Node $node): bool
    {
        return (
            $node instanceof Node\Expr\FuncCall
            || $node instanceof Node\Expr\MethodCall
            || $node instanceof Node\Expr\StaticCall
            || $node instanceof Node\Expr\New_
        );
    }
}
