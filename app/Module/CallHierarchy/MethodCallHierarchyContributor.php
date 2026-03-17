<?php

declare(strict_types=1);

namespace App\Module\CallHierarchy;

use App\Core\Contracts\CallHierarchy\AsCallHierarchyContributor;
use App\Core\Contracts\CallHierarchy\CallHierarchyContributor;
use App\Core\Contracts\CallHierarchy\IncomingCallsConsumer;
use App\Core\Contracts\CallHierarchy\IncomingCallsContext;
use App\Core\Contracts\CallHierarchy\OutgoingCallsConsumer;
use App\Core\Contracts\CallHierarchy\OutgoingCallsContext;
use App\Core\Contracts\CallHierarchy\PrepareCallHierarchyConsumer;
use App\Core\Contracts\CallHierarchy\PrepareCallHierarchyContext;
use App\Module\Indexing\Data\MethodData;
use App\Module\Indexing\Indexer\ClassMethodIndexer;
use App\Module\Indexing\Indexer\MethodCallUsageIndexer;
use App\Module\Indexing\IndexLookup;
use App\Module\PsiFile\InMemoryPsiFileManager;
use App\Module\PsiFile\PHPPsiFile;
use App\Module\PsiFile\Tree;
use Lsp\Extension\DocumentManager\Editor\EditorInterface;
use Lsp\Protocol\Type\CallHierarchyIncomingCall;
use Lsp\Protocol\Type\CallHierarchyItem;
use Lsp\Protocol\Type\CallHierarchyOutgoingCall;
use Lsp\Protocol\Type\Position;
use Lsp\Protocol\Type\Range;
use Lsp\Protocol\Type\SymbolKind;
use Lsp\Protocol\Type\TextDocumentIdentifier;
use Override;
use PhpParser\Node;
use PhpParser\NodeFinder;

#[AsCallHierarchyContributor]
final class MethodCallHierarchyContributor implements CallHierarchyContributor
{
    public function __construct(
        private readonly IndexLookup $indexLookup,
        private readonly InMemoryPsiFileManager $fileManager,
    ) {}

    #[Override]
    public function prepare(PrepareCallHierarchyContext $context, PrepareCallHierarchyConsumer $consumer): void
    {
        $file = $this->fileManager->findPsiFile($context->editor, $context->textDocumentIdentifier);
        if ($file === null) {
            return;
        }

        $element = $file->findLastAtPosition($context->position);
        $resolved = $this->resolveMethodInfo($element);
        if ($resolved === null) {
            return;
        }

        [$methodName, $className] = $resolved;
        $lookupKey = $className . '::' . $methodName;

        $entry = $this->indexLookup->findEntry(ClassMethodIndexer::class, $lookupKey);
        if ($entry === null) {
            return;
        }

        /** @var MethodData $data */
        $data = $entry->value;
        $source = $this->fileManager->findPsiFile(
            $context->editor,
            new TextDocumentIdentifier($entry->uri),
        );
        if ($source === null) {
            return;
        }

        [$startLine, $startCol] = Tree::toLineColumn($source->getDocument(), $data->startPosition);
        [$endLine, $endCol] = Tree::toLineColumn($source->getDocument(), $data->endPosition);

        $consumer(new CallHierarchyItem(
            name: $lookupKey,
            kind: SymbolKind::MethodKind,
            uri: $entry->uri,
            range: new Range(
                new Position($startLine, $startCol),
                new Position($endLine, $endCol),
            ),
            selectionRange: new Range(
                new Position($startLine, $startCol),
                new Position($startLine, $startCol),
            ),
            detail: $data->returnType,
            data: ['type' => 'method', 'name' => $methodName, 'class' => $className],
        ));
    }

    #[Override]
    public function incomingCalls(IncomingCallsContext $context, IncomingCallsConsumer $consumer): void
    {
        $data = $context->item->data;
        if (!is_array($data) || ($data['type'] ?? null) !== 'method') {
            return;
        }

        $methodName = $data['name'] ?? null;
        if (!is_string($methodName)) {
            return;
        }

        /** @var array<string, array{item: CallHierarchyItem, ranges: list<Range>}> $callers */
        $callers = [];

        foreach ($this->indexLookup->findByKey(MethodCallUsageIndexer::class) as $entry) {
            /** @var array{string, int, ?string} $value */
            $value = $entry->value;
            if ($value[0] !== $methodName) {
                continue;
            }

            /** @var non-empty-string $uri */
            $uri = $entry->uri;
            $source = $this->fileManager->findPsiFile(
                $context->editor,
                new TextDocumentIdentifier($uri),
            );
            if ($source === null) {
                continue;
            }

            /** @var array{int<0, 2147483647>, int<0, 2147483647>} $lineCol */
            $lineCol = Tree::toLineColumn($source->getDocument(), $value[1]);
            $callRange = new Range(
                new Position($lineCol[0], $lineCol[1]),
                new Position($lineCol[0], $lineCol[1]),
            );

            $callerInfo = $this->findEnclosingCallable($source, $value[1]);
            $callerKey = $uri . ':' . ($callerInfo['name'] ?? '__global__');

            if (!array_key_exists($callerKey, $callers)) {
                if ($callerInfo !== null) {
                    $callers[$callerKey] = [
                        'item' => new CallHierarchyItem(
                            name: $callerInfo['name'],
                            kind: $callerInfo['kind'],
                            uri: $uri,
                            range: $callerInfo['range'],
                            selectionRange: $callerInfo['selectionRange'],
                            data: $callerInfo['data'],
                        ),
                        'ranges' => [],
                    ];
                } else {
                    continue;
                }
            }

            $callers[$callerKey]['ranges'][] = $callRange;
        }

        foreach ($callers as $caller) {
            $consumer(new CallHierarchyIncomingCall(
                from: $caller['item'],
                fromRanges: $caller['ranges'],
            ));
        }
    }

    #[Override]
    public function outgoingCalls(OutgoingCallsContext $context, OutgoingCallsConsumer $consumer): void
    {
        $data = $context->item->data;
        if (!is_array($data) || ($data['type'] ?? null) !== 'method') {
            return;
        }

        $methodName = $data['name'] ?? null;
        $className = $data['class'] ?? null;
        if (!is_string($methodName) || !is_string($className)) {
            return;
        }

        $lookupKey = $className . '::' . $methodName;
        $entry = $this->indexLookup->findEntry(ClassMethodIndexer::class, $lookupKey);
        if ($entry === null) {
            return;
        }

        /** @var MethodData $methodData */
        $methodData = $entry->value;

        $source = $this->fileManager->findPsiFile(
            $context->editor,
            new TextDocumentIdentifier($entry->uri),
        );
        if ($source === null) {
            return;
        }

        $this->collectOutgoingMethodCalls($source, $methodData, $entry->uri, $context->editor, $consumer);
    }

    /**
     * @return array{name: string, kind: SymbolKind, range: Range, selectionRange: Range, data: array<string, string>}|null
     */
    private function findEnclosingCallable(PHPPsiFile $file, int $pos): ?array
    {
        $nodes = $file->findAtPosition($pos);
        foreach (array_reverse($nodes) as $node) {
            if ($node instanceof Node\Stmt\Function_) {
                $name = $node->namespacedName?->toString() ?? $node->name->toString();
                $range = Tree::getRange($node, $file);
                $selectionRange = Tree::getRange($node->name, $file);

                return [
                    'name' => $name,
                    'kind' => SymbolKind::FunctionKind,
                    'range' => $range,
                    'selectionRange' => $selectionRange,
                    'data' => ['type' => 'function', 'name' => $name],
                ];
            }
            if ($node instanceof Node\Stmt\ClassMethod) {
                $classNode =
                    Tree::parentOfType($node, Node\Stmt\Class_::class) ?? Tree::parentOfType(
                        $node,
                        Node\Stmt\Interface_::class,
                    ) ?? Tree::parentOfType($node, Node\Stmt\Trait_::class);
                $className = $classNode?->namespacedName?->toString() ?? $classNode?->name?->toString() ?? '';
                $mName = $node->name->toString();
                $range = Tree::getRange($node, $file);
                $selectionRange = Tree::getRange($node->name, $file);

                return [
                    'name' => $className . '::' . $mName,
                    'kind' => SymbolKind::MethodKind,
                    'range' => $range,
                    'selectionRange' => $selectionRange,
                    'data' => ['type' => 'method', 'name' => $mName, 'class' => $className],
                ];
            }
        }

        return null;
    }

    private function collectOutgoingMethodCalls(
        PHPPsiFile $source,
        MethodData $methodData,
        string $uri,
        EditorInterface $editor,
        OutgoingCallsConsumer $consumer,
    ): void {
        $finder = new NodeFinder();

        $classNodes = Tree::childrenOfTypes(
            $source->ast,
            Node\Stmt\Class_::class,
            Node\Stmt\Interface_::class,
            Node\Stmt\Trait_::class,
        );

        $targetMethod = null;
        foreach ($classNodes as $classNode) {
            $cName = $classNode->namespacedName?->toString() ?? $classNode->name?->toString() ?? '';
            if ($cName !== $methodData->className) {
                continue;
            }
            $methods = Tree::childrenOfType($classNode, Node\Stmt\ClassMethod::class);
            foreach ($methods as $method) {
                if ($method->name->toString() !== $methodData->name) {
                    continue;
                }

                $targetMethod = $method;
                break 2;
            }
        }

        if ($targetMethod === null || $targetMethod->stmts === null) {
            return;
        }

        /** @var array<string, array{item: CallHierarchyItem, ranges: list<Range>}> $outgoing */
        $outgoing = [];

        $methodCalls = $finder->findInstanceOf($targetMethod->stmts, Node\Expr\MethodCall::class);
        foreach ($methodCalls as $call) {
            if (!$call->name instanceof Node\Identifier) {
                continue;
            }

            $calledName = $call->name->toString();
            $key = 'method:' . $calledName;

            [$line, $col] = Tree::toLineColumn($source->getDocument(), $call->name->getStartFilePos());
            $callRange = new Range(
                new Position($line, $col),
                new Position($line, $col),
            );

            if (!array_key_exists($key, $outgoing)) {
                $targetItem = $this->resolveMethodTarget($calledName, $call, $source, $editor);
                if ($targetItem === null) {
                    continue;
                }
                $outgoing[$key] = [
                    'item' => $targetItem,
                    'ranges' => [],
                ];
            }

            $outgoing[$key]['ranges'][] = $callRange;
        }

        $staticCalls = $finder->findInstanceOf($targetMethod->stmts, Node\Expr\StaticCall::class);
        foreach ($staticCalls as $call) {
            if (!$call->name instanceof Node\Identifier) {
                continue;
            }

            $calledName = $call->name->toString();
            $calledClass = $call->class instanceof Node\Name ? $call->class->toString() : null;
            $key = 'static:' . ($calledClass ?? '') . '::' . $calledName;

            [$line, $col] = Tree::toLineColumn($source->getDocument(), $call->name->getStartFilePos());
            $callRange = new Range(
                new Position($line, $col),
                new Position($line, $col),
            );

            if (!array_key_exists($key, $outgoing)) {
                if ($calledClass !== null) {
                    $lookupKey = $calledClass . '::' . $calledName;
                    $targetItem = $this->resolveMethodTargetByKey($lookupKey, $calledName, $calledClass, $editor);
                    if ($targetItem === null) {
                        continue;
                    }
                    $outgoing[$key] = [
                        'item' => $targetItem,
                        'ranges' => [],
                    ];
                } else {
                    continue;
                }
            }

            $outgoing[$key]['ranges'][] = $callRange;
        }

        $funcCalls = $finder->findInstanceOf($targetMethod->stmts, Node\Expr\FuncCall::class);
        foreach ($funcCalls as $call) {
            if (!$call->name instanceof Node\Name) {
                continue;
            }

            $calledName = $call->name->toString();
            $key = 'func:' . $calledName;

            [$line, $col] = Tree::toLineColumn($source->getDocument(), $call->getStartFilePos());
            $callRange = new Range(
                new Position($line, $col),
                new Position($line, $col),
            );

            if (!array_key_exists($key, $outgoing)) {
                $targetItem = $this->resolveFunctionTarget($calledName, $editor);
                if ($targetItem === null) {
                    continue;
                }
                $outgoing[$key] = [
                    'item' => $targetItem,
                    'ranges' => [],
                ];
            }

            $outgoing[$key]['ranges'][] = $callRange;
        }

        foreach ($outgoing as $entry) {
            $consumer(new CallHierarchyOutgoingCall(
                to: $entry['item'],
                fromRanges: $entry['ranges'],
            ));
        }
    }

    private function resolveMethodTarget(
        string $methodName,
        Node\Expr\MethodCall $call,
        PHPPsiFile $source,
        EditorInterface $editor,
    ): ?CallHierarchyItem {
        if ($call->var instanceof Node\Expr\Variable && $call->var->name === 'this') {
            $classNode = Tree::parentOfType($call, Node\Stmt\Class_::class);
            if ($classNode !== null) {
                $className = $classNode->namespacedName?->toString() ?? $classNode->name?->toString() ?? '';

                return $this->resolveMethodTargetByKey(
                    $className . '::' . $methodName,
                    $methodName,
                    $className,
                    $editor,
                );
            }
        }

        return null;
    }

    private function resolveMethodTargetByKey(
        string $lookupKey,
        string $methodName,
        string $className,
        EditorInterface $editor,
    ): ?CallHierarchyItem {
        $entry = $this->indexLookup->findEntry(ClassMethodIndexer::class, $lookupKey);
        if ($entry === null) {
            return null;
        }

        /** @var MethodData $data */
        $data = $entry->value;
        $source = $this->fileManager->findPsiFile($editor, new TextDocumentIdentifier($entry->uri));
        if ($source === null) {
            return null;
        }

        [$startLine, $startCol] = Tree::toLineColumn($source->getDocument(), $data->startPosition);
        [$endLine, $endCol] = Tree::toLineColumn($source->getDocument(), $data->endPosition);

        return new CallHierarchyItem(
            name: $lookupKey,
            kind: SymbolKind::MethodKind,
            uri: $entry->uri,
            range: new Range(
                new Position($startLine, $startCol),
                new Position($endLine, $endCol),
            ),
            selectionRange: new Range(
                new Position($startLine, $startCol),
                new Position($startLine, $startCol),
            ),
            detail: $data->returnType,
            data: ['type' => 'method', 'name' => $methodName, 'class' => $className],
        );
    }

    private function resolveFunctionTarget(string $functionName, EditorInterface $editor): ?CallHierarchyItem
    {
        foreach ($this->indexLookup->findByField(
            \App\Module\Indexing\Indexer\FunctionIndexer::class,
            'fqn',
            $functionName,
        ) as $entry) {
            /** @var \App\Module\Indexing\Data\FunctionData $data */
            $data = $entry->value;

            $source = $this->fileManager->findPsiFile($editor, new TextDocumentIdentifier($entry->uri));
            if ($source === null) {
                continue;
            }

            [$startLine, $startCol] = Tree::toLineColumn($source->getDocument(), $data->startPosition);
            [$endLine, $endCol] = Tree::toLineColumn($source->getDocument(), $data->endPosition);

            return new CallHierarchyItem(
                name: $functionName,
                kind: SymbolKind::FunctionKind,
                uri: $entry->uri,
                range: new Range(
                    new Position($startLine, $startCol),
                    new Position($endLine, $endCol),
                ),
                selectionRange: new Range(
                    new Position($startLine, $startCol),
                    new Position($startLine, $startCol),
                ),
                detail: $data->returnType,
                data: ['type' => 'function', 'name' => $functionName],
            );
        }

        return null;
    }

    /**
     * @return array{string, string}|null
     */
    private function resolveMethodInfo(?Node $element): ?array
    {
        if ($element === null) {
            return null;
        }

        if ($element instanceof Node\Identifier) {
            $parent = $element->getAttribute('parent');
            if ($parent instanceof Node\Expr\MethodCall || $parent instanceof Node\Expr\StaticCall) {
                $methodName = $element->toString();

                if ($parent instanceof Node\Expr\StaticCall && $parent->class instanceof Node\Name) {
                    return [$methodName, $parent->class->toString()];
                }

                if (
                    $parent instanceof Node\Expr\MethodCall
                    && $parent->var instanceof Node\Expr\Variable
                    && $parent->var->name === 'this'
                ) {
                    $classNode = Tree::parentOfType($element, Node\Stmt\Class_::class);
                    if ($classNode !== null) {
                        $className = $classNode->namespacedName?->toString() ?? $classNode->name?->toString() ?? '';

                        return [$methodName, $className];
                    }
                }

                return null;
            }
            if ($parent instanceof Node\Stmt\ClassMethod) {
                $classNode =
                    Tree::parentOfType($parent, Node\Stmt\Class_::class) ?? Tree::parentOfType(
                        $parent,
                        Node\Stmt\Interface_::class,
                    ) ?? Tree::parentOfType($parent, Node\Stmt\Trait_::class);
                if ($classNode !== null) {
                    $className = $classNode->namespacedName?->toString() ?? $classNode->name?->toString() ?? '';

                    return [$element->toString(), $className];
                }
            }
        }

        return null;
    }
}
