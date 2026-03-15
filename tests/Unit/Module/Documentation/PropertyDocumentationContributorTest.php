<?php

declare(strict_types=1);

namespace App\Tests\Unit\Module\Documentation;

use App\Core\Contracts\Documentation\DocumentationConsumer;
use App\Core\Contracts\Documentation\DocumentationContext;
use App\Module\Documentation\PropertyDocumentationContributor;
use App\Module\Indexing\Data\PropertyData;
use App\Module\Indexing\Data\Visibility;
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
final class PropertyDocumentationContributorTest extends TestCase
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

        $contributor = new PropertyDocumentationContributor($indexLookup, $fileManager);
        $contributor->contribute($context, $consumer);

        $this->assertEmpty($consumer->results);
    }

    #[TestDox('returns documentation for a matching property fetch on this')]
    public function testReturnsDocumentationForMatchingProperty(): void
    {
        $code = '<?php namespace App; class MyClass { public readonly string $name; public function test() { $this->name; } }';
        $psiFile = PsiFileFactory::fromCode($code);

        $fileManager = MockHelper::mock(InMemoryPsiFileManager::class);
        $fileManager->method('findPsiFile')->willReturn($psiFile);

        $propertyData = new PropertyData(
            name: 'name',
            className: 'App\MyClass',
            startPosition: 0,
            endPosition: 100,
            visibility: Visibility::Public,
            type: 'string',
            isStatic: false,
            isReadonly: true,
            isPromoted: false,
        );

        $indexLookup = IndexTestHelper::createLookup([
            'php.properties.fqn' => ['file:///test.php' => ['App\\MyClass::$name' => $propertyData]],
        ]);

        $context = new DocumentationContext(
            ProtocolFactory::textDocumentIdentifier(),
            ProtocolFactory::position(0, 100),
            MockHelper::mock(EditorInterface::class),
        );
        $consumer = new DocumentationConsumer();

        $contributor = new PropertyDocumentationContributor($indexLookup, $fileManager);
        $contributor->contribute($context, $consumer);

        $this->assertNotEmpty($consumer->results);
        $this->assertStringContainsString('App\MyClass', $consumer->results[0]);
        $this->assertStringContainsString('public', $consumer->results[0]);
        $this->assertStringContainsString('readonly', $consumer->results[0]);
        $this->assertStringContainsString('string', $consumer->results[0]);
        $this->assertStringContainsString('$name', $consumer->results[0]);
        $this->assertStringContainsString('```php', $consumer->results[0]);
    }
}
