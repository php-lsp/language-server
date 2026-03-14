<?php

declare(strict_types=1);

namespace App\Module\Indexing\Indexer;

use App\Core\Contracts\Indexing\AsIndexer;
use App\Module\Indexing\Storage\IndexData\EnumData;
use App\Module\PsiFile\PHPPsiFile;
use App\Module\PsiFile\Tree;
use PhpParser\Node;
use PhpParser\Node\Stmt\Enum_;

#[AsIndexer]
/**
 * @extends AbstractPhpIndexer<EnumData>
 */
class EnumIndexer extends AbstractPhpIndexer
{
    public static function getKey(): string
    {
        return 'php.enums.fqn';
    }

    protected function indexInternal(PHPPsiFile $phpFile): array
    {
        $enums = Tree::childrenOfType($phpFile->ast, Enum_::class);

        $results = [];
        foreach ($enums as $enum) {
            $fqn = $enum->namespacedName->toString();
            $implements = [];
            foreach ($enum->implements as $implement) {
                $implements[] = $implement->toString();
            }

            $backedType = null;
            if ($enum->scalarType !== null) {
                $backedType = $enum->scalarType->toString();
            }

            $results[$fqn] = new EnumData(
                fqn: $fqn,
                startPosition: $enum->getStartFilePos(),
                endPosition: $enum->getEndFilePos(),
                backedType: $backedType,
                implements: $implements,
            );
        }

        return $results;
    }
}
