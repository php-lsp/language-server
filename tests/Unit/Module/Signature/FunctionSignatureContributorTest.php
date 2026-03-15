<?php

declare(strict_types=1);

namespace App\Tests\Unit\Module\Signature;

use App\Core\Contracts\Signature\SignatureConsumer;
use App\Core\Contracts\Signature\SignatureContext;
use App\Module\Indexing\Storage\IndexData\FunctionData;
use App\Module\Signature\FunctionSignatureContributor;
use App\Tests\Support\IndexTestHelper;
use App\Tests\Support\MockHelper;
use App\Tests\Support\ProtocolFactory;
use App\Tests\Support\PsiFileFactory;
use App\Tests\TestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;

#[Group('unit')]
final class FunctionSignatureContributorTest extends TestCase
{
    #[TestDox('returns empty when no FuncCall node at position')]
    public function testReturnsEmptyWhenNoFuncCall(): void
    {
        $psiFile = PsiFileFactory::fromCode('<?php echo 1;');
        $fileManager = MockHelper::mock(\App\Module\PsiFile\InMemoryPsiFileManager::class);
        $fileManager->method('findPsiFile')->willReturn($psiFile);

        $lookup = IndexTestHelper::createLookup();
        $contributor = new FunctionSignatureContributor($fileManager, $lookup);

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

    #[TestDox('finds signature for no-param function')]
    public function testFindsSignatureForNoParamFunction(): void
    {
        $callerFile = PsiFileFactory::fromCode('<?php myFunc();');
        // Function with return type, no params, with docblock
        $defCode = "<?php\n/** Doc */\nfunction myFunc(): void {}";
        $defFile = PsiFileFactory::fromCode($defCode);

        $fileManager = MockHelper::mock(\App\Module\PsiFile\InMemoryPsiFileManager::class);
        $fileManager->method('findPsiFile')->willReturnCallback(function ($editor, $identifier) use ($callerFile, $defFile) {
            if ($identifier->uri === 'file:///def.php') {
                return $defFile;
            }
            return $callerFile;
        });

        $funcStartPos = strpos($defCode, 'function');

        $lookup = IndexTestHelper::createLookup([
            'php.functions.fqn' => [
                'file:///def.php' => ['myFunc' => new FunctionData('myFunc', $funcStartPos, strlen($defCode) - 1, [], 'void')],
            ],
        ]);

        $contributor = new FunctionSignatureContributor($fileManager, $lookup);

        $editor = MockHelper::mock(\Lsp\Extension\DocumentManager\Editor\EditorInterface::class);
        $context = new SignatureContext(
            ProtocolFactory::textDocumentIdentifier(),
            ProtocolFactory::position(0, 8),
            $editor,
        );
        $consumer = new SignatureConsumer();
        $contributor->contribute($context, $consumer);

        $this->assertNotEmpty($consumer->results);
        $this->assertSame('myFunc(): void', $consumer->results[0]->label);
    }

    #[TestDox('returns empty when function not in index')]
    public function testReturnsEmptyWhenNotInIndex(): void
    {
        $psiFile = PsiFileFactory::fromCode('<?php myFunc(1);');
        $fileManager = MockHelper::mock(\App\Module\PsiFile\InMemoryPsiFileManager::class);
        $fileManager->method('findPsiFile')->willReturn($psiFile);

        $lookup = IndexTestHelper::createLookup([
            'php.functions.fqn' => [
                'file:///def.php' => ['otherFunc' => new FunctionData('otherFunc', 0, 20, [], null)],
            ],
        ]);

        $contributor = new FunctionSignatureContributor($fileManager, $lookup);

        $editor = MockHelper::mock(\Lsp\Extension\DocumentManager\Editor\EditorInterface::class);
        $context = new SignatureContext(
            ProtocolFactory::textDocumentIdentifier(),
            ProtocolFactory::position(0, 8),
            $editor,
        );
        $consumer = new SignatureConsumer();
        $contributor->contribute($context, $consumer);

        $this->assertEmpty($consumer->results);
    }
}
