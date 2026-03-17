<?php

declare(strict_types=1);

namespace App\Tests\Unit\Module\CallHierarchy;

use App\Core\Contracts\CallHierarchy\IncomingCallsConsumer;
use App\Core\Contracts\CallHierarchy\IncomingCallsContext;
use App\Core\Contracts\CallHierarchy\OutgoingCallsConsumer;
use App\Core\Contracts\CallHierarchy\OutgoingCallsContext;
use App\Core\Contracts\CallHierarchy\PrepareCallHierarchyConsumer;
use App\Core\Contracts\CallHierarchy\PrepareCallHierarchyContext;
use App\Module\CallHierarchy\MethodCallHierarchyContributor;
use App\Module\Document\DocumentIdentifierFactoryInterface;
use App\Module\Indexing\Data\MethodData;
use App\Module\Indexing\Data\Visibility;
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
final class MethodCallHierarchyContributorTest extends TestCase
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

        $contributor = new MethodCallHierarchyContributor($lookup, $fileManager, $this->createDocFactory());

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

    #[TestDox('prepare returns empty when cursor is not on a method')]
    public function testPrepareReturnsEmptyWhenNotMethod(): void
    {
        $psiFile = PsiFileFactory::fromCode('<?php $x = 1;');
        $lookup = IndexTestHelper::createLookup();
        $fileManager = MockHelper::mock(\App\Module\PsiFile\InMemoryPsiFileManager::class);
        $fileManager->method('findPsiFile')->willReturn($psiFile);

        $contributor = new MethodCallHierarchyContributor($lookup, $fileManager, $this->createDocFactory());

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

    #[TestDox('prepare returns CallHierarchyItem for method definition')]
    public function testPrepareReturnsMethodItem(): void
    {
        $code = '<?php class Foo { public function bar() {} }';
        $psiFile = PsiFileFactory::fromCode($code);

        $methodData = new MethodData(
            name: 'bar',
            className: 'Foo',
            startPosition: strpos($code, 'public'),
            endPosition: strpos($code, '{}') + 1,
            visibility: Visibility::Public,
            isStatic: false,
            isAbstract: false,
            returnType: null,
            parameters: [],
        );

        $lookup = IndexTestHelper::createLookup([
            'php.classMethods.fqn' => [
                'file:///test.php' => ['Foo::bar' => $methodData],
            ],
        ]);

        $fileManager = MockHelper::mock(\App\Module\PsiFile\InMemoryPsiFileManager::class);
        $fileManager->method('findPsiFile')->willReturn($psiFile);

        $contributor = new MethodCallHierarchyContributor($lookup, $fileManager, $this->createDocFactory());

        $editor = MockHelper::mock(\Lsp\Extension\DocumentManager\Editor\EditorInterface::class);
        $context = new PrepareCallHierarchyContext(
            ProtocolFactory::textDocumentIdentifier(),
            ProtocolFactory::position(0, 35),
            $editor,
        );
        $consumer = new PrepareCallHierarchyConsumer();
        $contributor->prepare($context, $consumer);

        $this->assertNotEmpty($consumer->results);
        $this->assertInstanceOf(CallHierarchyItem::class, $consumer->results[0]);
        $this->assertSame('Foo::bar', $consumer->results[0]->name);
        $this->assertSame(SymbolKind::MethodKind, $consumer->results[0]->kind);
    }

    #[TestDox('prepare returns non-zero-width selectionRange')]
    public function testPrepareReturnsNonZeroWidthSelectionRange(): void
    {
        $code = '<?php class Foo { public function bar() {} }';
        $psiFile = PsiFileFactory::fromCode($code);

        $methodData = new MethodData(
            name: 'bar',
            className: 'Foo',
            startPosition: strpos($code, 'public'),
            endPosition: strpos($code, '{}') + 1,
            visibility: Visibility::Public,
            isStatic: false,
            isAbstract: false,
            returnType: null,
            parameters: [],
        );

        $lookup = IndexTestHelper::createLookup([
            'php.classMethods.fqn' => [
                'file:///test.php' => ['Foo::bar' => $methodData],
            ],
        ]);

        $fileManager = MockHelper::mock(\App\Module\PsiFile\InMemoryPsiFileManager::class);
        $fileManager->method('findPsiFile')->willReturn($psiFile);

        $contributor = new MethodCallHierarchyContributor($lookup, $fileManager, $this->createDocFactory());

        $editor = MockHelper::mock(\Lsp\Extension\DocumentManager\Editor\EditorInterface::class);
        $context = new PrepareCallHierarchyContext(
            ProtocolFactory::textDocumentIdentifier(),
            ProtocolFactory::position(0, 35),
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

    #[TestDox('incoming calls returns empty for non-method type')]
    public function testIncomingCallsReturnsEmptyForNonMethodType(): void
    {
        $lookup = IndexTestHelper::createLookup();
        $fileManager = MockHelper::mock(\App\Module\PsiFile\InMemoryPsiFileManager::class);

        $contributor = new MethodCallHierarchyContributor($lookup, $fileManager, $this->createDocFactory());

        $item = new CallHierarchyItem(
            name: 'test',
            kind: SymbolKind::FunctionKind,
            uri: 'file:///test.php',
            range: new Range(new Position(0, 0), new Position(0, 0)),
            selectionRange: new Range(new Position(0, 0), new Position(0, 0)),
            data: ['type' => 'function', 'name' => 'test'],
        );

        $editor = MockHelper::mock(\Lsp\Extension\DocumentManager\Editor\EditorInterface::class);
        $context = new IncomingCallsContext($item, $editor);
        $consumer = new IncomingCallsConsumer();
        $contributor->incomingCalls($context, $consumer);

        $this->assertEmpty($consumer->results);
    }

    #[TestDox('incoming calls finds callers of a method')]
    public function testIncomingCallsFindMethodCallers(): void
    {
        $callerCode = '<?php class Foo { public function caller() { $this->bar(); } public function bar() {} }';
        $callerFile = PsiFileFactory::fromCode($callerCode, 'file:///caller.php');

        $barPos = strpos($callerCode, 'bar()');
        $lookup = IndexTestHelper::createLookup([
            'php.methodCallUsages' => [
                'file:///caller.php' => [['bar', $barPos, null]],
            ],
        ]);

        $fileManager = MockHelper::mock(\App\Module\PsiFile\InMemoryPsiFileManager::class);
        $fileManager->method('findPsiFile')->willReturn($callerFile);

        $contributor = new MethodCallHierarchyContributor($lookup, $fileManager, $this->createDocFactory());

        $item = new CallHierarchyItem(
            name: 'Foo::bar',
            kind: SymbolKind::MethodKind,
            uri: 'file:///test.php',
            range: new Range(new Position(0, 0), new Position(0, 0)),
            selectionRange: new Range(new Position(0, 0), new Position(0, 0)),
            data: ['type' => 'method', 'name' => 'bar', 'class' => 'Foo'],
        );

        $editor = MockHelper::mock(\Lsp\Extension\DocumentManager\Editor\EditorInterface::class);
        $context = new IncomingCallsContext($item, $editor);
        $consumer = new IncomingCallsConsumer();
        $contributor->incomingCalls($context, $consumer);

        $this->assertNotEmpty($consumer->results);
    }

    #[TestDox('incoming calls filters out calls to different class when class is known')]
    public function testIncomingCallsFiltersClassNameWhenKnown(): void
    {
        $callerCode = '<?php class Foo { public function caller() { Bar::baz(); } }';
        $callerFile = PsiFileFactory::fromCode($callerCode, 'file:///caller.php');

        $bazPos = strpos($callerCode, 'baz()');
        $lookup = IndexTestHelper::createLookup([
            'php.methodCallUsages' => [
                'file:///caller.php' => [['baz', $bazPos, 'Bar']],
            ],
        ]);

        $fileManager = MockHelper::mock(\App\Module\PsiFile\InMemoryPsiFileManager::class);
        $fileManager->method('findPsiFile')->willReturn($callerFile);

        $contributor = new MethodCallHierarchyContributor($lookup, $fileManager, $this->createDocFactory());

        $item = new CallHierarchyItem(
            name: 'Qux::baz',
            kind: SymbolKind::MethodKind,
            uri: 'file:///test.php',
            range: new Range(new Position(0, 0), new Position(0, 0)),
            selectionRange: new Range(new Position(0, 0), new Position(0, 0)),
            data: ['type' => 'method', 'name' => 'baz', 'class' => 'Qux'],
        );

        $editor = MockHelper::mock(\Lsp\Extension\DocumentManager\Editor\EditorInterface::class);
        $context = new IncomingCallsContext($item, $editor);
        $consumer = new IncomingCallsConsumer();
        $contributor->incomingCalls($context, $consumer);

        $this->assertEmpty($consumer->results, 'Should not return callers when class name does not match');
    }

    #[TestDox('incoming calls returns non-zero-width fromRanges')]
    public function testIncomingCallsReturnsNonZeroWidthFromRanges(): void
    {
        $callerCode = '<?php class Foo { public function caller() { $this->bar(); } public function bar() {} }';
        $callerFile = PsiFileFactory::fromCode($callerCode, 'file:///caller.php');

        $barPos = strpos($callerCode, 'bar()');
        $lookup = IndexTestHelper::createLookup([
            'php.methodCallUsages' => [
                'file:///caller.php' => [['bar', $barPos, null]],
            ],
        ]);

        $fileManager = MockHelper::mock(\App\Module\PsiFile\InMemoryPsiFileManager::class);
        $fileManager->method('findPsiFile')->willReturn($callerFile);

        $contributor = new MethodCallHierarchyContributor($lookup, $fileManager, $this->createDocFactory());

        $item = new CallHierarchyItem(
            name: 'Foo::bar',
            kind: SymbolKind::MethodKind,
            uri: 'file:///test.php',
            range: new Range(new Position(0, 0), new Position(0, 0)),
            selectionRange: new Range(new Position(0, 0), new Position(0, 0)),
            data: ['type' => 'method', 'name' => 'bar', 'class' => 'Foo'],
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

    #[TestDox('outgoing calls returns empty for non-method type')]
    public function testOutgoingCallsReturnsEmptyForNonMethodType(): void
    {
        $lookup = IndexTestHelper::createLookup();
        $fileManager = MockHelper::mock(\App\Module\PsiFile\InMemoryPsiFileManager::class);

        $contributor = new MethodCallHierarchyContributor($lookup, $fileManager, $this->createDocFactory());

        $item = new CallHierarchyItem(
            name: 'test',
            kind: SymbolKind::FunctionKind,
            uri: 'file:///test.php',
            range: new Range(new Position(0, 0), new Position(0, 0)),
            selectionRange: new Range(new Position(0, 0), new Position(0, 0)),
            data: ['type' => 'function', 'name' => 'test'],
        );

        $editor = MockHelper::mock(\Lsp\Extension\DocumentManager\Editor\EditorInterface::class);
        $context = new OutgoingCallsContext($item, $editor);
        $consumer = new OutgoingCallsConsumer();
        $contributor->outgoingCalls($context, $consumer);

        $this->assertEmpty($consumer->results);
    }
}
