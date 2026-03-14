<?php

declare(strict_types=1);

namespace App\Tests\Unit\Module\References;

use App\Core\Contracts\References\ReferenceConsumer;
use App\Core\Contracts\References\ReferenceContext;
use App\Module\References\PropertyReferenceContributor;
use App\Tests\Support\IndexTestHelper;
use App\Tests\Support\MockHelper;
use App\Tests\Support\ProtocolFactory;
use App\Tests\Support\PsiFileFactory;
use App\Tests\TestCase;
use Lsp\Protocol\Type\Location;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;

#[Group('unit')]
final class PropertyReferenceContributorTest extends TestCase
{
    #[TestDox('returns empty when file not found')]
    public function testReturnsEmptyWhenNoFile(): void
    {
        $lookup = IndexTestHelper::createLookup();
        $fileManager = MockHelper::mock(\App\Module\PsiFile\InMemoryPsiFileManager::class);
        $fileManager->method('findPsiFile')->willReturn(null);

        $contributor = new PropertyReferenceContributor($lookup, $fileManager);

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

    #[TestDox('finds property references from instance access')]
    public function testFindsPropertyReferences(): void
    {
        $cursorCode = '<?php $obj->name;';
        $cursorFile = PsiFileFactory::fromCode($cursorCode);

        $usageCode = '<?php $a->name;';
        $usageFile = PsiFileFactory::fromCode($usageCode, 'file:///usage.php');

        $propPos = strpos($usageCode, 'name');
        $lookup = IndexTestHelper::createLookup([
            'php.propertyAccessUsages' => [
                'file:///usage.php' => [['name', $propPos, null]],
            ],
        ]);

        $fileManager = MockHelper::mock(\App\Module\PsiFile\InMemoryPsiFileManager::class);
        $fileManager->method('findPsiFile')->willReturnCallback(
            fn ($editor, $identifier) => match ($identifier->uri) {
                'file:///usage.php' => $usageFile,
                default => $cursorFile,
            },
        );

        $contributor = new PropertyReferenceContributor($lookup, $fileManager);

        $editor = MockHelper::mock(\Lsp\Extension\DocumentManager\Editor\EditorInterface::class);
        $context = new ReferenceContext(
            ProtocolFactory::textDocumentIdentifier(),
            ProtocolFactory::position(0, 13),
            $editor,
        );
        $consumer = new ReferenceConsumer();
        $contributor->contribute($context, $consumer);

        $this->assertNotEmpty($consumer->results);
        $this->assertInstanceOf(Location::class, $consumer->results[0]);
    }

    #[TestDox('returns empty when cursor on non-property')]
    public function testReturnsEmptyWhenNotProperty(): void
    {
        $psiFile = PsiFileFactory::fromCode('<?php echo 1;');
        $lookup = IndexTestHelper::createLookup();
        $fileManager = MockHelper::mock(\App\Module\PsiFile\InMemoryPsiFileManager::class);
        $fileManager->method('findPsiFile')->willReturn($psiFile);

        $contributor = new PropertyReferenceContributor($lookup, $fileManager);

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
}
