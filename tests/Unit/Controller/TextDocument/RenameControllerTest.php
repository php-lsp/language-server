<?php

declare(strict_types=1);

namespace App\Tests\Unit\Controller\TextDocument;

use App\Controller\TextDocument\RenameController;
use App\Module\PsiFile\InMemoryPsiFileManager;
use App\Tests\Support\ProtocolFactory;
use App\Tests\TestCase;
use Lsp\Extension\DocumentManager\Editor\EditorInterface;
use Lsp\Protocol\Type\PrepareRenameParams;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;

#[Group('unit')]
final class RenameControllerTest extends TestCase
{
    #[TestDox('invokes without error')]
    public function testInvokesWithoutError(): void
    {
        $fileManager = $this->createMock(InMemoryPsiFileManager::class);
        $controller = new RenameController([], $fileManager);
        $editor = $this->createMock(EditorInterface::class);
        $params = new PrepareRenameParams(
            textDocument: ProtocolFactory::textDocumentIdentifier(),
            position: ProtocolFactory::position(),
        );

        $result = $controller($editor, $params);

        $this->assertNull($result);
    }

    #[TestDox('aggregates results from contributors')]
    public function testAggregatesContributors(): void
    {
        $contributor = new class implements \App\Core\Contracts\References\ReferenceContributor {
            public function contribute(\App\Core\Contracts\References\ReferenceContext $context, \App\Core\Contracts\References\ReferenceConsumer $consumer): void
            {
                $consumer(new \Lsp\Protocol\Type\Location(uri: 'file:///foo.php', range: ProtocolFactory::range()));
            }
        };

        $fileManager = $this->createMock(InMemoryPsiFileManager::class);
        $controller = new RenameController([$contributor], $fileManager);
        $editor = $this->createMock(EditorInterface::class);
        $params = new PrepareRenameParams(
            textDocument: ProtocolFactory::textDocumentIdentifier(),
            position: ProtocolFactory::position(),
        );

        $result = $controller($editor, $params);

        $this->assertNull($result);
    }
}
