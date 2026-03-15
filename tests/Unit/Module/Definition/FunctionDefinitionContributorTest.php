<?php

declare(strict_types=1);

namespace App\Tests\Unit\Module\Definition;

use App\Core\Contracts\Definition\DefinitionConsumer;
use App\Core\Contracts\Definition\DefinitionContext;
use App\Module\Definition\FunctionDefinitionContributor;
use App\Module\Indexing\Data\FunctionData;
use App\Tests\Support\IndexTestHelper;
use App\Tests\Support\MockHelper;
use App\Tests\Support\ProtocolFactory;
use App\Tests\Support\PsiFileFactory;
use App\Tests\TestCase;
use Lsp\Protocol\Type\Location;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;

#[Group('unit')]
final class FunctionDefinitionContributorTest extends TestCase
{
    #[TestDox('returns empty when file not found')]
    public function testReturnsEmptyWhenNoFile(): void
    {
        $lookup = IndexTestHelper::createLookup();
        $fileManager = MockHelper::mock(\App\Module\PsiFile\InMemoryPsiFileManager::class);
        $fileManager->method('findPsiFile')->willReturn(null);
        $docFactory = MockHelper::mock(\App\Module\Document\DocumentIdentifierFactoryInterface::class);

        $contributor = new FunctionDefinitionContributor($lookup, $fileManager, $docFactory);

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

    #[TestDox('returns empty when node is not a function call')]
    public function testReturnsEmptyWhenNotFuncCall(): void
    {
        $psiFile = PsiFileFactory::fromCode('<?php echo 1;');
        $lookup = IndexTestHelper::createLookup();
        $fileManager = MockHelper::mock(\App\Module\PsiFile\InMemoryPsiFileManager::class);
        $fileManager->method('findPsiFile')->willReturn($psiFile);
        $docFactory = MockHelper::mock(\App\Module\Document\DocumentIdentifierFactoryInterface::class);

        $contributor = new FunctionDefinitionContributor($lookup, $fileManager, $docFactory);

        $editor = MockHelper::mock(\Lsp\Extension\DocumentManager\Editor\EditorInterface::class);
        $context = new DefinitionContext(
            ProtocolFactory::textDocumentIdentifier(),
            ProtocolFactory::position(0, 6),
            $editor,
        );
        $consumer = new DefinitionConsumer();
        $contributor->contribute($context, $consumer);

        $this->assertEmpty($consumer->results);
    }

    #[TestDox('finds function definition')]
    public function testFindsFunctionDefinition(): void
    {
        $defCode = '<?php function foo() {}';
        $defPsiFile = PsiFileFactory::fromCode($defCode, 'file:///def.php');

        $usageCode = '<?php \foo();';
        $usagePsiFile = PsiFileFactory::fromCode($usageCode);

        $lookup = IndexTestHelper::createLookup([
            'php.functions.fqn' => [
                'file:///def.php' => ['foo' => new FunctionData('foo', 6, 22, null, [])],
            ],
        ]);

        $fileManager = MockHelper::mock(\App\Module\PsiFile\InMemoryPsiFileManager::class);
        $fileManager->method('findPsiFile')->willReturnCallback(
            function ($editor, $textDoc) use ($usagePsiFile, $defPsiFile) {
                if ($textDoc->uri === 'file:///def.php') {
                    return $defPsiFile;
                }

                return $usagePsiFile;
            },
        );

        $docFactory = MockHelper::mock(\App\Module\Document\DocumentIdentifierFactoryInterface::class);
        $docFactory->method('create')->willReturn(ProtocolFactory::textDocumentIdentifier('file:///def.php'));

        $contributor = new FunctionDefinitionContributor($lookup, $fileManager, $docFactory);

        $editor = MockHelper::mock(\Lsp\Extension\DocumentManager\Editor\EditorInterface::class);
        $context = new DefinitionContext(
            ProtocolFactory::textDocumentIdentifier(),
            ProtocolFactory::position(0, 7),
            $editor,
        );
        $consumer = new DefinitionConsumer();
        $contributor->contribute($context, $consumer);

        $this->assertCount(1, $consumer->results);
        $this->assertInstanceOf(Location::class, $consumer->results[0]);
        $this->assertSame('file:///def.php', $consumer->results[0]->uri);
    }
}
