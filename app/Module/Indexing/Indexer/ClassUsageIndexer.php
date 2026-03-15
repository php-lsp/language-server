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
 * Indexes all class/interface/trait name usages (FullyQualified names excluding function calls).
 *
 * @extends AbstractPhpIndexer<array{string, int}>
 */
class ClassUsageIndexer extends AbstractPhpIndexer
{
    #[Override]
    public static function getKey(): string
    {
        return 'php.classUsages';
    }

    #[Override]
    protected function indexInternal(PHPPsiFile $phpFile): array
    {
        $finder = new NodeFinder();

        $names = $finder->findInstanceOf($phpFile->ast->children, Node\Name\FullyQualified::class);

        $results = [];
        foreach ($names as $name) {
            $parent = $name->getAttribute('parent');

            if ($parent instanceof Node\Expr\FuncCall) {
                continue;
            }

            if ($parent instanceof Node\Expr\ConstFetch) {
                continue;
            }

            $className = $name->toString();

            if (in_array(strtolower($className), ['true', 'false', 'null'], true)) {
                continue;
            }

            $pos = $name->getStartFilePos();
            $results["{$className}@{$pos}"] = [$className, $pos];
        }

        return $results;
    }
}
