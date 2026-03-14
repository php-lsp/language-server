<?php

declare(strict_types=1);

namespace App\Tests\Unit\Module\References;

use App\Core\Contracts\References\ReferenceConsumer;
use App\Core\Contracts\References\ReferenceContext;
use App\Module\References\VariableReferenceContributor;
use App\Tests\Support\MockHelper;
use App\Tests\Support\ProtocolFactory;
use App\Tests\Support\PsiFileFactory;
use App\Tests\TestCase;
use Lsp\Protocol\Type\Location;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;

#[Group('unit')]
final class VariableReferenceContributorTest extends TestCase
{
    #[TestDox('returns empty when file not found')]
    public function testReturnsEmptyWhenNoFile(): void
    {
        $fileManager = MockHelper::mock(\App\Module\PsiFile\InMemoryPsiFileManager::class);
        $fileManager->method('findPsiFile')->willReturn(null);

        $contributor = new VariableReferenceContributor($fileManager);

        $editor = MockHelper::mock(\Lsp\Extension\DocumentManager\Editor\EditorInterface::class);
        $context = new ReferenceContext(
            ProtocolFactory::textDocumentIdentifier(),
            ProtocolFactory::position(0, 5),
            $editor,
        );
        $consumer = new ReferenceConsumer();
        $contributor->contribute($context, $consumer);

        $this->assertEmpty($consumer->results);
    }

    #[TestDox('finds all variable usages in function scope')]
    public function testFindsVariableUsagesInFunction(): void
    {
        $code = '<?php function foo() { $x = 1; echo $x; return $x; }';
        $psiFile = PsiFileFactory::fromCode($code);
        $fileManager = MockHelper::mock(\App\Module\PsiFile\InMemoryPsiFileManager::class);
        $fileManager->method('findPsiFile')->willReturn($psiFile);

        $contributor = new VariableReferenceContributor($fileManager);

        $xPos = strpos($code, '$x');
        $editor = MockHelper::mock(\Lsp\Extension\DocumentManager\Editor\EditorInterface::class);
        $context = new ReferenceContext(
            ProtocolFactory::textDocumentIdentifier(),
            ProtocolFactory::position(0, $xPos + 1),
            $editor,
        );
        $consumer = new ReferenceConsumer();
        $contributor->contribute($context, $consumer);

        $this->assertCount(3, $consumer->results);
        foreach ($consumer->results as $result) {
            $this->assertInstanceOf(Location::class, $result);
        }
    }

    #[TestDox('returns empty when cursor not on variable')]
    public function testReturnsEmptyWhenNotVariable(): void
    {
        $psiFile = PsiFileFactory::fromCode('<?php echo 1;');
        $fileManager = MockHelper::mock(\App\Module\PsiFile\InMemoryPsiFileManager::class);
        $fileManager->method('findPsiFile')->willReturn($psiFile);

        $contributor = new VariableReferenceContributor($fileManager);

        $editor = MockHelper::mock(\Lsp\Extension\DocumentManager\Editor\EditorInterface::class);
        $context = new ReferenceContext(
            ProtocolFactory::textDocumentIdentifier(),
            ProtocolFactory::position(0, 5),
            $editor,
        );
        $consumer = new ReferenceConsumer();
        $contributor->contribute($context, $consumer);

        $this->assertEmpty($consumer->results);
    }

    #[TestDox('finds variables at file scope')]
    public function testFindsVariablesAtFileScope(): void
    {
        $code = '<?php $x = 1; $y = $x;';
        $psiFile = PsiFileFactory::fromCode($code);
        $fileManager = MockHelper::mock(\App\Module\PsiFile\InMemoryPsiFileManager::class);
        $fileManager->method('findPsiFile')->willReturn($psiFile);

        $contributor = new VariableReferenceContributor($fileManager);

        $editor = MockHelper::mock(\Lsp\Extension\DocumentManager\Editor\EditorInterface::class);
        $context = new ReferenceContext(
            ProtocolFactory::textDocumentIdentifier(),
            ProtocolFactory::position(0, 7),
            $editor,
        );
        $consumer = new ReferenceConsumer();
        $contributor->contribute($context, $consumer);

        $this->assertCount(2, $consumer->results);
    }

    #[TestDox('does not mix variables from different scopes')]
    public function testDoesNotMixScopes(): void
    {
        $code = '<?php $x = 1; function foo() { $x = 2; echo $x; }';
        $psiFile = PsiFileFactory::fromCode($code);
        $fileManager = MockHelper::mock(\App\Module\PsiFile\InMemoryPsiFileManager::class);
        $fileManager->method('findPsiFile')->willReturn($psiFile);

        $contributor = new VariableReferenceContributor($fileManager);

        $funcXPos = strpos($code, '$x = 2');
        $editor = MockHelper::mock(\Lsp\Extension\DocumentManager\Editor\EditorInterface::class);
        $context = new ReferenceContext(
            ProtocolFactory::textDocumentIdentifier(),
            ProtocolFactory::position(0, $funcXPos + 1),
            $editor,
        );
        $consumer = new ReferenceConsumer();
        $contributor->contribute($context, $consumer);

        $this->assertCount(2, $consumer->results);
    }
}
