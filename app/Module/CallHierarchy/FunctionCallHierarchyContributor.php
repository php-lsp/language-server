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
use App\Module\Document\DocumentIdentifierFactoryInterface;
use App\Module\Indexing\Data\FunctionData;
use App\Module\Indexing\Data\MethodData;
use App\Module\Indexing\Indexer\ClassMethodIndexer;
use App\Module\Indexing\Indexer\FunctionCallUsageIndexer;
use App\Module\Indexing\Indexer\FunctionIndexer;
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
use Override;
use PhpParser\Node;
use PhpParser\NodeFinder;

#[AsCallHierarchyContributor]
final class FunctionCallHierarchyContributor implements CallHierarchyContributor
{
    public function __construct(
        private readonly IndexLookup $indexLookup,
        private readonly InMemoryPsiFileManager $fileManager,
        private readonly DocumentIdentifierFactoryInterface $documentIdentifierFactory,
    ) {}

    #[Override]
    public function prepare(PrepareCallHierarchyContext $context, PrepareCallHierarchyConsumer $consumer): void
    {
        $file = $this->fileManager->findPsiFile($context->editor, $context->textDocumentIdentifier);
        if ($file === null) {
            return;
        }

        $element = $file->findLastAtPosition($context->position);
        $functionName = $this->resolveFunctionName($element);
        if ($functionName === null) {
            return;
        }

        foreach ($this->indexLookup->findByField(FunctionIndexer::class, 'fqn', $functionName) as $entry) {
            /** @var FunctionData $data */
            $data = $entry->value;

            $textDocumentIdentifier = $this->documentIdentifierFactory->create($entry->uri);
            $source = $this->fileManager->findPsiFile($context->editor, $textDocumentIdentifier);
            if ($source === null) {
                continue;
            }

            [$startLine, $startCol] = Tree::toLineColumn($source->getDocument(), $data->startPosition);
            [$endLine, $endCol] = Tree::toLineColumn($source->getDocument(), $data->endPosition);

            $selectionRange = CallHierarchyHelper::findFunctionNameRange($source, $data) ?? new Range(
                new Position($startLine, $startCol),
                new Position($startLine, $startCol),
            );

            $consumer(new CallHierarchyItem(
                name: $functionName,
                kind: SymbolKind::FunctionKind,
                uri: $entry->uri,
                range: new Range(
                    new Position($startLine, $startCol),
                    new Position($endLine, $endCol),
                ),
                selectionRange: $selectionRange,
                detail: $data->returnType,
                data: ['type' => 'function', 'name' => $functionName],
            ));
        }
    }

    #[Override]
    public function incomingCalls(IncomingCallsContext $context, IncomingCallsConsumer $consumer): void
    {
        $data = $context->item->data;
        if (!is_array($data) || ($data['type'] ?? null) !== 'function') {
            return;
        }

        $functionName = $data['name'] ?? null;
        if (!is_string($functionName)) {
            return;
        }

        /** @var array<string, array{item: CallHierarchyItem, ranges: list<Range>}> $callers */
        $callers = [];

        foreach ($this->indexLookup->findByKey(FunctionCallUsageIndexer::class) as $entry) {
            /** @var array{string, int} $value */
            $value = $entry->value;
            if ($value[0] !== $functionName) {
                continue;
            }

            /** @var non-empty-string $uri */
            $uri = $entry->uri;
            $source = $this->fileManager->findPsiFile(
                $context->editor,
                $this->documentIdentifierFactory->create($uri),
            );
            if ($source === null) {
                continue;
            }

            $callRange = CallHierarchyHelper::makeNameRange(
                $source->getDocument(),
                $value[1],
                strlen($functionName),
            );

            $callerInfo = CallHierarchyHelper::findEnclosingCallable($source, $value[1]);
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
        if (!is_array($data) || ($data['type'] ?? null) !== 'function') {
            return;
        }

        $functionName = $data['name'] ?? null;
        if (!is_string($functionName)) {
            return;
        }

        foreach ($this->indexLookup->findByField(FunctionIndexer::class, 'fqn', $functionName) as $entry) {
            /** @var FunctionData $funcData */
            $funcData = $entry->value;

            $textDocumentIdentifier = $this->documentIdentifierFactory->create($entry->uri);
            $source = $this->fileManager->findPsiFile($context->editor, $textDocumentIdentifier);
            if ($source === null) {
                continue;
            }

            $this->collectOutgoingCalls($source, $funcData, $entry->uri, $context->editor, $consumer);
        }
    }

    private function collectOutgoingCalls(
        PHPPsiFile $source,
        FunctionData $funcData,
        string $uri,
        EditorInterface $editor,
        OutgoingCallsConsumer $consumer,
    ): void {
        $finder = new NodeFinder();
        $funcNodes = $finder->findInstanceOf($source->ast->children, Node\Stmt\Function_::class);

        $targetFunc = null;
        foreach ($funcNodes as $fn) {
            $fqn = $fn->namespacedName?->toString() ?? $fn->name->toString();
            if ($fqn === $funcData->fqn) {
                $targetFunc = $fn;
                break;
            }
        }

        if ($targetFunc === null || $targetFunc->stmts === null) {
            return;
        }

        /** @var array<string, array{item: CallHierarchyItem, ranges: list<Range>}> $outgoing */
        $outgoing = [];

        $funcCalls = $finder->findInstanceOf($targetFunc->stmts, Node\Expr\FuncCall::class);
        foreach ($funcCalls as $call) {
            if (!$call->name instanceof Node\Name) {
                continue;
            }

            $calledName = $call->name->toString();
            $key = 'func:' . $calledName;

            $callRange = CallHierarchyHelper::makeNameRange(
                $source->getDocument(),
                $call->getStartFilePos(),
                strlen($calledName),
            );

            if (!array_key_exists($key, $outgoing)) {
                $targetItem = $this->resolveTargetItem($calledName, $editor);
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

        $methodCalls = $finder->findInstanceOf($targetFunc->stmts, Node\Expr\MethodCall::class);
        foreach ($methodCalls as $call) {
            if (!$call->name instanceof Node\Identifier) {
                continue;
            }

            $calledName = $call->name->toString();
            $key = 'method:' . $calledName;

            $callRange = CallHierarchyHelper::makeNameRange(
                $source->getDocument(),
                $call->name->getStartFilePos(),
                strlen($calledName),
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

        $staticCalls = $finder->findInstanceOf($targetFunc->stmts, Node\Expr\StaticCall::class);
        foreach ($staticCalls as $call) {
            if (!$call->name instanceof Node\Identifier) {
                continue;
            }

            $calledName = $call->name->toString();
            $calledClass = $call->class instanceof Node\Name ? $call->class->toString() : null;
            $key = 'static:' . ($calledClass ?? '') . '::' . $calledName;

            $callRange = CallHierarchyHelper::makeNameRange(
                $source->getDocument(),
                $call->name->getStartFilePos(),
                strlen($calledName),
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

        foreach ($outgoing as $entry) {
            $consumer(new CallHierarchyOutgoingCall(
                to: $entry['item'],
                fromRanges: $entry['ranges'],
            ));
        }
    }

    private function resolveTargetItem(string $functionName, EditorInterface $editor): ?CallHierarchyItem
    {
        foreach ($this->indexLookup->findByField(FunctionIndexer::class, 'fqn', $functionName) as $entry) {
            /** @var FunctionData $data */
            $data = $entry->value;

            $textDocumentIdentifier = $this->documentIdentifierFactory->create($entry->uri);
            $source = $this->fileManager->findPsiFile($editor, $textDocumentIdentifier);
            if ($source === null) {
                continue;
            }

            [$startLine, $startCol] = Tree::toLineColumn($source->getDocument(), $data->startPosition);
            [$endLine, $endCol] = Tree::toLineColumn($source->getDocument(), $data->endPosition);

            $selectionRange = CallHierarchyHelper::findFunctionNameRange($source, $data) ?? new Range(
                new Position($startLine, $startCol),
                new Position($startLine, $startCol),
            );

            return new CallHierarchyItem(
                name: $functionName,
                kind: SymbolKind::FunctionKind,
                uri: $entry->uri,
                range: new Range(
                    new Position($startLine, $startCol),
                    new Position($endLine, $endCol),
                ),
                selectionRange: $selectionRange,
                detail: $data->returnType,
                data: ['type' => 'function', 'name' => $functionName],
            );
        }

        return null;
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
        $source = $this->fileManager->findPsiFile($editor, $this->documentIdentifierFactory->create($entry->uri));
        if ($source === null) {
            return null;
        }

        [$startLine, $startCol] = Tree::toLineColumn($source->getDocument(), $data->startPosition);
        [$endLine, $endCol] = Tree::toLineColumn($source->getDocument(), $data->endPosition);

        $selectionRange = CallHierarchyHelper::findMethodNameRange($source, $data) ?? new Range(
            new Position($startLine, $startCol),
            new Position($startLine, $startCol),
        );

        return new CallHierarchyItem(
            name: $lookupKey,
            kind: SymbolKind::MethodKind,
            uri: $entry->uri,
            range: new Range(
                new Position($startLine, $startCol),
                new Position($endLine, $endCol),
            ),
            selectionRange: $selectionRange,
            detail: $data->returnType,
            data: ['type' => 'method', 'name' => $methodName, 'class' => $className],
        );
    }

    private function resolveFunctionName(?Node $element): ?string
    {
        if ($element === null) {
            return null;
        }

        if ($element instanceof Node\Name) {
            $parent = $element->getAttribute('parent');
            if ($parent instanceof Node\Expr\FuncCall) {
                return $element->toString();
            }
        }

        if ($element instanceof Node\Identifier) {
            $parent = $element->getAttribute('parent');
            if ($parent instanceof Node\Stmt\Function_) {
                return $parent->namespacedName?->toString() ?? $element->toString();
            }
        }

        return null;
    }
}
