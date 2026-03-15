<?php

declare(strict_types=1);

namespace App\Module\Indexing\Indexer;

use App\Core\Contracts\Indexing\AsIndexer;
use App\Module\Indexing\Data\EnumData;
use App\Module\Indexing\Data\NodeTypeExtractor;
use App\Module\PsiFile\PHPPsiFile;
use App\Module\PsiFile\Tree;
use PhpParser\Node\Stmt\Enum_;

/**
 * @extends AbstractPhpIndexer<EnumData>
 */
#[AsIndexer]
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
            if ($enum->name === null) {
                continue;
            }

            $fqn = $enum->namespacedName?->toString() ?? $enum->name->toString();

            $implements = [];
            foreach ($enum->implements as $impl) {
                $implements[] = $impl->toString();
            }

            $results[$fqn] = new EnumData(
                fqn: $fqn,
                startPosition: $enum->getStartFilePos(),
                endPosition: $enum->getEndFilePos(),
                backedType: NodeTypeExtractor::typeToString($enum->scalarType),
                implements: $implements,
            );
        }

        return $results;
    }
}
