<?php

declare(strict_types=1);

namespace App\Module\Indexing\Indexer;

use App\Core\Contracts\Indexing\AsIndexer;
use App\Module\Indexing\Storage\IndexData\ConstantData;
use App\Module\PsiFile\PHPPsiFile;
use App\Module\PsiFile\Tree;
use PhpParser\Node;
use PhpParser\Node\Stmt\Const_;
use PhpParser\NodeFinder;

#[AsIndexer]
/**
 * @extends AbstractPhpIndexer<ConstantData>
 */
class GlobalConstantIndexer extends AbstractPhpIndexer
{
    public static function getKey(): string
    {
        return 'php.constants.fqn';
    }

    protected function indexInternal(PHPPsiFile $phpFile): array
    {
        $results = [];

        // Handle `const FOO = ...` statements
        $constStmts = Tree::childrenOfType($phpFile->ast, Const_::class);
        foreach ($constStmts as $constStmt) {
            foreach ($constStmt->consts as $const) {
                $name = $const->namespacedName?->toString() ?? $const->name->toString();

                $value = null;
                if ($const->value instanceof Node\Scalar\String_) {
                    $value = $const->value->value;
                } elseif ($const->value instanceof Node\Scalar\Int_) {
                    $value = (string) $const->value->value;
                } elseif ($const->value instanceof Node\Scalar\Float_) {
                    $value = (string) $const->value->value;
                }

                $results[$name] = new ConstantData(
                    name: $name,
                    ownerFqn: null,
                    startPosition: $constStmt->getStartFilePos(),
                    endPosition: $constStmt->getEndFilePos(),
                    type: null,
                    value: $value,
                );
            }
        }

        // Handle `define('FOO', ...)` calls
        $finder = new NodeFinder();
        /** @var Node\Expr\FuncCall[] $funcCalls */
        $funcCalls = $finder->findInstanceOf($phpFile->ast->children, Node\Expr\FuncCall::class);
        foreach ($funcCalls as $funcCall) {
            if (!$funcCall->name instanceof Node\Name) {
                continue;
            }
            if ($funcCall->name->toString() !== 'define') {
                continue;
            }
            if (count($funcCall->args) < 2) {
                continue;
            }
            $firstArg = $funcCall->args[0];
            if (!$firstArg instanceof Node\Arg || !$firstArg->value instanceof Node\Scalar\String_) {
                continue;
            }

            $name = $firstArg->value->value;

            $value = null;
            $secondArg = $funcCall->args[1];
            if ($secondArg instanceof Node\Arg) {
                if ($secondArg->value instanceof Node\Scalar\String_) {
                    $value = $secondArg->value->value;
                } elseif ($secondArg->value instanceof Node\Scalar\Int_) {
                    $value = (string) $secondArg->value->value;
                } elseif ($secondArg->value instanceof Node\Scalar\Float_) {
                    $value = (string) $secondArg->value->value;
                }
            }

            $results[$name] = new ConstantData(
                name: $name,
                ownerFqn: null,
                startPosition: $funcCall->getStartFilePos(),
                endPosition: $funcCall->getEndFilePos(),
                type: null,
                value: $value,
            );
        }

        return $results;
    }
}
