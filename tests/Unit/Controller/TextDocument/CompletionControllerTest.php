<?php

declare(strict_types=1);

namespace App\Tests\Unit\Controller\TextDocument;

use App\Controller\TextDocument\CompletionController;
use App\Core\Contracts\Completion\CompletionConsumer;
use App\Core\Contracts\Completion\CompletionContext;
use App\Core\Contracts\Completion\CompletionContributor;
use App\Core\Contracts\PsiFile\PsiFileManagerInterface;
use App\Tests\Support\MockHelper;
use App\Tests\Support\ProtocolFactory;
use App\Tests\TestCase;
use Lsp\Extension\DocumentManager\Editor\EditorInterface;
use Lsp\Protocol\Type\CompletionItem;
use Lsp\Protocol\Type\CompletionParams;
use App\Module\Telemetry\NoopTracer;
use Psr\Log\LoggerInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;

#[Group('unit')]
final class CompletionControllerTest extends TestCase
{
    #[TestDox('returns empty with no contributors')]
    public function testReturnsEmptyWithNoContributors(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $fileManager = MockHelper::mock(PsiFileManagerInterface::class);

        $controller = new CompletionController([], $logger, $fileManager, new NoopTracer());
        $editor = MockHelper::mock(EditorInterface::class);
        $params = new CompletionParams(
            textDocument: ProtocolFactory::textDocumentIdentifier(),
            position: ProtocolFactory::position(),
        );

        $result = $controller($editor, $params);

        $this->assertSame([], $result);
    }

    #[TestDox('collects results from contributors')]
    public function testCollectsFromContributors(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $fileManager = MockHelper::mock(PsiFileManagerInterface::class);

        $contributor = new class implements CompletionContributor {
            public function contribute(CompletionContext $context, CompletionConsumer $consumer): void
            {
                ($consumer)(new CompletionItem(label: 'test'));
            }
        };

        $controller = new CompletionController([$contributor], $logger, $fileManager, new NoopTracer());
        $editor = MockHelper::mock(EditorInterface::class);
        $params = new CompletionParams(
            textDocument: ProtocolFactory::textDocumentIdentifier(),
            position: ProtocolFactory::position(),
        );

        $result = $controller($editor, $params);

        $this->assertCount(1, $result);
        $this->assertSame('test', $result[0]->label);
    }

    #[TestDox('handles contributor exceptions')]
    public function testHandlesContributorException(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('error');

        $fileManager = MockHelper::mock(PsiFileManagerInterface::class);

        $contributor = new class implements CompletionContributor {
            public function contribute(CompletionContext $context, CompletionConsumer $consumer): void
            {
                throw new \RuntimeException('fail');
            }
        };

        $controller = new CompletionController([$contributor], $logger, $fileManager, new NoopTracer());
        $editor = MockHelper::mock(EditorInterface::class);
        $params = new CompletionParams(
            textDocument: ProtocolFactory::textDocumentIdentifier(),
            position: ProtocolFactory::position(),
        );

        $result = $controller($editor, $params);

        $this->assertSame([], $result);
    }

    #[TestDox('sortText is prefixed with group index based on contributor order')]
    public function testSortTextPrefixedWithGroupIndex(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $fileManager = MockHelper::mock(PsiFileManagerInterface::class);

        $contributor1 = new class implements CompletionContributor {
            public function contribute(CompletionContext $context, CompletionConsumer $consumer): void
            {
                ($consumer)(new CompletionItem(label: 'alpha'));
                ($consumer)(new CompletionItem(label: 'beta', sortText: 'custom'));
            }
        };

        $contributor2 = new class implements CompletionContributor {
            public function contribute(CompletionContext $context, CompletionConsumer $consumer): void
            {
                ($consumer)(new CompletionItem(label: 'gamma'));
            }
        };

        $controller = new CompletionController(
            [$contributor1, $contributor2],
            $logger,
            $fileManager,
            new NoopTracer(),
        );
        $editor = MockHelper::mock(EditorInterface::class);
        $params = new CompletionParams(
            textDocument: ProtocolFactory::textDocumentIdentifier(),
            position: ProtocolFactory::position(),
        );

        $result = $controller($editor, $params);

        $this->assertCount(3, $result);

        // First contributor items get prefix "000"
        $this->assertSame('000alpha', $result[0]->sortText);
        $this->assertSame('000custom', $result[1]->sortText);

        // Second contributor items get prefix "001"
        $this->assertSame('001gamma', $result[2]->sortText);
    }
}
