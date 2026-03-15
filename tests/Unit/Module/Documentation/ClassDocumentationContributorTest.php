<?php

declare(strict_types=1);

namespace App\Tests\Unit\Module\Documentation;

use App\Core\Contracts\Documentation\DocumentationConsumer;
use App\Core\Contracts\Documentation\DocumentationContext;
use App\Module\Documentation\ClassDocumentationContributor;
use App\Module\Indexing\Storage\IndexData\ClassData;
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
final class ClassDocumentationContributorTest extends TestCase
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

        $contributor = new ClassDocumentationContributor($indexLookup, $fileManager);
        $contributor->contribute($context, $consumer);

        $this->assertEmpty($consumer->results);
    }

    #[TestDox('returns documentation for a matching class')]
    public function testReturnsDocumentationForMatchingClass(): void
    {
        $code = '<?php new \App\MyClass();';
        $psiFile = PsiFileFactory::fromCode($code);

        $fileManager = MockHelper::mock(InMemoryPsiFileManager::class);
        $fileManager->method('findPsiFile')->willReturn($psiFile);

        $classData = new ClassData(
            fqn: 'App\MyClass',
            startPosition: 0,
            endPosition: 100,
            extends: 'App\BaseClass',
            implements: ['App\FooInterface'],
            isAbstract: false,
            isFinal: true,
        );

        $indexLookup = IndexTestHelper::createLookup([
            'php.classes.fqn' => ['file:///test.php' => [$classData]],
        ]);

        $context = new DocumentationContext(
            ProtocolFactory::textDocumentIdentifier(),
            ProtocolFactory::position(0, 12),
            MockHelper::mock(EditorInterface::class),
        );
        $consumer = new DocumentationConsumer();

        $contributor = new ClassDocumentationContributor($indexLookup, $fileManager);
        $contributor->contribute($context, $consumer);

        $this->assertNotEmpty($consumer->results);
        $this->assertStringContainsString('final', $consumer->results[0]);
        $this->assertStringContainsString('class', $consumer->results[0]);
        $this->assertStringContainsString('App\MyClass', $consumer->results[0]);
        $this->assertStringContainsString('extends App\BaseClass', $consumer->results[0]);
        $this->assertStringContainsString('implements App\FooInterface', $consumer->results[0]);
        $this->assertStringContainsString('```php', $consumer->results[0]);
    }
}
