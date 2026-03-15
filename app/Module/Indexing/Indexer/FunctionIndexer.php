<?php

declare(strict_types=1);

namespace App\Module\Indexing\Indexer;

use App\Core\Contracts\Indexing\AsIndexer;
use App\Module\Indexing\Data\FunctionData;
use App\Module\Indexing\Data\NodeTypeExtractor;
use App\Module\PsiFile\PHPPsiFile;
use App\Module\PsiFile\Tree;
use PhpParser\Node\Stmt\Function_;

/**
 * @extends AbstractPhpIndexer<FunctionData>
 */
#[AsIndexer]
class FunctionIndexer extends AbstractPhpIndexer
{
    public static function getKey(): string
    {
        return 'php.functions.fqn';
    }

    protected function indexInternal(PHPPsiFile $phpFile): array
    {
        $functions = Tree::childrenOfType($phpFile->ast, Function_::class);

        $results = [];
        foreach ($functions as $function) {
            $fqn = $function->namespacedName?->toString() ?? $function->name->toString();

            $results[$fqn] = new FunctionData(
                fqn: $fqn,
                startPosition: $function->getStartFilePos(),
                endPosition: $function->getEndFilePos(),
                returnType: NodeTypeExtractor::typeToString($function->returnType),
                parameters: NodeTypeExtractor::extractParameters($function),
            );
        }

        return $results;
    }
}
