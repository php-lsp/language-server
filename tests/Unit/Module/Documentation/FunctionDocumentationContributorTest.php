<?php

declare(strict_types=1);

namespace App\Tests\Unit\Module\Documentation;

use App\Core\Contracts\Documentation\DocumentationConsumer;
use App\Core\Contracts\Documentation\DocumentationContext;
use App\Module\Documentation\FunctionDocumentationContributor;
use App\Module\Indexing\Storage\IndexData\FunctionData;
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
final class FunctionDocumentationContributorTest extends TestCase
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

        $contributor = new FunctionDocumentationContributor($indexLookup, $fileManager);
        $contributor->contribute($context, $consumer);

        $this->assertEmpty($consumer->results);
    }

    #[TestDox('returns documentation for a matching function call')]
    public function testReturnsDocumentationForMatchingFunction(): void
    {
        $code = '<?php array_map($fn, $arr);';
        $psiFile = PsiFileFactory::fromCode($code);

        $fileManager = MockHelper::mock(InMemoryPsiFileManager::class);
        $fileManager->method('findPsiFile')->willReturn($psiFile);

        $functionData = new FunctionData(
            fqn: 'array_map',
            startPosition: 0,
            endPosition: 100,
            parameters: [
                new ParameterData(
                    name: 'callback',
                    type: '?callable',
                    hasDefault: false,
                    isVariadic: false,
                    isPromoted: false,
                ),
                new ParameterData(
                    name: 'array',
                    type: 'array',
                    hasDefault: false,
                    isVariadic: true,
                    isPromoted: false,
                ),
            ],
            returnType: 'array',
        );

        $indexLookup = IndexTestHelper::createLookup([
            'php.functions.fqn' => ['file:///test.php' => [$functionData]],
        ]);

        $context = new DocumentationContext(
            ProtocolFactory::textDocumentIdentifier(),
            ProtocolFactory::position(0, 10),
            MockHelper::mock(EditorInterface::class),
        );
        $consumer = new DocumentationConsumer();

        $contributor = new FunctionDocumentationContributor($indexLookup, $fileManager);
        $contributor->contribute($context, $consumer);

        $this->assertNotEmpty($consumer->results);
        $this->assertStringContainsString('function array_map', $consumer->results[0]);
        $this->assertStringContainsString('?callable $callback', $consumer->results[0]);
        $this->assertStringContainsString('...', $consumer->results[0]);
        $this->assertStringContainsString(': array', $consumer->results[0]);
        $this->assertStringContainsString('```php', $consumer->results[0]);
    }
}
