<?php

declare(strict_types=1);

namespace App\Module\Indexing\Indexer;

use App\Core\Contracts\Indexing\AsIndexer;
use App\Module\PsiFile\PHPPsiFile;
use PhpParser\Node;
use PhpParser\NodeFinder;

#[AsIndexer]
/**
 * Indexes all class/interface/trait name usages (FullyQualified names excluding function calls).
 *
 * @extends AbstractPhpIndexer<array{string, int}>
 */
class ClassUsageIndexer extends AbstractPhpIndexer
{
    public static function getKey(): string
    {
        return 'php.classUsages';
    }

    protected function indexInternal(PHPPsiFile $phpFile): array
    {
        $finder = new NodeFinder();

        /** @var Node\Name\FullyQualified[] $names */
        $names = $finder->findInstanceOf($phpFile->ast->children, Node\Name\FullyQualified::class);

        $results = [];
        foreach ($names as $name) {
            $parent = $name->getAttribute('parent');

            if ($parent instanceof Node\Expr\FuncCall) {
                continue;
            }

            $results[] = [$name->toString(), $name->getStartFilePos()];
        }

        return $results;
    }
}
