<?php

declare(strict_types=1);

namespace App\Module\Indexing\Indexer;

use App\Core\Contracts\Indexing\AsIndexer;
use App\Module\PsiFile\PHPPsiFile;
use App\Module\PsiFile\Tree;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\Enum_;
use PhpParser\Node\Stmt\Interface_;

#[AsIndexer]
/**
 * Indexes parent-child and implementation relationships.
 *
 * Stores arrays of [childFQN, parentFQN, relation] tuples where
 * relation is 'extends' or 'implements'.
 *
 * @extends AbstractPhpIndexer<array{string, string, string}>
 */
class InheritanceIndexer extends AbstractPhpIndexer
{
    public static function getKey(): string
    {
        return 'php.inheritance';
    }

    protected function indexInternal(PHPPsiFile $phpFile): array
    {
        $results = [];

        // Classes
        $classes = Tree::childrenOfType($phpFile->ast, Class_::class);
        foreach ($classes as $class) {
            $fqn = $class->namespacedName->toString();

            if ($class->extends !== null) {
                $parentFqn = $class->extends->toString();
                $results[] = [$fqn, $parentFqn, 'extends'];
            }

            foreach ($class->implements as $implement) {
                $results[] = [$fqn, $implement->toString(), 'implements'];
            }
        }

        // Interfaces
        $interfaces = Tree::childrenOfType($phpFile->ast, Interface_::class);
        foreach ($interfaces as $interface) {
            $fqn = $interface->namespacedName->toString();

            foreach ($interface->extends as $extend) {
                $results[] = [$fqn, $extend->toString(), 'extends'];
            }
        }

        // Enums
        $enums = Tree::childrenOfType($phpFile->ast, Enum_::class);
        foreach ($enums as $enum) {
            $fqn = $enum->namespacedName->toString();

            foreach ($enum->implements as $implement) {
                $results[] = [$fqn, $implement->toString(), 'implements'];
            }
        }

        return $results;
    }
}
