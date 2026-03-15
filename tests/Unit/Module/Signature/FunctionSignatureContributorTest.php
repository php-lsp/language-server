<?php

declare(strict_types=1);

namespace App\Tests\Unit\Module\Signature;

use App\Core\Contracts\Signature\SignatureConsumer;
use App\Core\Contracts\Signature\SignatureContext;
use App\Module\Indexing\Data\FunctionData;
use App\Module\Indexing\Data\ParameterData;
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

        $lookup = IndexTestHelper::createLookup([
            'php.functions.fqn' => [
                'file:///def.php' => ['myFunc' => new FunctionData('myFunc', 0, 30, 'void', [])],
            ],
        ]);

        $fileManager = MockHelper::mock(\App\Module\PsiFile\InMemoryPsiFileManager::class);
        $fileManager->method('findPsiFile')->willReturn($callerFile);

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
                'file:///def.php' => ['otherFunc' => new FunctionData('otherFunc', 0, 30, null, [])],
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
