<?php

declare(strict_types=1);

namespace App\Tests\Unit\Module\Signature;

use App\Core\Contracts\Signature\SignatureConsumer;
use App\Core\Contracts\Signature\SignatureContext;
use App\Module\Signature\MethodSignatureContributor;
use App\Tests\Support\IndexTestHelper;
use App\Tests\Support\MockHelper;
use App\Tests\Support\ProtocolFactory;
use App\Tests\Support\PsiFileFactory;
use App\Tests\TestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;

#[Group('unit')]
final class MethodSignatureContributorTest extends TestCase
{
    #[TestDox('returns empty when no file')]
    public function testReturnsEmptyWhenNoFile(): void
    {
        $fileManager = MockHelper::mock(\App\Module\PsiFile\InMemoryPsiFileManager::class);
        $fileManager->method('findPsiFile')->willReturn(null);

        $lookup = IndexTestHelper::createLookup();
        $contributor = new MethodSignatureContributor($fileManager, $lookup);

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

    #[TestDox('returns empty when no method call at position')]
    public function testReturnsEmptyWhenNoMethodCall(): void
    {
        $psiFile = PsiFileFactory::fromCode('<?php echo 1;');
        $fileManager = MockHelper::mock(\App\Module\PsiFile\InMemoryPsiFileManager::class);
        $fileManager->method('findPsiFile')->willReturn($psiFile);

        $lookup = IndexTestHelper::createLookup();
        $contributor = new MethodSignatureContributor($fileManager, $lookup);

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

    #[TestDox('finds signature for static method call')]
    public function testFindsSignatureForStaticCall(): void
    {
        $callerCode = '<?php \Foo::bar();';
        $callerFile = PsiFileFactory::fromCode($callerCode);

        $defCode = '<?php class Foo { public function bar(string $name, int $age): void {} }';
        $defFile = PsiFileFactory::fromCode($defCode, 'file:///def.php');

        $fileManager = MockHelper::mock(\App\Module\PsiFile\InMemoryPsiFileManager::class);
        $fileManager->method('findPsiFile')->willReturnCallback(
            fn ($editor, $identifier) => match ($identifier->uri) {
                'file:///def.php' => $defFile,
                default => $callerFile,
            },
        );

        $lookup = IndexTestHelper::createLookup([
            'php.classMethods.fqn' => [
                'file:///def.php' => ['Foo' => ['bar']],
            ],
        ]);

        $contributor = new MethodSignatureContributor($fileManager, $lookup);

        $editor = MockHelper::mock(\Lsp\Extension\DocumentManager\Editor\EditorInterface::class);
        $context = new SignatureContext(
            ProtocolFactory::textDocumentIdentifier(),
            ProtocolFactory::position(0, 14),
            $editor,
        );
        $consumer = new SignatureConsumer();
        $contributor->contribute($context, $consumer);

        $this->assertNotEmpty($consumer->results);
        $this->assertStringContainsString('Foo::bar', $consumer->results[0]->label);
        $this->assertStringContainsString('string $name', $consumer->results[0]->label);
        $this->assertStringContainsString(': void', $consumer->results[0]->label);
        $this->assertCount(2, $consumer->results[0]->parameters);
    }

    #[TestDox('finds signature for method call on $this')]
    public function testFindsSignatureForThisCall(): void
    {
        $code = '<?php class Foo { public function bar(int $x): string { return ""; } public function baz() { $this->bar(); } }';
        $file = PsiFileFactory::fromCode($code);

        $fileManager = MockHelper::mock(\App\Module\PsiFile\InMemoryPsiFileManager::class);
        $fileManager->method('findPsiFile')->willReturn($file);

        $lookup = IndexTestHelper::createLookup([
            'php.classMethods.fqn' => [
                'file:///test.php' => ['Foo' => ['bar', 'baz']],
            ],
        ]);

        $contributor = new MethodSignatureContributor($fileManager, $lookup);

        $barPos = strpos($code, '$this->bar()') + 9;
        $editor = MockHelper::mock(\Lsp\Extension\DocumentManager\Editor\EditorInterface::class);
        $context = new SignatureContext(
            ProtocolFactory::textDocumentIdentifier(),
            ProtocolFactory::position(0, $barPos),
            $editor,
        );
        $consumer = new SignatureConsumer();
        $contributor->contribute($context, $consumer);

        $this->assertNotEmpty($consumer->results);
        $this->assertStringContainsString('bar', $consumer->results[0]->label);
    }

    #[TestDox('returns empty when method not in index')]
    public function testReturnsEmptyWhenNotInIndex(): void
    {
        $code = '<?php \Foo::bar();';
        $file = PsiFileFactory::fromCode($code);

        $fileManager = MockHelper::mock(\App\Module\PsiFile\InMemoryPsiFileManager::class);
        $fileManager->method('findPsiFile')->willReturn($file);

        $lookup = IndexTestHelper::createLookup([
            'php.classMethods.fqn' => [
                'file:///def.php' => ['Other' => ['bar']],
            ],
        ]);

        $contributor = new MethodSignatureContributor($fileManager, $lookup);

        $editor = MockHelper::mock(\Lsp\Extension\DocumentManager\Editor\EditorInterface::class);
        $context = new SignatureContext(
            ProtocolFactory::textDocumentIdentifier(),
            ProtocolFactory::position(0, 14),
            $editor,
        );
        $consumer = new SignatureConsumer();
        $contributor->contribute($context, $consumer);

        $this->assertEmpty($consumer->results);
    }

    #[TestDox('includes docblock in signature')]
    public function testIncludesDocblock(): void
    {
        $callerCode = '<?php \Foo::bar();';
        $callerFile = PsiFileFactory::fromCode($callerCode);

        $defCode = "<?php class Foo {\n/** Does things */\npublic function bar(): void {} }";
        $defFile = PsiFileFactory::fromCode($defCode, 'file:///def.php');

        $fileManager = MockHelper::mock(\App\Module\PsiFile\InMemoryPsiFileManager::class);
        $fileManager->method('findPsiFile')->willReturnCallback(
            fn ($editor, $identifier) => match ($identifier->uri) {
                'file:///def.php' => $defFile,
                default => $callerFile,
            },
        );

        $lookup = IndexTestHelper::createLookup([
            'php.classMethods.fqn' => [
                'file:///def.php' => ['Foo' => ['bar']],
            ],
        ]);

        $contributor = new MethodSignatureContributor($fileManager, $lookup);

        $editor = MockHelper::mock(\Lsp\Extension\DocumentManager\Editor\EditorInterface::class);
        $context = new SignatureContext(
            ProtocolFactory::textDocumentIdentifier(),
            ProtocolFactory::position(0, 14),
            $editor,
        );
        $consumer = new SignatureConsumer();
        $contributor->contribute($context, $consumer);

        $this->assertNotEmpty($consumer->results);
        $this->assertStringContainsString('Does things', $consumer->results[0]->documentation);
    }
}
