<?php

declare(strict_types=1);

namespace App\Tests\Unit\Module\CallHierarchy;

use App\Core\Contracts\CallHierarchy\IncomingCallsConsumer;
use App\Core\Contracts\CallHierarchy\IncomingCallsContext;
use App\Core\Contracts\CallHierarchy\OutgoingCallsConsumer;
use App\Core\Contracts\CallHierarchy\OutgoingCallsContext;
use App\Core\Contracts\CallHierarchy\PrepareCallHierarchyConsumer;
use App\Core\Contracts\CallHierarchy\PrepareCallHierarchyContext;
use App\Module\CallHierarchy\FunctionCallHierarchyContributor;
use App\Module\Document\DocumentIdentifierFactoryInterface;
use App\Module\Indexing\Data\FunctionData;
use App\Tests\Support\IndexTestHelper;
use App\Tests\Support\MockHelper;
use App\Tests\Support\ProtocolFactory;
use App\Tests\Support\PsiFileFactory;
use App\Tests\TestCase;
use Lsp\Protocol\Type\CallHierarchyItem;
use Lsp\Protocol\Type\Position;
use Lsp\Protocol\Type\Range;
use Lsp\Protocol\Type\SymbolKind;
use Lsp\Protocol\Type\TextDocumentIdentifier;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;

#[Group('unit')]
final class FunctionCallHierarchyContributorTest extends TestCase
{
    private function createDocFactory(): \PHPUnit\Framework\MockObject\MockObject
    {
        $docFactory = MockHelper::mock(DocumentIdentifierFactoryInterface::class);
        $docFactory->method('create')->willReturnCallback(
            static fn(string $uri) => new TextDocumentIdentifier($uri),
        );

        return $docFactory;
    }

    #[TestDox('prepare returns empty when file not found')]
    public function testPrepareReturnsEmptyWhenNoFile(): void
    {
        $lookup = IndexTestHelper::createLookup();
        $fileManager = MockHelper::mock(\App\Module\PsiFile\InMemoryPsiFileManager::class);
        $fileManager->method('findPsiFile')->willReturn(null);

        $contributor = new FunctionCallHierarchyContributor($lookup, $fileManager, $this->createDocFactory());

        $editor = MockHelper::mock(\Lsp\Extension\DocumentManager\Editor\EditorInterface::class);
        $context = new PrepareCallHierarchyContext(
            ProtocolFactory::textDocumentIdentifier(),
            ProtocolFactory::position(0, 5),
            $editor,
        );
        $consumer = new PrepareCallHierarchyConsumer();
        $contributor->prepare($context, $consumer);

        $this->assertEmpty($consumer->results);
    }

    #[TestDox('prepare returns empty when cursor is not on a function')]
    public function testPrepareReturnsEmptyWhenNotFunction(): void
    {
        $psiFile = PsiFileFactory::fromCode('<?php $x = 1;');
        $lookup = IndexTestHelper::createLookup();
        $fileManager = MockHelper::mock(\App\Module\PsiFile\InMemoryPsiFileManager::class);
        $fileManager->method('findPsiFile')->willReturn($psiFile);

        $contributor = new FunctionCallHierarchyContributor($lookup, $fileManager, $this->createDocFactory());

        $editor = MockHelper::mock(\Lsp\Extension\DocumentManager\Editor\EditorInterface::class);
        $context = new PrepareCallHierarchyContext(
            ProtocolFactory::textDocumentIdentifier(),
            ProtocolFactory::position(0, 7),
            $editor,
        );
        $consumer = new PrepareCallHierarchyConsumer();
        $contributor->prepare($context, $consumer);

        $this->assertEmpty($consumer->results);
    }

    #[TestDox('prepare returns CallHierarchyItem for function definition')]
    public function testPrepareReturnsFunctionItem(): void
    {
        $code = '<?php function myFunc() {}';
        $psiFile = PsiFileFactory::fromCode($code);

        $funcData = new FunctionData(
            fqn: 'myFunc',
            startPosition: strpos($code, 'function'),
            endPosition: strlen($code) - 1,
            returnType: null,
            parameters: [],
        );

        $lookup = IndexTestHelper::createLookup([
            'php.functions.fqn' => [
                'file:///test.php' => ['myFunc' => $funcData],
            ],
        ]);

        $fileManager = MockHelper::mock(\App\Module\PsiFile\InMemoryPsiFileManager::class);
        $fileManager->method('findPsiFile')->willReturn($psiFile);

        $contributor = new FunctionCallHierarchyContributor($lookup, $fileManager, $this->createDocFactory());

        $editor = MockHelper::mock(\Lsp\Extension\DocumentManager\Editor\EditorInterface::class);
        $context = new PrepareCallHierarchyContext(
            ProtocolFactory::textDocumentIdentifier(),
            ProtocolFactory::position(0, 18),
            $editor,
        );
        $consumer = new PrepareCallHierarchyConsumer();
        $contributor->prepare($context, $consumer);

        $this->assertNotEmpty($consumer->results);
        $this->assertInstanceOf(CallHierarchyItem::class, $consumer->results[0]);
        $this->assertSame('myFunc', $consumer->results[0]->name);
        $this->assertSame(SymbolKind::FunctionKind, $consumer->results[0]->kind);
    }

    #[TestDox('prepare returns non-zero-width selectionRange')]
    public function testPrepareReturnsNonZeroWidthSelectionRange(): void
    {
        $code = '<?php function myFunc() {}';
        $psiFile = PsiFileFactory::fromCode($code);

        $funcData = new FunctionData(
            fqn: 'myFunc',
            startPosition: strpos($code, 'function'),
            endPosition: strlen($code) - 1,
            returnType: null,
            parameters: [],
        );

        $lookup = IndexTestHelper::createLookup([
            'php.functions.fqn' => [
                'file:///test.php' => ['myFunc' => $funcData],
            ],
        ]);

        $fileManager = MockHelper::mock(\App\Module\PsiFile\InMemoryPsiFileManager::class);
        $fileManager->method('findPsiFile')->willReturn($psiFile);

        $contributor = new FunctionCallHierarchyContributor($lookup, $fileManager, $this->createDocFactory());

        $editor = MockHelper::mock(\Lsp\Extension\DocumentManager\Editor\EditorInterface::class);
        $context = new PrepareCallHierarchyContext(
            ProtocolFactory::textDocumentIdentifier(),
            ProtocolFactory::position(0, 18),
            $editor,
        );
        $consumer = new PrepareCallHierarchyConsumer();
        $contributor->prepare($context, $consumer);

        $this->assertNotEmpty($consumer->results);
        $selectionRange = $consumer->results[0]->selectionRange;
        $this->assertNotSame(
            $selectionRange->start->character,
            $selectionRange->end->character,
            'selectionRange should not be zero-width',
        );
    }

    #[TestDox('incoming calls finds callers of a function')]
    public function testIncomingCallsFindsFunctionCallers(): void
    {
        $callerCode = '<?php function caller() { myFunc(); }';
        $callerFile = PsiFileFactory::fromCode($callerCode, 'file:///caller.php');

        $funcPos = strpos($callerCode, 'myFunc()');
        $lookup = IndexTestHelper::createLookup([
            'php.functionCallUsages' => [
                'file:///caller.php' => [['myFunc', $funcPos]],
            ],
        ]);

        $fileManager = MockHelper::mock(\App\Module\PsiFile\InMemoryPsiFileManager::class);
        $fileManager->method('findPsiFile')->willReturn($callerFile);

        $contributor = new FunctionCallHierarchyContributor($lookup, $fileManager, $this->createDocFactory());

        $item = new CallHierarchyItem(
            name: 'myFunc',
            kind: SymbolKind::FunctionKind,
            uri: 'file:///test.php',
            range: new Range(new Position(0, 0), new Position(0, 0)),
            selectionRange: new Range(new Position(0, 0), new Position(0, 0)),
            data: ['type' => 'function', 'name' => 'myFunc'],
        );

        $editor = MockHelper::mock(\Lsp\Extension\DocumentManager\Editor\EditorInterface::class);
        $context = new IncomingCallsContext($item, $editor);
        $consumer = new IncomingCallsConsumer();
        $contributor->incomingCalls($context, $consumer);

        $this->assertNotEmpty($consumer->results);
        $this->assertSame('caller', $consumer->results[0]->from->name);
    }

    #[TestDox('incoming calls returns non-zero-width fromRanges')]
    public function testIncomingCallsReturnsNonZeroWidthFromRanges(): void
    {
        $callerCode = '<?php function caller() { myFunc(); }';
        $callerFile = PsiFileFactory::fromCode($callerCode, 'file:///caller.php');

        $funcPos = strpos($callerCode, 'myFunc()');
        $lookup = IndexTestHelper::createLookup([
            'php.functionCallUsages' => [
                'file:///caller.php' => [['myFunc', $funcPos]],
            ],
        ]);

        $fileManager = MockHelper::mock(\App\Module\PsiFile\InMemoryPsiFileManager::class);
        $fileManager->method('findPsiFile')->willReturn($callerFile);

        $contributor = new FunctionCallHierarchyContributor($lookup, $fileManager, $this->createDocFactory());

        $item = new CallHierarchyItem(
            name: 'myFunc',
            kind: SymbolKind::FunctionKind,
            uri: 'file:///test.php',
            range: new Range(new Position(0, 0), new Position(0, 0)),
            selectionRange: new Range(new Position(0, 0), new Position(0, 0)),
            data: ['type' => 'function', 'name' => 'myFunc'],
        );

        $editor = MockHelper::mock(\Lsp\Extension\DocumentManager\Editor\EditorInterface::class);
        $context = new IncomingCallsContext($item, $editor);
        $consumer = new IncomingCallsConsumer();
        $contributor->incomingCalls($context, $consumer);

        $this->assertNotEmpty($consumer->results);
        $fromRanges = $consumer->results[0]->fromRanges;
        $this->assertNotEmpty($fromRanges);
        $this->assertNotSame(
            $fromRanges[0]->start->character,
            $fromRanges[0]->end->character,
            'fromRanges should not be zero-width',
        );
    }

    #[TestDox('incoming calls returns empty for non-function type')]
    public function testIncomingCallsReturnsEmptyForNonFunctionType(): void
    {
        $lookup = IndexTestHelper::createLookup();
        $fileManager = MockHelper::mock(\App\Module\PsiFile\InMemoryPsiFileManager::class);

        $contributor = new FunctionCallHierarchyContributor($lookup, $fileManager, $this->createDocFactory());

        $item = new CallHierarchyItem(
            name: 'test',
            kind: SymbolKind::MethodKind,
            uri: 'file:///test.php',
            range: new Range(new Position(0, 0), new Position(0, 0)),
            selectionRange: new Range(new Position(0, 0), new Position(0, 0)),
            data: ['type' => 'method', 'name' => 'test'],
        );

        $editor = MockHelper::mock(\Lsp\Extension\DocumentManager\Editor\EditorInterface::class);
        $context = new IncomingCallsContext($item, $editor);
        $consumer = new IncomingCallsConsumer();
        $contributor->incomingCalls($context, $consumer);

        $this->assertEmpty($consumer->results);
    }

    #[TestDox('outgoing calls returns empty for non-function type')]
    public function testOutgoingCallsReturnsEmptyForNonFunctionType(): void
    {
        $lookup = IndexTestHelper::createLookup();
        $fileManager = MockHelper::mock(\App\Module\PsiFile\InMemoryPsiFileManager::class);

        $contributor = new FunctionCallHierarchyContributor($lookup, $fileManager, $this->createDocFactory());

        $item = new CallHierarchyItem(
            name: 'test',
            kind: SymbolKind::MethodKind,
            uri: 'file:///test.php',
            range: new Range(new Position(0, 0), new Position(0, 0)),
            selectionRange: new Range(new Position(0, 0), new Position(0, 0)),
            data: ['type' => 'method', 'name' => 'test'],
        );

        $editor = MockHelper::mock(\Lsp\Extension\DocumentManager\Editor\EditorInterface::class);
        $context = new OutgoingCallsContext($item, $editor);
        $consumer = new OutgoingCallsConsumer();
        $contributor->outgoingCalls($context, $consumer);

        $this->assertEmpty($consumer->results);
    }

    #[TestDox('outgoing calls finds functions called from a function')]
    public function testOutgoingCallsFindsFunctionCalls(): void
    {
        $code = '<?php function myFunc() { otherFunc(); }';
        $psiFile = PsiFileFactory::fromCode($code, 'file:///test.php');

        $funcData = new FunctionData(
            fqn: 'myFunc',
            startPosition: strpos($code, 'function'),
            endPosition: strlen($code) - 1,
            returnType: null,
            parameters: [],
        );
        $otherFuncData = new FunctionData(
            fqn: 'otherFunc',
            startPosition: 0,
            endPosition: 20,
            returnType: null,
            parameters: [],
        );

        $lookup = IndexTestHelper::createLookup([
            'php.functions.fqn' => [
                'file:///test.php' => ['myFunc' => $funcData, 'otherFunc' => $otherFuncData],
            ],
        ]);

        $fileManager = MockHelper::mock(\App\Module\PsiFile\InMemoryPsiFileManager::class);
        $fileManager->method('findPsiFile')->willReturn($psiFile);

        $contributor = new FunctionCallHierarchyContributor($lookup, $fileManager, $this->createDocFactory());

        $item = new CallHierarchyItem(
            name: 'myFunc',
            kind: SymbolKind::FunctionKind,
            uri: 'file:///test.php',
            range: new Range(new Position(0, 0), new Position(0, 0)),
            selectionRange: new Range(new Position(0, 0), new Position(0, 0)),
            data: ['type' => 'function', 'name' => 'myFunc'],
        );

        $editor = MockHelper::mock(\Lsp\Extension\DocumentManager\Editor\EditorInterface::class);
        $context = new OutgoingCallsContext($item, $editor);
        $consumer = new OutgoingCallsConsumer();
        $contributor->outgoingCalls($context, $consumer);

        $this->assertNotEmpty($consumer->results);
        $this->assertSame('otherFunc', $consumer->results[0]->to->name);
    }
}
