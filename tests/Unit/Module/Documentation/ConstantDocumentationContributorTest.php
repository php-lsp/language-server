<?php

declare(strict_types=1);

namespace App\Tests\Unit\Module\Documentation;

use App\Core\Contracts\Documentation\DocumentationConsumer;
use App\Core\Contracts\Documentation\DocumentationContext;
use App\Module\Documentation\ConstantDocumentationContributor;
use App\Module\Indexing\Storage\IndexData\ConstantData;
use App\Module\PsiFile\InMemoryPsiFileManager;
use App\Tests\Support\IndexTestHelper;
use App\Tests\Support\MockHelper;
use App\Tests\Support\ProtocolFactory;
use App\Tests\Support\PsiFileFactory;
use App\Tests\TestCase;
use Lsp\Extension\DocumentManager\Editor\EditorInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;

#[Group('unit')]
final class ConstantDocumentationContributorTest extends TestCase
{
    #[TestDox('returns empty when file not found')]
    public function testReturnsEmptyWhenFileNotFound(): void
    {
        $fileManager = MockHelper::mock(InMemoryPsiFileManager::class);
        $fileManager->method('findPsiFile')->willReturn(null);

        $indexLookup = IndexTestHelper::createLookup();

        $context = new DocumentationContext(
            ProtocolFactory::textDocumentIdentifier(),
            ProtocolFactory::position(),
            MockHelper::mock(EditorInterface::class),
        );
        $consumer = new DocumentationConsumer();

        $contributor = new ConstantDocumentationContributor($indexLookup, $fileManager);
        $contributor->contribute($context, $consumer);

        $this->assertEmpty($consumer->results);
    }

    #[TestDox('returns documentation for a matching class constant')]
    public function testReturnsDocumentationForMatchingClassConstant(): void
    {
        $code = '<?php Foo::BAR;';
        $psiFile = PsiFileFactory::fromCode($code);

        $fileManager = MockHelper::mock(InMemoryPsiFileManager::class);
        $fileManager->method('findPsiFile')->willReturn($psiFile);

        $constantData = new ConstantData(
            name: 'BAR',
            ownerFqn: 'Foo',
            startPosition: 0,
            endPosition: 100,
            type: 'string',
            value: "'baz'",
        );

        $indexLookup = IndexTestHelper::createLookup([
            'php.classConstants.fqn' => ['file:///test.php' => [$constantData]],
        ]);

        $context = new DocumentationContext(
            ProtocolFactory::textDocumentIdentifier(),
            ProtocolFactory::position(0, 12),
            MockHelper::mock(EditorInterface::class),
        );
        $consumer = new DocumentationConsumer();

        $contributor = new ConstantDocumentationContributor($indexLookup, $fileManager);
        $contributor->contribute($context, $consumer);

        $this->assertNotEmpty($consumer->results);
        $this->assertStringContainsString('const Foo::BAR', $consumer->results[0]);
        $this->assertStringContainsString(': string', $consumer->results[0]);
        $this->assertStringContainsString("= 'baz'", $consumer->results[0]);
        $this->assertStringContainsString('```php', $consumer->results[0]);
    }

    #[TestDox('returns documentation for a matching global constant')]
    public function testReturnsDocumentationForMatchingGlobalConstant(): void
    {
        $code = '<?php echo MY_CONST;';
        $psiFile = PsiFileFactory::fromCode($code);

        $fileManager = MockHelper::mock(InMemoryPsiFileManager::class);
        $fileManager->method('findPsiFile')->willReturn($psiFile);

        $constantData = new ConstantData(
            name: 'MY_CONST',
            ownerFqn: null,
            startPosition: 0,
            endPosition: 100,
            type: null,
            value: '42',
        );

        $indexLookup = IndexTestHelper::createLookup([
            'php.constants.fqn' => ['file:///test.php' => [$constantData]],
        ]);

        $context = new DocumentationContext(
            ProtocolFactory::textDocumentIdentifier(),
            ProtocolFactory::position(0, 12),
            MockHelper::mock(EditorInterface::class),
        );
        $consumer = new DocumentationConsumer();

        $contributor = new ConstantDocumentationContributor($indexLookup, $fileManager);
        $contributor->contribute($context, $consumer);

        $this->assertNotEmpty($consumer->results);
        $this->assertStringContainsString('const MY_CONST', $consumer->results[0]);
        $this->assertStringContainsString('= 42', $consumer->results[0]);
        $this->assertStringContainsString('```php', $consumer->results[0]);
    }
}
