<?php

declare(strict_types=1);

namespace App\Tests\Unit\Module\Documentation;

use App\Core\Contracts\Documentation\DocumentationConsumer;
use App\Core\Contracts\Documentation\DocumentationContext;
use App\Module\Documentation\NodesTraceDocumentationContributor;
use App\Tests\Support\MockHelper;
use App\Tests\Support\ProtocolFactory;
use App\Tests\Support\PsiFileFactory;
use App\Tests\TestCase;
use Lsp\Extension\DocumentManager\Editor\EditorInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;

#[Group('unit')]
final class NodesTraceDocumentationContributorTest extends TestCase
{
    #[TestDox('contributes node trace when node is found')]
    public function testContributesNodeTrace(): void
    {
        $psiFile = PsiFileFactory::fromCode('<?php class Foo {}');

        $fileManager = MockHelper::mock(\App\Module\PsiFile\InMemoryPsiFileManager::class);
        $fileManager->method('findPsiFile')->willReturn($psiFile);

        $editor = MockHelper::mock(EditorInterface::class);
        $context = new DocumentationContext(
            ProtocolFactory::textDocumentIdentifier(),
            ProtocolFactory::position(0, 14),
            $editor,
        );
        $consumer = new DocumentationConsumer();

        $contributor = new NodesTraceDocumentationContributor($fileManager);
        $contributor->contribute($context, $consumer);

        $this->assertNotEmpty($consumer->results);
        $this->assertStringContainsString('Node classes', $consumer->results[0]);
    }

    #[TestDox('does nothing when file not found')]
    public function testDoesNothingWhenNoFile(): void
    {
        $fileManager = MockHelper::mock(\App\Module\PsiFile\InMemoryPsiFileManager::class);
        $fileManager->method('findPsiFile')->willReturn(null);

        $editor = MockHelper::mock(EditorInterface::class);
        $context = new DocumentationContext(
            ProtocolFactory::textDocumentIdentifier(),
            ProtocolFactory::position(),
            $editor,
        );
        $consumer = new DocumentationConsumer();

        $contributor = new NodesTraceDocumentationContributor($fileManager);
        $contributor->contribute($context, $consumer);

        $this->assertEmpty($consumer->results);
    }
}
