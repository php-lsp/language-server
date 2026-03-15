<?php

declare(strict_types=1);

namespace App\Tests\Unit\Module\Definition;

use App\Core\Contracts\Definition\DefinitionConsumer;
use App\Core\Contracts\Definition\DefinitionContext;
use App\Module\Definition\VariableDefinitionContributor;
use App\Tests\Support\MockHelper;
use App\Tests\Support\ProtocolFactory;
use App\Tests\Support\PsiFileFactory;
use App\Tests\TestCase;
use Lsp\Protocol\Type\Location;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;

#[Group('unit')]
final class VariableDefinitionContributorTest extends TestCase
{
    #[TestDox('returns empty when file not found')]
    public function testReturnsEmptyWhenNoFile(): void
    {
        $fileManager = MockHelper::mock(\App\Module\PsiFile\InMemoryPsiFileManager::class);
        $fileManager->method('findPsiFile')->willReturn(null);

        $contributor = new VariableDefinitionContributor($fileManager);

        $editor = MockHelper::mock(\Lsp\Extension\DocumentManager\Editor\EditorInterface::class);
        $context = new DefinitionContext(
            ProtocolFactory::textDocumentIdentifier(),
            ProtocolFactory::position(0, 10),
            $editor,
        );
        $consumer = new DefinitionConsumer();
        $contributor->contribute($context, $consumer);

        $this->assertEmpty($consumer->results);
    }

    #[TestDox('finds variable definition from parameter')]
    public function testFindsVariableFromParameter(): void
    {
        $code = '<?php function foo($name) { echo $name; }';
        $psiFile = PsiFileFactory::fromCode($code);
        $fileManager = MockHelper::mock(\App\Module\PsiFile\InMemoryPsiFileManager::class);
        $fileManager->method('findPsiFile')->willReturn($psiFile);

        $contributor = new VariableDefinitionContributor($fileManager);

        $editor = MockHelper::mock(\Lsp\Extension\DocumentManager\Editor\EditorInterface::class);
        $context = new DefinitionContext(
            ProtocolFactory::textDocumentIdentifier(),
            ProtocolFactory::position(0, 34),
            $editor,
        );
        $consumer = new DefinitionConsumer();
        $contributor->contribute($context, $consumer);

        $this->assertCount(1, $consumer->results);
        $this->assertInstanceOf(Location::class, $consumer->results[0]);
        $this->assertSame('file:///test.php', $consumer->results[0]->uri);
    }

    #[TestDox('finds variable definition from first assignment')]
    public function testFindsVariableFromAssignment(): void
    {
        $code = '<?php function foo() { $x = 1; echo $x; }';
        $psiFile = PsiFileFactory::fromCode($code);
        $fileManager = MockHelper::mock(\App\Module\PsiFile\InMemoryPsiFileManager::class);
        $fileManager->method('findPsiFile')->willReturn($psiFile);

        $contributor = new VariableDefinitionContributor($fileManager);

        $editor = MockHelper::mock(\Lsp\Extension\DocumentManager\Editor\EditorInterface::class);
        $context = new DefinitionContext(
            ProtocolFactory::textDocumentIdentifier(),
            ProtocolFactory::position(0, 37),
            $editor,
        );
        $consumer = new DefinitionConsumer();
        $contributor->contribute($context, $consumer);

        $this->assertCount(1, $consumer->results);
        $this->assertInstanceOf(Location::class, $consumer->results[0]);
        $this->assertSame('file:///test.php', $consumer->results[0]->uri);
    }
}
