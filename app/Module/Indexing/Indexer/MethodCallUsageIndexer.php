<?php

declare(strict_types=1);

namespace App\Module\Indexing\Indexer;

use App\Core\Contracts\Indexing\AsIndexer;
use App\Module\PsiFile\PHPPsiFile;
use Override;
use PhpParser\Node;
use PhpParser\NodeFinder;

#[AsIndexer]
/**
 * Indexes all method call usages (instance and static).
 *
 * @extends AbstractPhpIndexer<array{string, int, ?string}>
 */
class MethodCallUsageIndexer extends AbstractPhpIndexer
{
    #[Override]
    public static function getKey(): string
    {
        return 'php.methodCallUsages';
    }

    #[Override]
    protected function indexInternal(PHPPsiFile $phpFile): array
    {
        $finder = new NodeFinder();

        $results = [];

        $instanceCalls = $finder->findInstanceOf($phpFile->ast->children, Node\Expr\MethodCall::class);
        foreach ($instanceCalls as $call) {
            if (!$call->name instanceof Node\Identifier) {
                continue;
            }

            $results[] = [$call->name->toString(), $call->name->getStartFilePos(), null];
        }

        $staticCalls = $finder->findInstanceOf($phpFile->ast->children, Node\Expr\StaticCall::class);
        foreach ($staticCalls as $call) {
            if (!$call->name instanceof Node\Identifier) {
                continue;
            }

            $className = $call->class instanceof Node\Name ? $call->class->toString() : null;
            $results[] = [$call->name->toString(), $call->name->getStartFilePos(), $className];
        }

        return $results;
    }
}
