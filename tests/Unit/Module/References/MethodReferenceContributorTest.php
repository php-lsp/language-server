<?php

declare(strict_types=1);

namespace App\Tests\Unit\Module\References;

use App\Core\Contracts\References\ReferenceConsumer;
use App\Core\Contracts\References\ReferenceContext;
use App\Module\References\MethodReferenceContributor;
use App\Tests\Support\IndexTestHelper;
use App\Tests\Support\MockHelper;
use App\Tests\Support\ProtocolFactory;
use App\Tests\Support\PsiFileFactory;
use App\Tests\TestCase;
use Lsp\Protocol\Type\Location;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;

#[Group('unit')]
final class MethodReferenceContributorTest extends TestCase
{
    #[TestDox('returns empty when file not found')]
    public function testReturnsEmptyWhenNoFile(): void
    {
        $lookup = IndexTestHelper::createLookup();
        $fileManager = MockHelper::mock(\App\Module\PsiFile\InMemoryPsiFileManager::class);
        $fileManager->method('findPsiFile')->willReturn(null);

        $contributor = new MethodReferenceContributor($lookup, $fileManager);

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

    #[TestDox('finds method references from instance call')]
    public function testFindsMethodReferences(): void
    {
        $cursorCode = '<?php $obj->doSomething();';
        $cursorFile = PsiFileFactory::fromCode($cursorCode);

        $usageCode = '<?php $a->doSomething();';
        $usageFile = PsiFileFactory::fromCode($usageCode, 'file:///usage.php');

        $methodPos = strpos($usageCode, 'doSomething');
        $lookup = IndexTestHelper::createLookup([
            'php.methodCallUsages' => [
                'file:///usage.php' => [['doSomething', $methodPos, null]],
            ],
        ]);

        $fileManager = MockHelper::mock(\App\Module\PsiFile\InMemoryPsiFileManager::class);
        $fileManager->method('findPsiFile')->willReturnCallback(
            fn ($editor, $identifier) => match ($identifier->uri) {
                'file:///usage.php' => $usageFile,
                default => $cursorFile,
            },
        );

        $contributor = new MethodReferenceContributor($lookup, $fileManager);

        $editor = MockHelper::mock(\Lsp\Extension\DocumentManager\Editor\EditorInterface::class);
        $context = new ReferenceContext(
            ProtocolFactory::textDocumentIdentifier(),
            ProtocolFactory::position(0, 14),
            $editor,
        );
        $consumer = new ReferenceConsumer();
        $contributor->contribute($context, $consumer);

        $this->assertNotEmpty($consumer->results);
        $this->assertInstanceOf(Location::class, $consumer->results[0]);
    }

    #[TestDox('finds method references from definition')]
    public function testFindsMethodReferencesFromDefinition(): void
    {
        $defCode = '<?php class Foo { public function bar() {} }';
        $defFile = PsiFileFactory::fromCode($defCode);

        $usageCode = '<?php $x->bar();';
        $usageFile = PsiFileFactory::fromCode($usageCode, 'file:///usage.php');

        $methodPos = strpos($usageCode, 'bar');
        $lookup = IndexTestHelper::createLookup([
            'php.methodCallUsages' => [
                'file:///usage.php' => [['bar', $methodPos, null]],
            ],
        ]);

        $fileManager = MockHelper::mock(\App\Module\PsiFile\InMemoryPsiFileManager::class);
        $fileManager->method('findPsiFile')->willReturnCallback(
            fn ($editor, $identifier) => match ($identifier->uri) {
                'file:///usage.php' => $usageFile,
                default => $defFile,
            },
        );

        $contributor = new MethodReferenceContributor($lookup, $fileManager);

        $editor = MockHelper::mock(\Lsp\Extension\DocumentManager\Editor\EditorInterface::class);
        $context = new ReferenceContext(
            ProtocolFactory::textDocumentIdentifier(),
            ProtocolFactory::position(0, 36),
            $editor,
        );
        $consumer = new ReferenceConsumer();
        $contributor->contribute($context, $consumer);

        $this->assertNotEmpty($consumer->results);
    }

    #[TestDox('returns empty when cursor on non-method')]
    public function testReturnsEmptyWhenNotMethod(): void
    {
        $psiFile = PsiFileFactory::fromCode('<?php echo 1;');
        $lookup = IndexTestHelper::createLookup();
        $fileManager = MockHelper::mock(\App\Module\PsiFile\InMemoryPsiFileManager::class);
        $fileManager->method('findPsiFile')->willReturn($psiFile);

        $contributor = new MethodReferenceContributor($lookup, $fileManager);

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
