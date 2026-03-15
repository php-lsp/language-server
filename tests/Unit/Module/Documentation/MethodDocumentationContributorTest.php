<?php

declare(strict_types=1);

namespace App\Tests\Unit\Module\Documentation;

use App\Core\Contracts\Documentation\DocumentationConsumer;
use App\Core\Contracts\Documentation\DocumentationContext;
use App\Module\Documentation\MethodDocumentationContributor;
use App\Module\Indexing\Storage\IndexData\MethodData;
use App\Module\Indexing\Storage\IndexData\ParameterData;
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
final class MethodDocumentationContributorTest extends TestCase
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

        $contributor = new MethodDocumentationContributor($indexLookup, $fileManager);
        $contributor->contribute($context, $consumer);

        $this->assertEmpty($consumer->results);
    }

    #[TestDox('returns documentation for a matching static method call')]
    public function testReturnsDocumentationForMatchingStaticMethod(): void
    {
        $code = '<?php Foo::bar();';
        $psiFile = PsiFileFactory::fromCode($code);

        $fileManager = MockHelper::mock(InMemoryPsiFileManager::class);
        $fileManager->method('findPsiFile')->willReturn($psiFile);

        $methodData = new MethodData(
            name: 'bar',
            className: 'Foo',
            startPosition: 0,
            endPosition: 100,
            visibility: 'public',
            isStatic: true,
            isAbstract: false,
            parameters: [
                new ParameterData(
                    name: 'value',
                    type: 'string',
                    hasDefault: false,
                    isVariadic: false,
                    isPromoted: false,
                ),
            ],
            returnType: 'void',
        );

        $indexLookup = IndexTestHelper::createLookup([
            'php.classMethods.fqn' => ['file:///test.php' => [$methodData]],
        ]);

        $context = new DocumentationContext(
            ProtocolFactory::textDocumentIdentifier(),
            ProtocolFactory::position(0, 12),
            MockHelper::mock(EditorInterface::class),
        );
        $consumer = new DocumentationConsumer();

        $contributor = new MethodDocumentationContributor($indexLookup, $fileManager);
        $contributor->contribute($context, $consumer);

        $this->assertNotEmpty($consumer->results);
        $this->assertStringContainsString('Foo', $consumer->results[0]);
        $this->assertStringContainsString('public', $consumer->results[0]);
        $this->assertStringContainsString('static', $consumer->results[0]);
        $this->assertStringContainsString('function bar', $consumer->results[0]);
        $this->assertStringContainsString('string $value', $consumer->results[0]);
        $this->assertStringContainsString(': void', $consumer->results[0]);
        $this->assertStringContainsString('```php', $consumer->results[0]);
    }
}
