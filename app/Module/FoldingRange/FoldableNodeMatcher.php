<?php

declare(strict_types=1);

namespace App\Module\FoldingRange;

use PhpParser\Node;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\Match_;
use PhpParser\Node\Scalar\InterpolatedString;
use PhpParser\Node\Stmt\Case_;
use PhpParser\Node\Stmt\Catch_;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Do_;
use PhpParser\Node\Stmt\Else_;
use PhpParser\Node\Stmt\ElseIf_;
use PhpParser\Node\Stmt\Enum_;
use PhpParser\Node\Stmt\Finally_;
use PhpParser\Node\Stmt\For_;
use PhpParser\Node\Stmt\Foreach_;
use PhpParser\Node\Stmt\Function_;
use PhpParser\Node\Stmt\If_;
use PhpParser\Node\Stmt\Interface_;
use PhpParser\Node\Stmt\Switch_;
use PhpParser\Node\Stmt\Trait_;
use PhpParser\Node\Stmt\TryCatch;
use PhpParser\Node\Stmt\While_;

/**
 * Determines whether a given AST node should produce a folding range.
 */
final class FoldableNodeMatcher
{
    /**
     * Node types that produce region folding ranges.
     *
     * @var list<class-string<Node>>
     */
    private const array FOLDABLE_TYPES = [
        Class_::class,
        Interface_::class,
        Trait_::class,
        Enum_::class,
        ClassMethod::class,
        Function_::class,
        If_::class,
        ElseIf_::class,
        Else_::class,
        While_::class,
        Do_::class,
        For_::class,
        Foreach_::class,
        TryCatch::class,
        Catch_::class,
        Finally_::class,
        Switch_::class,
        Case_::class,
        Match_::class,
        Array_::class,
        InterpolatedString::class,
    ];

    public static function isFoldable(Node $node): bool
    {
        foreach (self::FOLDABLE_TYPES as $type) {
            if ($node instanceof $type) {
                return true;
            }
        }

        return false;
    }
}
