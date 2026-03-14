<?php

declare(strict_types=1);

namespace App\Tests\Unit\Module\References;

use App\Core\Contracts\References\ReferenceConsumer;
use App\Core\Contracts\References\ReferenceContext;
use App\Module\References\InterfaceReferenceContributor;
use App\Tests\Support\IndexTestHelper;
use App\Tests\Support\MockHelper;
use App\Tests\Support\ProtocolFactory;
use App\Tests\Support\PsiFileFactory;
use App\Tests\TestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;

#[Group('unit')]
final class InterfaceReferenceContributorTest extends TestCase
{
    #[TestDox('returns empty when file not found')]
    public function testReturnsEmptyWhenNoFile(): void
    {
        $lookup = IndexTestHelper::createLookup();
        $fileManager = MockHelper::mock(\App\Module\PsiFile\InMemoryPsiFileManager::class);
        $fileManager->method('findPsiFile')->willReturn(null);

        $contributor = new InterfaceReferenceContributor($lookup, $fileManager);

        $editor = MockHelper::mock(\Lsp\Extension\DocumentManager\Editor\EditorInterface::class);
        $context = new ReferenceContext(
            ProtocolFactory::textDocumentIdentifier(),
            ProtocolFactory::position(0, 10),
            $editor,
        );
        $consumer = new ReferenceConsumer();
        $contributor->contribute($context, $consumer);

        $this->assertEmpty($consumer->results);
    }

    #[TestDox('returns empty when cursor is not on interface name')]
    public function testReturnsEmptyWhenNotInterfaceName(): void
    {
        $psiFile = PsiFileFactory::fromCode('<?php echo 1;');
        $lookup = IndexTestHelper::createLookup();
        $fileManager = MockHelper::mock(\App\Module\PsiFile\InMemoryPsiFileManager::class);
        $fileManager->method('findPsiFile')->willReturn($psiFile);

        $contributor = new InterfaceReferenceContributor($lookup, $fileManager);

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

    #[TestDox('finds interface references from definition')]
    public function testFindsInterfaceReferences(): void
    {
        $cursorFile = PsiFileFactory::fromCode('<?php interface Foo {}');
        $usageCode = '<?php class Bar implements \Foo {}';
        $usageFile = PsiFileFactory::fromCode($usageCode, 'file:///usage.php');

        $fooPos = strpos($usageCode, '\\Foo') + 1;
        $lookup = IndexTestHelper::createLookup([
            'php.classUsages' => [
                'file:///usage.php' => [['Foo', $fooPos]],
            ],
        ]);

        $fileManager = MockHelper::mock(\App\Module\PsiFile\InMemoryPsiFileManager::class);
        $fileManager->method('findPsiFile')->willReturnCallback(
            fn ($editor, $identifier) => match ($identifier->uri) {
                'file:///usage.php' => $usageFile,
                default => $cursorFile,
            },
        );

        $contributor = new InterfaceReferenceContributor($lookup, $fileManager);

        $editor = MockHelper::mock(\Lsp\Extension\DocumentManager\Editor\EditorInterface::class);
        $context = new ReferenceContext(
            ProtocolFactory::textDocumentIdentifier(),
            ProtocolFactory::position(0, 17),
            $editor,
        );
        $consumer = new ReferenceConsumer();
        $contributor->contribute($context, $consumer);

        $this->assertNotEmpty($consumer->results);
    }

    #[TestDox('returns empty when cursor on class not interface')]
    public function testReturnsEmptyForClassNotInterface(): void
    {
        $psiFile = PsiFileFactory::fromCode('<?php class Foo {}');
        $lookup = IndexTestHelper::createLookup();
        $fileManager = MockHelper::mock(\App\Module\PsiFile\InMemoryPsiFileManager::class);
        $fileManager->method('findPsiFile')->willReturn($psiFile);

        $contributor = new InterfaceReferenceContributor($lookup, $fileManager);

        $editor = MockHelper::mock(\Lsp\Extension\DocumentManager\Editor\EditorInterface::class);
        $context = new ReferenceContext(
            ProtocolFactory::textDocumentIdentifier(),
            ProtocolFactory::position(0, 14),
            $editor,
        );
        $consumer = new ReferenceConsumer();
        $contributor->contribute($context, $consumer);

        $this->assertEmpty($consumer->results);
    }
}
