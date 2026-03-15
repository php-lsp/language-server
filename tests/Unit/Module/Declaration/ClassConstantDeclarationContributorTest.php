<?php

declare(strict_types=1);

namespace App\Tests\Unit\Module\Declaration;

use App\Core\Contracts\Declaration\DeclarationConsumer;
use App\Core\Contracts\Declaration\DeclarationContext;
use App\Module\Declaration\ClassConstantDeclarationContributor;
use App\Module\Document\DocumentIdentifierFactoryInterface;
use App\Module\Indexing\Storage\IndexData\ConstantData;
use App\Tests\Support\IndexTestHelper;
use App\Tests\Support\MockHelper;
use App\Tests\Support\ProtocolFactory;
use App\Tests\Support\PsiFileFactory;
use App\Tests\TestCase;
use Lsp\Protocol\Type\Location;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;

#[Group('unit')]
final class ClassConstantDeclarationContributorTest extends TestCase
{
    #[TestDox('returns empty when file not found')]
    public function testReturnsEmptyWhenNoFile(): void
    {
        $lookup = IndexTestHelper::createLookup();
        $fileManager = MockHelper::mock(\App\Module\PsiFile\InMemoryPsiFileManager::class);
        $fileManager->method('findPsiFile')->willReturn(null);
        $docFactory = $this->createMock(DocumentIdentifierFactoryInterface::class);

        $contributor = new ClassConstantDeclarationContributor($lookup, $fileManager, $docFactory);

        $editor = MockHelper::mock(\Lsp\Extension\DocumentManager\Editor\EditorInterface::class);
        $context = new DeclarationContext(
            ProtocolFactory::textDocumentIdentifier(),
            ProtocolFactory::position(0, 10),
            $editor,
        );
        $consumer = new DeclarationConsumer();
        $contributor->contribute($context, $consumer);

        $this->assertEmpty($consumer->results);
    }

    #[TestDox('finds class constant declaration for Foo::BAR')]
    public function testFindsClassConstantDeclaration(): void
    {
        $code = '<?php \Foo::BAR;';
        $psiFile = PsiFileFactory::fromCode($code);
        $lookup = IndexTestHelper::createLookup([
            'php.classConstants.fqn' => [
                'file:///def.php' => ['BAR' => new ConstantData('BAR', 'Foo', 0, 10, null, null)],
            ],
        ]);
        $fileManager = MockHelper::mock(\App\Module\PsiFile\InMemoryPsiFileManager::class);
        $fileManager->method('findPsiFile')->willReturn($psiFile);
        $docFactory = $this->createMock(DocumentIdentifierFactoryInterface::class);
        $docFactory->method('create')->willReturn(ProtocolFactory::textDocumentIdentifier('file:///def.php'));

        $contributor = new ClassConstantDeclarationContributor($lookup, $fileManager, $docFactory);

        $editor = MockHelper::mock(\Lsp\Extension\DocumentManager\Editor\EditorInterface::class);
        $context = new DeclarationContext(
            ProtocolFactory::textDocumentIdentifier(),
            ProtocolFactory::position(0, 13),
            $editor,
        );
        $consumer = new DeclarationConsumer();
        $contributor->contribute($context, $consumer);

        $this->assertCount(1, $consumer->results);
        $this->assertInstanceOf(Location::class, $consumer->results[0]);
        $this->assertSame('file:///def.php', $consumer->results[0]->uri);
    }
}
