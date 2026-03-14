<?php

declare(strict_types=1);

namespace App\Module\Parallel\Task;

use Amp\Cancellation;
use Amp\Parallel\Worker\Task;
use Amp\Sync\Channel;
use PhpParser\ErrorHandler\Collecting;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\NodeVisitor\NodeConnectingVisitor;
use PhpParser\NodeVisitor\ParentConnectingVisitor;
use PhpParser\ParserFactory;

/**
 * Parses a PHP file and extracts all index data in a worker process.
 *
 * This combines parsing (CPU-heavy) + all indexer extractions into a single
 * worker task, avoiding multiple round-trips and maximizing CPU utilization.
 *
 * @implements Task<array<string, array<string|int, mixed>>, mixed, mixed>
 */
final class FileIndexTask implements Task
{
    public function __construct(
        public readonly string $uri,
        private readonly string $content,
    ) {}

    /**
     * @return array<string, array<string|int, mixed>> Map of indexKey => entries
     */
    public function run(Channel $channel, Cancellation $cancellation): array
    {
        $parser = (new ParserFactory())->createForNewestSupportedVersion();
        $errorHandler = new Collecting();

        try {
            $ast = $parser->parse($this->content, $errorHandler);

            if ($ast === null) {
                return [];
            }

            $traverser = new NodeTraverser(
                new NameResolver($errorHandler, ['preserveOriginalNames' => true]),
                new NodeConnectingVisitor(),
                new ParentConnectingVisitor(),
            );
            $ast = $traverser->traverse($ast);
        } catch (\Throwable) {
            return [];
        }

        $results = [];

        $results['php.classes.fqn'] = $this->extractClasses($ast);
        $results['php.interfaces.fqn'] = $this->extractInterfaces($ast);
        $results['php.traits.fqn'] = $this->extractTraits($ast);
        $results['php.functions.fqn'] = $this->extractFunctions($ast);
        $results['php.classMethods.fqn'] = $this->extractClassMethods($ast);

        return $results;
    }

    /**
     * @param array<\PhpParser\Node> $ast
     *
     * @return array<string, string>
     */
    private function extractClasses(array $ast): array
    {
        $results = [];

        foreach ($this->findNodes($ast, \PhpParser\Node\Stmt\Class_::class) as $class) {
            if ($class->namespacedName !== null) {
                $name = $class->namespacedName->toString();
                $results[$name] = $name;
            }
        }

        return $results;
    }

    /**
     * @param array<\PhpParser\Node> $ast
     *
     * @return list<string>
     */
    private function extractInterfaces(array $ast): array
    {
        $results = [];

        foreach ($this->findNodes($ast, \PhpParser\Node\Stmt\Interface_::class) as $interface) {
            if ($interface->namespacedName !== null) {
                $results[] = $interface->namespacedName->toString();
            }
        }

        return $results;
    }

    /**
     * @param array<\PhpParser\Node> $ast
     *
     * @return list<string>
     */
    private function extractTraits(array $ast): array
    {
        $results = [];

        foreach ($this->findNodes($ast, \PhpParser\Node\Stmt\Trait_::class) as $trait) {
            if ($trait->namespacedName !== null) {
                $results[] = $trait->namespacedName->toString();
            }
        }

        return $results;
    }

    /**
     * @param array<\PhpParser\Node> $ast
     *
     * @return array<string, list<string|int>>
     */
    private function extractFunctions(array $ast): array
    {
        $results = [];

        foreach ($this->findNodes($ast, \PhpParser\Node\Stmt\Function_::class) as $function) {
            if ($function->namespacedName !== null) {
                $name = $function->namespacedName->toString();
                $results[$name] = [$name, $function->getStartFilePos()];
            }
        }

        return $results;
    }

    /**
     * @param array<\PhpParser\Node> $ast
     *
     * @return array<string, list<string>>
     */
    private function extractClassMethods(array $ast): array
    {
        $results = [];

        foreach ($this->findNodes($ast, \PhpParser\Node\Stmt\Class_::class) as $class) {
            if ($class->namespacedName === null) {
                continue;
            }

            $className = $class->namespacedName->toString();

            foreach ($class->stmts as $stmt) {
                if ($stmt instanceof \PhpParser\Node\Stmt\ClassMethod) {
                    $results[$className][] = $stmt->name->toString();
                }
            }
        }

        return $results;
    }

    /**
     * Recursively find all nodes of a given type.
     *
     * @template T of \PhpParser\Node
     *
     * @param array<\PhpParser\Node> $nodes
     * @param class-string<T> $type
     *
     * @return list<T>
     */
    private function findNodes(array $nodes, string $type): array
    {
        $results = [];

        foreach ($nodes as $node) {
            if ($node instanceof $type) {
                $results[] = $node;
            }

            if ($node instanceof \PhpParser\Node\Stmt\Namespace_) {
                foreach ($node->stmts as $stmt) {
                    if ($stmt instanceof $type) {
                        $results[] = $stmt;
                    }
                }
            }
        }

        return $results;
    }
}
