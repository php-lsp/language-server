<?php

declare(strict_types=1);

namespace App\Tests\Unit\Controller;

use App\Controller\TextDocument\CompletionController;
use App\Core\Contracts\Completion\CompletionConsumer;
use App\Core\Contracts\Completion\CompletionContext;
use App\Core\Contracts\Completion\CompletionContributor;
use App\Module\PsiFile\InMemoryPsiFileManager;
use App\Module\Telemetry\TracerInterface;
use App\Tests\TestCase;
use Lsp\Extension\DocumentManager\Editor\EditorInterface;
use Lsp\Protocol\Type\CompletionItem;
use Lsp\Protocol\Type\CompletionItemKind;
use Lsp\Protocol\Type\CompletionParams;
use Lsp\Protocol\Type\Position;
use Lsp\Protocol\Type\TextDocumentIdentifier;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;
use Psr\Log\NullLogger;

#[Group('unit')]
#[TestDox('Completion Controller')]
final class CompletionControllerTest extends TestCase
{
    private function createContributor(CompletionItem ...$items): CompletionContributor
    {
        return new class ($items) implements CompletionContributor {
            /** @param list<CompletionItem> $items */
            public function __construct(private readonly array $items) {}

            public function contribute(CompletionContext $context, CompletionConsumer $consumer): void
            {
                $consumer(...$this->items);
            }
        };
    }

    private function createTracer(): TracerInterface
    {
        return new class () implements TracerInterface {
            public function trace(string $name, callable $callback, array $attributes = []): mixed
            {
                return $callback();
            }
        };
    }

    private function createController(CompletionContributor ...$contributors): CompletionController
    {
        return new CompletionController(
            new \ArrayIterator($contributors),
            new NullLogger(),
            $this->createMock(InMemoryPsiFileManager::class),
            $this->createTracer(),
        );
    }

    #[TestDox('assigns sortText prefix based on contributor order')]
    public function testSortTextPrefix(): void
    {
        $contributor1 = $this->createContributor(
            new CompletionItem(label: 'alpha'),
        );
        $contributor2 = $this->createContributor(
            new CompletionItem(label: 'beta'),
        );

        $controller = $this->createController($contributor1, $contributor2);

        $editor = $this->createMock(EditorInterface::class);
        $params = new CompletionParams(
            textDocument: new TextDocumentIdentifier(uri: 'file:///test.php'),
            position: new Position(line: 0, character: 0),
        );

        $result = $controller($editor, $params);

        $this->assertCount(2, $result);
        $this->assertSame('000alpha', $result[0]->sortText);
        $this->assertSame('001beta', $result[1]->sortText);
    }

    #[TestDox('preserves existing sortText with prefix')]
    public function testPreservesExistingSortText(): void
    {
        $contributor = $this->createContributor(
            new CompletionItem(label: 'foo', sortText: 'zzz'),
        );

        $controller = $this->createController($contributor);

        $editor = $this->createMock(EditorInterface::class);
        $params = new CompletionParams(
            textDocument: new TextDocumentIdentifier(uri: 'file:///test.php'),
            position: new Position(line: 0, character: 0),
        );

        $result = $controller($editor, $params);

        $this->assertCount(1, $result);
        $this->assertSame('000zzz', $result[0]->sortText);
    }

    #[TestDox('preserves completion item properties')]
    public function testPreservesItemProperties(): void
    {
        $contributor = $this->createContributor(
            new CompletionItem(
                label: 'myMethod',
                kind: CompletionItemKind::MethodKind,
                detail: 'Some detail',
            ),
        );

        $controller = $this->createController($contributor);

        $editor = $this->createMock(EditorInterface::class);
        $params = new CompletionParams(
            textDocument: new TextDocumentIdentifier(uri: 'file:///test.php'),
            position: new Position(line: 0, character: 0),
        );

        $result = $controller($editor, $params);

        $this->assertCount(1, $result);
        $this->assertSame('myMethod', $result[0]->label);
        $this->assertSame(CompletionItemKind::MethodKind, $result[0]->kind);
        $this->assertSame('Some detail', $result[0]->detail);
    }
}
