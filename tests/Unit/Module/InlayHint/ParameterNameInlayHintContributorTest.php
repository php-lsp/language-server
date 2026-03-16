<?php

declare(strict_types=1);

namespace App\Tests\Unit\Module\InlayHint;

use App\Core\Contracts\InlayHint\InlayHintConsumer;
use App\Core\Contracts\InlayHint\InlayHintContext;
use App\Module\Indexing\Data\FunctionData;
use App\Module\Indexing\Data\MethodData;
use App\Module\Indexing\Data\ParameterData;
use App\Module\Indexing\Data\Visibility;
use App\Module\InlayHint\ParameterNameInlayHintContributor;
use App\Tests\Support\IndexTestHelper;
use App\Tests\Support\MockHelper;
use App\Tests\Support\ProtocolFactory;
use App\Tests\Support\PsiFileFactory;
use App\Tests\TestCase;
use Lsp\Protocol\Type\InlayHintKind;
use Lsp\Protocol\Type\Range;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;

#[Group('unit')]
final class ParameterNameInlayHintContributorTest extends TestCase
{
    private function createContext(
        string $code,
        ?Range $range = null,
        array $indexEntries = [],
    ): array {
        $psiFile = PsiFileFactory::fromCode($code);
        $fileManager = MockHelper::mock(\App\Module\PsiFile\InMemoryPsiFileManager::class);
        $fileManager->method('findPsiFile')->willReturn($psiFile);

        $lookup = IndexTestHelper::createLookup($indexEntries);
        $contributor = new ParameterNameInlayHintContributor($fileManager, $lookup);

        $editor = MockHelper::mock(\Lsp\Extension\DocumentManager\Editor\EditorInterface::class);
        $context = new InlayHintContext(
            ProtocolFactory::textDocumentIdentifier(),
            $range ?? ProtocolFactory::range(
                ProtocolFactory::position(0, 0),
                ProtocolFactory::position(100, 0),
            ),
            $editor,
            $fileManager,
        );
        $consumer = new InlayHintConsumer();

        return [$contributor, $context, $consumer];
    }

    #[TestDox('returns parameter name hints for function calls')]
    public function testFunctionCallParameterHints(): void
    {
        $code = '<?php myFunc("hello", 42);';

        [$contributor, $context, $consumer] = $this->createContext($code, indexEntries: [
            'php.functions.fqn' => [
                'file:///def.php' => [
                    'myFunc' => new FunctionData('myFunc', 0, 30, 'void', [
                        new ParameterData('$name', 'string', false, false, false, false),
                        new ParameterData('$age', 'int', false, false, false, false),
                    ]),
                ],
            ],
        ]);

        $contributor->contribute($context, $consumer);

        $this->assertCount(2, $consumer->results);
        $this->assertSame('$name:', $consumer->results[0]->label);
        $this->assertSame(InlayHintKind::Parameter, $consumer->results[0]->kind);
        $this->assertTrue($consumer->results[0]->paddingRight);
        $this->assertSame('$age:', $consumer->results[1]->label);
    }

    #[TestDox('returns parameter name hints for method calls')]
    public function testMethodCallParameterHints(): void
    {
        $code = <<<'PHP'
<?php
namespace App;

class Foo {
    public function bar(): void {
        $this->doSomething("hello", 42);
    }
    public function doSomething(string $name, int $age): void {}
}
PHP;

        [$contributor, $context, $consumer] = $this->createContext($code, indexEntries: [
            'php.classMethods.fqn' => [
                'file:///test.php' => [
                    'App\\Foo::doSomething' => new MethodData(
                        'doSomething',
                        'App\\Foo',
                        0,
                        100,
                        Visibility::Public,
                        false,
                        false,
                        'void',
                        [
                            new ParameterData('$name', 'string', false, false, false, false),
                            new ParameterData('$age', 'int', false, false, false, false),
                        ],
                    ),
                ],
            ],
        ]);

        $contributor->contribute($context, $consumer);

        $this->assertCount(2, $consumer->results);
        $this->assertSame('$name:', $consumer->results[0]->label);
        $this->assertSame('$age:', $consumer->results[1]->label);
    }

    #[TestDox('skips named arguments')]
    public function testSkipsNamedArguments(): void
    {
        $code = '<?php myFunc(name: "hello", age: 42);';

        [$contributor, $context, $consumer] = $this->createContext($code, indexEntries: [
            'php.functions.fqn' => [
                'file:///def.php' => [
                    'myFunc' => new FunctionData('myFunc', 0, 30, 'void', [
                        new ParameterData('$name', 'string', false, false, false, false),
                        new ParameterData('$age', 'int', false, false, false, false),
                    ]),
                ],
            ],
        ]);

        $contributor->contribute($context, $consumer);

        $this->assertEmpty($consumer->results);
    }

    #[TestDox('skips when argument variable name matches parameter name')]
    public function testSkipsWhenArgMatchesParamName(): void
    {
        $code = '<?php $name = "hello"; myFunc($name);';

        [$contributor, $context, $consumer] = $this->createContext($code, indexEntries: [
            'php.functions.fqn' => [
                'file:///def.php' => [
                    'myFunc' => new FunctionData('myFunc', 0, 30, 'void', [
                        new ParameterData('$name', 'string', false, false, false, false),
                    ]),
                ],
            ],
        ]);

        $contributor->contribute($context, $consumer);

        $this->assertEmpty($consumer->results);
    }

    #[TestDox('skips variadic parameters')]
    public function testSkipsVariadicParameters(): void
    {
        $code = '<?php myFunc("hello", "world");';

        [$contributor, $context, $consumer] = $this->createContext($code, indexEntries: [
            'php.functions.fqn' => [
                'file:///def.php' => [
                    'myFunc' => new FunctionData('myFunc', 0, 30, 'void', [
                        new ParameterData('$items', 'string', false, true, false, false),
                    ]),
                ],
            ],
        ]);

        $contributor->contribute($context, $consumer);

        $this->assertEmpty($consumer->results);
    }

    #[TestDox('returns empty when file is not found')]
    public function testReturnsEmptyWhenFileNotFound(): void
    {
        $fileManager = MockHelper::mock(\App\Module\PsiFile\InMemoryPsiFileManager::class);
        $fileManager->method('findPsiFile')->willReturn(null);

        $lookup = IndexTestHelper::createLookup();
        $contributor = new ParameterNameInlayHintContributor($fileManager, $lookup);

        $editor = MockHelper::mock(\Lsp\Extension\DocumentManager\Editor\EditorInterface::class);
        $context = new InlayHintContext(
            ProtocolFactory::textDocumentIdentifier(),
            ProtocolFactory::range(
                ProtocolFactory::position(0, 0),
                ProtocolFactory::position(100, 0),
            ),
            $editor,
            $fileManager,
        );
        $consumer = new InlayHintConsumer();

        $contributor->contribute($context, $consumer);

        $this->assertEmpty($consumer->results);
    }

    #[TestDox('returns empty when function is not in index')]
    public function testReturnsEmptyWhenFunctionNotInIndex(): void
    {
        $code = '<?php unknownFunc("hello");';

        [$contributor, $context, $consumer] = $this->createContext($code);

        $contributor->contribute($context, $consumer);

        $this->assertEmpty($consumer->results);
    }

    #[TestDox('handles static call parameter hints')]
    public function testStaticCallParameterHints(): void
    {
        $code = '<?php Foo::create("hello", 42);';

        [$contributor, $context, $consumer] = $this->createContext($code, indexEntries: [
            'php.classMethods.fqn' => [
                'file:///test.php' => [
                    'Foo::create' => new MethodData(
                        'create',
                        'Foo',
                        0,
                        100,
                        Visibility::Public,
                        true,
                        false,
                        'self',
                        [
                            new ParameterData('$name', 'string', false, false, false, false),
                            new ParameterData('$age', 'int', false, false, false, false),
                        ],
                    ),
                ],
            ],
        ]);

        $contributor->contribute($context, $consumer);

        $this->assertCount(2, $consumer->results);
        $this->assertSame('$name:', $consumer->results[0]->label);
        $this->assertSame('$age:', $consumer->results[1]->label);
    }

    #[TestDox('handles new expression parameter hints')]
    public function testNewExpressionParameterHints(): void
    {
        $code = '<?php new Foo("hello", 42);';

        [$contributor, $context, $consumer] = $this->createContext($code, indexEntries: [
            'php.classMethods.fqn' => [
                'file:///test.php' => [
                    'Foo::__construct' => new MethodData(
                        '__construct',
                        'Foo',
                        0,
                        100,
                        Visibility::Public,
                        false,
                        false,
                        null,
                        [
                            new ParameterData('$name', 'string', false, false, false, false),
                            new ParameterData('$age', 'int', false, false, false, false),
                        ],
                    ),
                ],
            ],
        ]);

        $contributor->contribute($context, $consumer);

        $this->assertCount(2, $consumer->results);
        $this->assertSame('$name:', $consumer->results[0]->label);
        $this->assertSame('$age:', $consumer->results[1]->label);
    }

    #[TestDox('skips dynamic function calls')]
    public function testSkipsDynamicFunctionCalls(): void
    {
        $code = '<?php $func = "myFunc"; $func("hello");';

        [$contributor, $context, $consumer] = $this->createContext($code, indexEntries: [
            'php.functions.fqn' => [
                'file:///def.php' => [
                    'myFunc' => new FunctionData('myFunc', 0, 30, 'void', [
                        new ParameterData('$name', 'string', false, false, false, false),
                    ]),
                ],
            ],
        ]);

        $contributor->contribute($context, $consumer);

        $this->assertEmpty($consumer->results);
    }

    #[TestDox('position is correct for inlay hint')]
    public function testPositionIsCorrect(): void
    {
        $code = '<?php myFunc("hello");';

        [$contributor, $context, $consumer] = $this->createContext($code, indexEntries: [
            'php.functions.fqn' => [
                'file:///def.php' => [
                    'myFunc' => new FunctionData('myFunc', 0, 30, 'void', [
                        new ParameterData('$name', 'string', false, false, false, false),
                    ]),
                ],
            ],
        ]);

        $contributor->contribute($context, $consumer);

        $this->assertCount(1, $consumer->results);
        // "hello" starts at position 13 (after '<?php myFunc(')
        $this->assertSame(0, $consumer->results[0]->position->line);
        $this->assertSame(13, $consumer->results[0]->position->character);
    }
}
