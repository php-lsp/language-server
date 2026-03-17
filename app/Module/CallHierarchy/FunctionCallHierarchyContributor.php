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
use App\Module\Indexing\Indexer\FunctionCallUsageIndexer;
use App\Module\Indexing\Indexer\FunctionIndexer;
use App\Module\Indexing\IndexLookup;
use App\Module\PsiFile\InMemoryPsiFileManager;
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

            $consumer(new CallHierarchyItem(
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

            $this->collectOutgoingFunctionCalls($source, $funcData, $entry->uri, $context->editor, $consumer);
        }
    }

    /**
     * @return array{name: string, kind: SymbolKind, range: Range, selectionRange: Range, data: array<string, string>}|null
     */
    private function findEnclosingCallable(mixed $file, int $pos): ?array
    {
        if (!$file instanceof \App\Module\PsiFile\PHPPsiFile) {
            return null;
        }

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
                $methodName = $node->name->toString();
                $range = Tree::getRange($node, $file);
                $selectionRange = Tree::getRange($node->name, $file);

                return [
                    'name' => $className . '::' . $methodName,
                    'kind' => SymbolKind::MethodKind,
                    'range' => $range,
                    'selectionRange' => $selectionRange,
                    'data' => ['type' => 'method', 'name' => $methodName, 'class' => $className],
                ];
            }
        }

        return null;
    }

    private function collectOutgoingFunctionCalls(
        \App\Module\PsiFile\PHPPsiFile $source,
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

        $calls = $finder->findInstanceOf($targetFunc->stmts, Node\Expr\FuncCall::class);

        /** @var array<string, array{item: CallHierarchyItem, ranges: list<Range>}> $outgoing */
        $outgoing = [];

        foreach ($calls as $call) {
            if (!$call->name instanceof Node\Name) {
                continue;
            }

            $calledName = $call->name->toString();

            [$line, $col] = Tree::toLineColumn($source->getDocument(), $call->getStartFilePos());
            $callRange = new Range(
                new Position($line, $col),
                new Position($line, $col),
            );

            if (!array_key_exists($calledName, $outgoing)) {
                $targetItem = $this->resolveTargetItem($calledName, $editor);
                if ($targetItem === null) {
                    continue;
                }
                $outgoing[$calledName] = [
                    'item' => $targetItem,
                    'ranges' => [],
                ];
            }

            $outgoing[$calledName]['ranges'][] = $callRange;
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
