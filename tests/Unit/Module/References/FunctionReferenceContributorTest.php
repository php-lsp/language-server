<?php

declare(strict_types=1);

namespace App\Tests\Unit\Module\References;

use App\Core\Contracts\References\ReferenceConsumer;
use App\Core\Contracts\References\ReferenceContext;
use App\Module\References\FunctionReferenceContributor;
use App\Tests\Support\IndexTestHelper;
use App\Tests\Support\MockHelper;
use App\Tests\Support\ProtocolFactory;
use App\Tests\Support\PsiFileFactory;
use App\Tests\TestCase;
use Lsp\Protocol\Type\Location;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;

#[Group('unit')]
final class FunctionReferenceContributorTest extends TestCase
{
    #[TestDox('returns empty when file not found')]
    public function testReturnsEmptyWhenNoFile(): void
    {
        $lookup = IndexTestHelper::createLookup();
        $fileManager = MockHelper::mock(\App\Module\PsiFile\InMemoryPsiFileManager::class);
        $fileManager->method('findPsiFile')->willReturn(null);

        $contributor = new FunctionReferenceContributor($lookup, $fileManager);

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

    #[TestDox('finds function references from call')]
    public function testFindsFunctionReferences(): void
    {
        $callerCode = '<?php myFunc();';
        $callerFile = PsiFileFactory::fromCode($callerCode);
        $usageCode = '<?php myFunc();';
        $usageFile = PsiFileFactory::fromCode($usageCode, 'file:///usage.php');

        $funcPos = strpos($usageCode, 'myFunc');
        $lookup = IndexTestHelper::createLookup([
            'php.functionCallUsages' => [
                'file:///usage.php' => [['myFunc', $funcPos]],
            ],
        ]);

        $fileManager = MockHelper::mock(\App\Module\PsiFile\InMemoryPsiFileManager::class);
        $fileManager->method('findPsiFile')->willReturnCallback(
            fn ($editor, $identifier) => match ($identifier->uri) {
                'file:///usage.php' => $usageFile,
                default => $callerFile,
            },
        );

        $contributor = new FunctionReferenceContributor($lookup, $fileManager);

        $editor = MockHelper::mock(\Lsp\Extension\DocumentManager\Editor\EditorInterface::class);
        $context = new ReferenceContext(
            ProtocolFactory::textDocumentIdentifier(),
            ProtocolFactory::position(0, 8),
            $editor,
        );
        $consumer = new ReferenceConsumer();
        $contributor->contribute($context, $consumer);

        $this->assertNotEmpty($consumer->results);
        $this->assertInstanceOf(Location::class, $consumer->results[0]);
        $this->assertSame('file:///usage.php', $consumer->results[0]->uri);
    }

    #[TestDox('finds function references from definition')]
    public function testFindsFunctionReferencesFromDefinition(): void
    {
        $defCode = '<?php function myFunc() {}';
        $defFile = PsiFileFactory::fromCode($defCode);
        $usageCode = '<?php myFunc();';
        $usageFile = PsiFileFactory::fromCode($usageCode, 'file:///usage.php');

        $funcPos = strpos($usageCode, 'myFunc');
        $lookup = IndexTestHelper::createLookup([
            'php.functionCallUsages' => [
                'file:///usage.php' => [['myFunc', $funcPos]],
            ],
        ]);

        $fileManager = MockHelper::mock(\App\Module\PsiFile\InMemoryPsiFileManager::class);
        $fileManager->method('findPsiFile')->willReturnCallback(
            fn ($editor, $identifier) => match ($identifier->uri) {
                'file:///usage.php' => $usageFile,
                default => $defFile,
            },
        );

        $contributor = new FunctionReferenceContributor($lookup, $fileManager);

        $editor = MockHelper::mock(\Lsp\Extension\DocumentManager\Editor\EditorInterface::class);
        $context = new ReferenceContext(
            ProtocolFactory::textDocumentIdentifier(),
            ProtocolFactory::position(0, 18),
            $editor,
        );
        $consumer = new ReferenceConsumer();
        $contributor->contribute($context, $consumer);

        $this->assertNotEmpty($consumer->results);
    }

    #[TestDox('returns empty when cursor on non-function')]
    public function testReturnsEmptyWhenNotFunction(): void
    {
        $psiFile = PsiFileFactory::fromCode('<?php $x = 1;');
        $lookup = IndexTestHelper::createLookup();
        $fileManager = MockHelper::mock(\App\Module\PsiFile\InMemoryPsiFileManager::class);
        $fileManager->method('findPsiFile')->willReturn($psiFile);

        $contributor = new FunctionReferenceContributor($lookup, $fileManager);

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
