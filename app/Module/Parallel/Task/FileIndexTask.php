<?php

declare(strict_types=1);

namespace App\Module\Parallel\Task;

use Amp\Cancellation;
use Amp\Parallel\Worker\Task;
use Amp\Sync\Channel;
use Override;
use PhpParser\ErrorHandler\Collecting;
use PhpParser\Node;
use PhpParser\Node\Stmt;
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
    #[Override]
    public function run(Channel $channel, Cancellation $cancellation): array
    {
        $ast = $this->parseAst();

        if ($ast === null) {
            return [];
        }

        return [
            'php.classes.fqn' => $this->extractClasses($ast),
            'php.interfaces.fqn' => $this->extractInterfaces($ast),
            'php.traits.fqn' => $this->extractTraits($ast),
            'php.functions.fqn' => $this->extractFunctions($ast),
            'php.classMethods.fqn' => $this->extractClassMethods($ast),
        ];
    }

    /**
     * @return list<Node>|null
     */
    private function parseAst(): ?array
    {
        $parser = new ParserFactory()->createForNewestSupportedVersion();
        $errorHandler = new Collecting();

        try {
            $ast = $parser->parse($this->content, $errorHandler);

            if ($ast === null) {
                return null;
            }

            $traverser = new NodeTraverser(
                new NameResolver($errorHandler, ['preserveOriginalNames' => true]),
                new NodeConnectingVisitor(),
                new ParentConnectingVisitor(),
            );

            return $traverser->traverse($ast);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param list<Node> $ast
     *
     * @return array<string, string>
     */
    private function extractClasses(array $ast): array
    {
        $results = [];

        foreach ($this->findNodes($ast, Stmt\Class_::class) as $class) {
            if ($class->namespacedName === null) {
                continue;
            }

            $name = $class->namespacedName->toString();
            $results[$name] = $name;
        }

        return $results;
    }

    /**
     * @param list<Node> $ast
     *
     * @return list<string>
     */
    private function extractInterfaces(array $ast): array
    {
        $results = [];

        foreach ($this->findNodes($ast, Stmt\Interface_::class) as $interface) {
            if ($interface->namespacedName === null) {
                continue;
            }

            $results[] = $interface->namespacedName->toString();
        }

        return $results;
    }

    /**
     * @param list<Node> $ast
     *
     * @return list<string>
     */
    private function extractTraits(array $ast): array
    {
        $results = [];

        foreach ($this->findNodes($ast, Stmt\Trait_::class) as $trait) {
            if ($trait->namespacedName === null) {
                continue;
            }

            $results[] = $trait->namespacedName->toString();
        }

        return $results;
    }

    /**
     * @param list<Node> $ast
     *
     * @return array<string, list<string|int>>
     */
    private function extractFunctions(array $ast): array
    {
        $results = [];

        foreach ($this->findNodes($ast, Stmt\Function_::class) as $function) {
            if ($function->namespacedName === null) {
                continue;
            }

            $name = $function->namespacedName->toString();
            $results[$name] = [$name, $function->getStartFilePos()];
        }

        return $results;
    }

    /**
     * @param list<Node> $ast
     *
     * @return array<string, list<string>>
     */
    private function extractClassMethods(array $ast): array
    {
        $results = [];

        foreach ($this->findNodes($ast, Stmt\Class_::class) as $class) {
            if ($class->namespacedName === null) {
                continue;
            }

            $className = $class->namespacedName->toString();

            foreach ($class->stmts as $stmt) {
                if (!$stmt instanceof Stmt\ClassMethod) {
                    continue;
                }

                $results[$className][] = $stmt->name->toString();
            }
        }

        return $results;
    }

    /**
     * Recursively find all nodes of a given type.
     *
     * @template T of Node
     *
     * @param list<Node> $nodes
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

            if (!$node instanceof Stmt\Namespace_) {
                continue;
            }

            foreach ($node->stmts as $stmt) {
                if (!$stmt instanceof $type) {
                    continue;
                }

                $results[] = $stmt;
            }
        }

        return $results;
    }
}
