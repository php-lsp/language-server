<?php

declare(strict_types=1);

namespace App\Module\Indexing\Indexer;

use App\Core\Contracts\Indexing\AsIndexer;
use App\Module\Indexing\Data\InheritanceData;
use App\Module\PsiFile\PHPPsiFile;
use App\Module\PsiFile\Tree;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\Enum_;
use PhpParser\Node\Stmt\Interface_;

/**
 * @extends AbstractPhpIndexer<InheritanceData>
 */
#[AsIndexer]
class InheritanceIndexer extends AbstractPhpIndexer
{
    public static function getKey(): string
    {
        return 'php.inheritance';
    }

    protected function indexInternal(PHPPsiFile $phpFile): array
    {
        $results = [];

        $this->indexClasses($phpFile, $results);
        $this->indexInterfaces($phpFile, $results);
        $this->indexEnums($phpFile, $results);

        return $results;
    }

    /**
     * @param array<string, InheritanceData> $results
     */
    private function indexClasses(PHPPsiFile $phpFile, array &$results): void
    {
        $classes = Tree::childrenOfType($phpFile->ast, Class_::class);

        foreach ($classes as $class) {
            if ($class->name === null) {
                continue;
            }

            $fqn = $class->namespacedName?->toString() ?? $class->name->toString();
            $parents = [];

            if ($class->extends !== null) {
                $parents[] = $class->extends->toString();
            }

            foreach ($class->implements as $impl) {
                $parents[] = $impl->toString();
            }

            if ($parents !== []) {
                $results[$fqn] = new InheritanceData(
                    fqn: $fqn,
                    kind: 'class',
                    parents: $parents,
                );
            }
        }
    }

    /**
     * @param array<string, InheritanceData> $results
     */
    private function indexInterfaces(PHPPsiFile $phpFile, array &$results): void
    {
        $interfaces = Tree::childrenOfType($phpFile->ast, Interface_::class);

        foreach ($interfaces as $interface) {
            if ($interface->name === null) {
                continue;
            }

            $fqn = $interface->namespacedName?->toString() ?? $interface->name->toString();
            $parents = [];

            foreach ($interface->extends as $ext) {
                $parents[] = $ext->toString();
            }

            if ($parents !== []) {
                $results[$fqn] = new InheritanceData(
                    fqn: $fqn,
                    kind: 'interface',
                    parents: $parents,
                );
            }
        }
    }

    /**
     * @param array<string, InheritanceData> $results
     */
    private function indexEnums(PHPPsiFile $phpFile, array &$results): void
    {
        $enums = Tree::childrenOfType($phpFile->ast, Enum_::class);

        foreach ($enums as $enum) {
            if ($enum->name === null) {
                continue;
            }

            $fqn = $enum->namespacedName?->toString() ?? $enum->name->toString();
            $parents = [];

            foreach ($enum->implements as $impl) {
                $parents[] = $impl->toString();
            }

            if ($parents !== []) {
                $results[$fqn] = new InheritanceData(
                    fqn: $fqn,
                    kind: 'enum',
                    parents: $parents,
                );
            }
        }
    }
}
