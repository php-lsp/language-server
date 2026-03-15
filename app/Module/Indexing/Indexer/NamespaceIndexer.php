<?php

declare(strict_types=1);

namespace App\Module\Indexing\Indexer;

use App\Core\Contracts\Indexing\AsIndexer;
use App\Module\Indexing\Data\NamespaceData;
use App\Module\PsiFile\PHPPsiFile;
use App\Module\PsiFile\Tree;
use Override;
use PhpParser\Node\Stmt\Namespace_;

/**
 * @extends AbstractPhpIndexer<NamespaceData>
 */
#[AsIndexer]
class NamespaceIndexer extends AbstractPhpIndexer
{
    #[Override]
    public static function getKey(): string
    {
        return 'php.namespaces.fqn';
    }

    #[Override]
    protected function indexInternal(PHPPsiFile $phpFile): array
    {
        $namespaces = Tree::childrenOfType($phpFile->ast, Namespace_::class);

        $results = [];
        foreach ($namespaces as $namespace) {
            if ($namespace->name === null) {
                continue;
            }

            $fqn = $namespace->name->toString();

            $results[$fqn] = new NamespaceData(
                fqn: $fqn,
                startPosition: $namespace->getStartFilePos(),
                endPosition: $namespace->getEndFilePos(),
            );
        }

        return $results;
    }
}
