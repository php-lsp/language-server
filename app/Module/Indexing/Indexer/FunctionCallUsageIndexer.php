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
 * Indexes all function call usages.
 *
 * @extends AbstractPhpIndexer<array{string, int}>
 */
class FunctionCallUsageIndexer extends AbstractPhpIndexer
{
    #[Override]
    public static function getKey(): string
    {
        return 'php.functionCallUsages';
    }

    #[Override]
    protected function indexInternal(PHPPsiFile $phpFile): array
    {
        $finder = new NodeFinder();

        $calls = $finder->findInstanceOf($phpFile->ast->children, Node\Expr\FuncCall::class);

        $results = [];
        foreach ($calls as $call) {
            if (!$call->name instanceof Node\Name) {
                continue;
            }

            $results[] = [$call->name->toString(), $call->getStartFilePos()];
        }

        return $results;
    }
}
