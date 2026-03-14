<?php

declare(strict_types=1);

namespace App\Tests\Unit\Module\Signature;

use App\Core\Contracts\Signature\SignatureConsumer;
use App\Core\Contracts\Signature\SignatureContext;
use App\Module\Signature\ConstructorSignatureContributor;
use App\Tests\Support\IndexTestHelper;
use App\Tests\Support\MockHelper;
use App\Tests\Support\ProtocolFactory;
use App\Tests\Support\PsiFileFactory;
use App\Tests\TestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;

#[Group('unit')]
final class ConstructorSignatureContributorTest extends TestCase
{
    #[TestDox('returns empty when no file')]
    public function testReturnsEmptyWhenNoFile(): void
    {
        $fileManager = MockHelper::mock(\App\Module\PsiFile\InMemoryPsiFileManager::class);
        $fileManager->method('findPsiFile')->willReturn(null);

        $lookup = IndexTestHelper::createLookup();
        $contributor = new ConstructorSignatureContributor($fileManager, $lookup);

        $editor = MockHelper::mock(\Lsp\Extension\DocumentManager\Editor\EditorInterface::class);
        $context = new SignatureContext(
            ProtocolFactory::textDocumentIdentifier(),
            ProtocolFactory::position(0, 5),
            $editor,
        );
        $consumer = new SignatureConsumer();
        $contributor->contribute($context, $consumer);

        $this->assertEmpty($consumer->results);
    }

    #[TestDox('returns empty when no new expression at position')]
    public function testReturnsEmptyWhenNoNewExpression(): void
    {
        $psiFile = PsiFileFactory::fromCode('<?php echo 1;');
        $fileManager = MockHelper::mock(\App\Module\PsiFile\InMemoryPsiFileManager::class);
        $fileManager->method('findPsiFile')->willReturn($psiFile);

        $lookup = IndexTestHelper::createLookup();
        $contributor = new ConstructorSignatureContributor($fileManager, $lookup);

        $editor = MockHelper::mock(\Lsp\Extension\DocumentManager\Editor\EditorInterface::class);
        $context = new SignatureContext(
            ProtocolFactory::textDocumentIdentifier(),
            ProtocolFactory::position(0, 5),
            $editor,
        );
        $consumer = new SignatureConsumer();
        $contributor->contribute($context, $consumer);

        $this->assertEmpty($consumer->results);
    }

    #[TestDox('finds constructor signature with parameters')]
    public function testFindsConstructorSignatureWithParams(): void
    {
        $callerCode = '<?php new \Foo();';
        $callerFile = PsiFileFactory::fromCode($callerCode);

        $defCode = '<?php class Foo { public function __construct(string $name, int $age) {} }';
        $defFile = PsiFileFactory::fromCode($defCode, 'file:///def.php');

        $fileManager = MockHelper::mock(\App\Module\PsiFile\InMemoryPsiFileManager::class);
        $fileManager->method('findPsiFile')->willReturnCallback(
            fn ($editor, $identifier) => match ($identifier->uri) {
                'file:///def.php' => $defFile,
                default => $callerFile,
            },
        );

        $lookup = IndexTestHelper::createLookup([
            'php.classes.fqn' => [
                'file:///def.php' => ['Foo' => 'Foo'],
            ],
        ]);

        $contributor = new ConstructorSignatureContributor($fileManager, $lookup);

        $editor = MockHelper::mock(\Lsp\Extension\DocumentManager\Editor\EditorInterface::class);
        $context = new SignatureContext(
            ProtocolFactory::textDocumentIdentifier(),
            ProtocolFactory::position(0, 13),
            $editor,
        );
        $consumer = new SignatureConsumer();
        $contributor->contribute($context, $consumer);

        $this->assertNotEmpty($consumer->results);
        $this->assertStringContainsString('new Foo', $consumer->results[0]->label);
        $this->assertStringContainsString('string $name', $consumer->results[0]->label);
        $this->assertCount(2, $consumer->results[0]->parameters);
    }

    #[TestDox('finds constructor signature for class without constructor')]
    public function testFindsSignatureForClassWithoutConstructor(): void
    {
        $callerCode = '<?php new \Foo();';
        $callerFile = PsiFileFactory::fromCode($callerCode);

        $defCode = '<?php class Foo {}';
        $defFile = PsiFileFactory::fromCode($defCode, 'file:///def.php');

        $fileManager = MockHelper::mock(\App\Module\PsiFile\InMemoryPsiFileManager::class);
        $fileManager->method('findPsiFile')->willReturnCallback(
            fn ($editor, $identifier) => match ($identifier->uri) {
                'file:///def.php' => $defFile,
                default => $callerFile,
            },
        );

        $lookup = IndexTestHelper::createLookup([
            'php.classes.fqn' => [
                'file:///def.php' => ['Foo' => 'Foo'],
            ],
        ]);

        $contributor = new ConstructorSignatureContributor($fileManager, $lookup);

        $editor = MockHelper::mock(\Lsp\Extension\DocumentManager\Editor\EditorInterface::class);
        $context = new SignatureContext(
            ProtocolFactory::textDocumentIdentifier(),
            ProtocolFactory::position(0, 13),
            $editor,
        );
        $consumer = new SignatureConsumer();
        $contributor->contribute($context, $consumer);

        $this->assertNotEmpty($consumer->results);
        $this->assertSame('new Foo()', $consumer->results[0]->label);
        $this->assertEmpty($consumer->results[0]->parameters);
    }

    #[TestDox('returns empty when class not in index')]
    public function testReturnsEmptyWhenNotInIndex(): void
    {
        $psiFile = PsiFileFactory::fromCode('<?php new \Foo();');
        $fileManager = MockHelper::mock(\App\Module\PsiFile\InMemoryPsiFileManager::class);
        $fileManager->method('findPsiFile')->willReturn($psiFile);

        $lookup = IndexTestHelper::createLookup([
            'php.classes.fqn' => [
                'file:///def.php' => ['Bar' => 'Bar'],
            ],
        ]);

        $contributor = new ConstructorSignatureContributor($fileManager, $lookup);

        $editor = MockHelper::mock(\Lsp\Extension\DocumentManager\Editor\EditorInterface::class);
        $context = new SignatureContext(
            ProtocolFactory::textDocumentIdentifier(),
            ProtocolFactory::position(0, 13),
            $editor,
        );
        $consumer = new SignatureConsumer();
        $contributor->contribute($context, $consumer);

        $this->assertEmpty($consumer->results);
    }

    #[TestDox('includes docblock in constructor signature')]
    public function testIncludesDocblock(): void
    {
        $callerCode = '<?php new \Foo();';
        $callerFile = PsiFileFactory::fromCode($callerCode);

        $defCode = "<?php class Foo {\n/** Create a new Foo */\npublic function __construct() {} }";
        $defFile = PsiFileFactory::fromCode($defCode, 'file:///def.php');

        $fileManager = MockHelper::mock(\App\Module\PsiFile\InMemoryPsiFileManager::class);
        $fileManager->method('findPsiFile')->willReturnCallback(
            fn ($editor, $identifier) => match ($identifier->uri) {
                'file:///def.php' => $defFile,
                default => $callerFile,
            },
        );

        $lookup = IndexTestHelper::createLookup([
            'php.classes.fqn' => [
                'file:///def.php' => ['Foo' => 'Foo'],
            ],
        ]);

        $contributor = new ConstructorSignatureContributor($fileManager, $lookup);

        $editor = MockHelper::mock(\Lsp\Extension\DocumentManager\Editor\EditorInterface::class);
        $context = new SignatureContext(
            ProtocolFactory::textDocumentIdentifier(),
            ProtocolFactory::position(0, 13),
            $editor,
        );
        $consumer = new SignatureConsumer();
        $contributor->contribute($context, $consumer);

        $this->assertNotEmpty($consumer->results);
        $this->assertStringContainsString('Create a new Foo', $consumer->results[0]->documentation);
    }
}
