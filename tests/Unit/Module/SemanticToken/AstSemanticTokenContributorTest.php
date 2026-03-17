<?php

declare(strict_types=1);

namespace App\Tests\Unit\Module\SemanticToken;

use App\Core\Contracts\SemanticToken\SemanticTokenConsumer;
use App\Core\Contracts\SemanticToken\SemanticTokenContext;
use App\Module\PsiFile\InMemoryPsiFileManager;
use App\Module\SemanticToken\AstSemanticTokenContributor;
use App\Module\SemanticToken\RawSemanticToken;
use App\Module\SemanticToken\SemanticTokenLegend;
use App\Tests\Support\MockHelper;
use App\Tests\Support\ProtocolFactory;
use App\Tests\Support\PsiFileFactory;
use App\Tests\TestCase;
use Lsp\Extension\DocumentManager\Editor\EditorInterface;
use Lsp\Protocol\Type\SemanticTokenModifiers;
use Lsp\Protocol\Type\SemanticTokenTypes;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;

#[Group('unit')]
final class AstSemanticTokenContributorTest extends TestCase
{
    #[TestDox('returns empty when file not found')]
    public function testReturnsEmptyWhenNoFile(): void
    {
        $fileManager = MockHelper::mock(InMemoryPsiFileManager::class);
        $fileManager->method('findPsiFile')->willReturn(null);

        $tokens = $this->getTokens('<?php ', $fileManager);

        $this->assertEmpty($tokens);
    }

    #[TestDox('classifies class declaration')]
    public function testClassDeclaration(): void
    {
        $code = <<<'PHP'
<?php
class Foo {}
PHP;
        $tokens = $this->getTokens($code);

        $classToken = $this->findToken($tokens, 1, SemanticTokenTypes::ClassType);
        $this->assertNotNull($classToken, 'Class name should produce a class token');
        $this->assertSame(3, $classToken->length);
        $this->assertHasModifier($classToken, SemanticTokenModifiers::Declaration);
    }

    #[TestDox('classifies abstract class with abstract modifier')]
    public function testAbstractClass(): void
    {
        $code = <<<'PHP'
<?php
abstract class Foo {}
PHP;
        $tokens = $this->getTokens($code);

        $classToken = $this->findToken($tokens, 1, SemanticTokenTypes::ClassType);
        $this->assertNotNull($classToken, 'Abstract class should produce a class token');
        $this->assertHasModifier($classToken, SemanticTokenModifiers::Abstract);
        $this->assertHasModifier($classToken, SemanticTokenModifiers::Declaration);
    }

    #[TestDox('classifies interface declaration')]
    public function testInterfaceDeclaration(): void
    {
        $code = <<<'PHP'
<?php
interface FooInterface {}
PHP;
        $tokens = $this->getTokens($code);

        $token = $this->findToken($tokens, 1, SemanticTokenTypes::InterfaceType);
        $this->assertNotNull($token, 'Interface name should produce an interface token');
        $this->assertHasModifier($token, SemanticTokenModifiers::Declaration);
    }

    #[TestDox('classifies trait declaration')]
    public function testTraitDeclaration(): void
    {
        $code = <<<'PHP'
<?php
trait FooTrait {}
PHP;
        $tokens = $this->getTokens($code);

        // Traits use ClassType
        $token = $this->findToken($tokens, 1, SemanticTokenTypes::ClassType);
        $this->assertNotNull($token, 'Trait name should produce a class token');
        $this->assertHasModifier($token, SemanticTokenModifiers::Declaration);
    }

    #[TestDox('classifies enum declaration')]
    public function testEnumDeclaration(): void
    {
        $code = <<<'PHP'
<?php
enum Status {}
PHP;
        $tokens = $this->getTokens($code);

        $token = $this->findToken($tokens, 1, SemanticTokenTypes::EnumType);
        $this->assertNotNull($token, 'Enum name should produce an enum token');
        $this->assertHasModifier($token, SemanticTokenModifiers::Declaration);
    }

    #[TestDox('classifies enum case')]
    public function testEnumCase(): void
    {
        $code = <<<'PHP'
<?php
enum Status {
    case Active;
}
PHP;
        $tokens = $this->getTokens($code);

        $token = $this->findToken($tokens, 2, SemanticTokenTypes::EnumMemberType);
        $this->assertNotNull($token, 'Enum case should produce an enumMember token');
        $this->assertHasModifier($token, SemanticTokenModifiers::Declaration);
    }

    #[TestDox('classifies function declaration')]
    public function testFunctionDeclaration(): void
    {
        $code = <<<'PHP'
<?php
function hello() {}
PHP;
        $tokens = $this->getTokens($code);

        $token = $this->findToken($tokens, 1, SemanticTokenTypes::FunctionType);
        $this->assertNotNull($token, 'Function name should produce a function token');
        $this->assertHasModifier($token, SemanticTokenModifiers::Declaration);
    }

    #[TestDox('classifies method declaration')]
    public function testMethodDeclaration(): void
    {
        $code = <<<'PHP'
<?php
class Foo {
    public function bar() {}
}
PHP;
        $tokens = $this->getTokens($code);

        $token = $this->findToken($tokens, 2, SemanticTokenTypes::MethodType);
        $this->assertNotNull($token, 'Method name should produce a method token');
        $this->assertHasModifier($token, SemanticTokenModifiers::Declaration);
    }

    #[TestDox('classifies static method with static modifier')]
    public function testStaticMethod(): void
    {
        $code = <<<'PHP'
<?php
class Foo {
    public static function bar() {}
}
PHP;
        $tokens = $this->getTokens($code);

        $token = $this->findToken($tokens, 2, SemanticTokenTypes::MethodType);
        $this->assertNotNull($token);
        $this->assertHasModifier($token, SemanticTokenModifiers::Static);
        $this->assertHasModifier($token, SemanticTokenModifiers::Declaration);
    }

    #[TestDox('classifies function parameters')]
    public function testFunctionParameters(): void
    {
        $code = <<<'PHP'
<?php
function greet($name) {}
PHP;
        $tokens = $this->getTokens($code);

        $token = $this->findToken($tokens, 1, SemanticTokenTypes::ParameterType);
        $this->assertNotNull($token, 'Parameter should produce a parameter token');
        $this->assertHasModifier($token, SemanticTokenModifiers::Declaration);
    }

    #[TestDox('classifies variable usage')]
    public function testVariableUsage(): void
    {
        $code = <<<'PHP'
<?php
$x = 1;
echo $x;
PHP;
        $tokens = $this->getTokens($code);

        $variableTokens = $this->findAllTokens($tokens, SemanticTokenTypes::VariableType);
        $this->assertGreaterThanOrEqual(1, count($variableTokens), 'Variables should produce variable tokens');
    }

    #[TestDox('classifies variable assignment with modification modifier')]
    public function testVariableAssignment(): void
    {
        $code = <<<'PHP'
<?php
$x = 1;
PHP;
        $tokens = $this->getTokens($code);

        $varToken = $this->findToken($tokens, 1, SemanticTokenTypes::VariableType);
        $this->assertNotNull($varToken, 'Variable on assignment LHS should produce a variable token');
        $this->assertHasModifier($varToken, SemanticTokenModifiers::Modification);
    }

    #[TestDox('classifies property declaration')]
    public function testPropertyDeclaration(): void
    {
        $code = <<<'PHP'
<?php
class Foo {
    public string $name;
}
PHP;
        $tokens = $this->getTokens($code);

        $token = $this->findToken($tokens, 2, SemanticTokenTypes::PropertyType);
        $this->assertNotNull($token, 'Property should produce a property token');
        $this->assertHasModifier($token, SemanticTokenModifiers::Declaration);
    }

    #[TestDox('classifies readonly property with readonly modifier')]
    public function testReadonlyProperty(): void
    {
        $code = <<<'PHP'
<?php
class Foo {
    public readonly string $name;
}
PHP;
        $tokens = $this->getTokens($code);

        $token = $this->findToken($tokens, 2, SemanticTokenTypes::PropertyType);
        $this->assertNotNull($token);
        $this->assertHasModifier($token, SemanticTokenModifiers::Readonly);
    }

    #[TestDox('classifies function call')]
    public function testFunctionCall(): void
    {
        $code = <<<'PHP'
<?php
strlen("hello");
PHP;
        $tokens = $this->getTokens($code);

        $token = $this->findToken($tokens, 1, SemanticTokenTypes::FunctionType);
        $this->assertNotNull($token, 'Function call should produce a function token');
        $this->assertNotHasModifier($token, SemanticTokenModifiers::Declaration);
    }

    #[TestDox('classifies method call')]
    public function testMethodCall(): void
    {
        $code = <<<'PHP'
<?php
$obj->doSomething();
PHP;
        $tokens = $this->getTokens($code);

        $token = $this->findToken($tokens, 1, SemanticTokenTypes::MethodType);
        $this->assertNotNull($token, 'Method call should produce a method token');
        $this->assertNotHasModifier($token, SemanticTokenModifiers::Declaration);
    }

    #[TestDox('classifies static call with class and method')]
    public function testStaticCall(): void
    {
        $code = <<<'PHP'
<?php
Foo::bar();
PHP;
        $tokens = $this->getTokens($code);

        $classToken = $this->findToken($tokens, 1, SemanticTokenTypes::ClassType);
        $this->assertNotNull($classToken, 'Static call class should produce a class token');

        $methodToken = $this->findToken($tokens, 1, SemanticTokenTypes::MethodType);
        $this->assertNotNull($methodToken, 'Static call method should produce a method token');
        $this->assertHasModifier($methodToken, SemanticTokenModifiers::Static);
    }

    #[TestDox('classifies property fetch')]
    public function testPropertyFetch(): void
    {
        $code = <<<'PHP'
<?php
$obj->name;
PHP;
        $tokens = $this->getTokens($code);

        $token = $this->findToken($tokens, 1, SemanticTokenTypes::PropertyType);
        $this->assertNotNull($token, 'Property fetch should produce a property token');
    }

    #[TestDox('classifies new expression')]
    public function testNewExpression(): void
    {
        $code = <<<'PHP'
<?php
new Foo();
PHP;
        $tokens = $this->getTokens($code);

        $token = $this->findToken($tokens, 1, SemanticTokenTypes::ClassType);
        $this->assertNotNull($token, 'new expression should produce a class token');
    }

    #[TestDox('classifies attribute as decorator')]
    public function testAttribute(): void
    {
        $code = <<<'PHP'
<?php
#[Override]
function foo() {}
PHP;
        $tokens = $this->getTokens($code);

        $token = $this->findToken($tokens, 1, SemanticTokenTypes::DecoratorType);
        $this->assertNotNull($token, 'Attribute should produce a decorator token');
    }

    #[TestDox('classifies namespace declaration')]
    public function testNamespaceDeclaration(): void
    {
        $code = <<<'PHP'
<?php
namespace App\Module;
PHP;
        $tokens = $this->getTokens($code);

        $token = $this->findToken($tokens, 1, SemanticTokenTypes::NamespaceType);
        $this->assertNotNull($token, 'Namespace should produce a namespace token');
        $this->assertHasModifier($token, SemanticTokenModifiers::Declaration);
    }

    #[TestDox('classifies class constant fetch')]
    public function testClassConstantFetch(): void
    {
        $code = <<<'PHP'
<?php
Foo::BAR;
PHP;
        $tokens = $this->getTokens($code);

        $classToken = $this->findToken($tokens, 1, SemanticTokenTypes::ClassType);
        $this->assertNotNull($classToken, 'Class constant fetch class should produce a class token');

        $constToken = $this->findToken($tokens, 1, SemanticTokenTypes::PropertyType);
        $this->assertNotNull($constToken, 'Class constant fetch should produce a property token');
        $this->assertHasModifier($constToken, SemanticTokenModifiers::Readonly);
    }

    #[TestDox('classifies class extends and implements')]
    public function testClassExtendsImplements(): void
    {
        $code = <<<'PHP'
<?php
class Foo extends Bar implements Baz {}
PHP;
        $tokens = $this->getTokens($code);

        // Should have class tokens for Foo, Bar (as class), and Baz (as interface)
        $classTokens = $this->findAllTokens($tokens, SemanticTokenTypes::ClassType);
        $this->assertGreaterThanOrEqual(2, count($classTokens), 'Should have class tokens for Foo and Bar');

        $interfaceTokens = $this->findAllTokens($tokens, SemanticTokenTypes::InterfaceType);
        $this->assertCount(1, $interfaceTokens, 'Should have interface token for Baz');
    }

    #[TestDox('classifies type hints in parameters')]
    public function testTypeHintInParameter(): void
    {
        $code = <<<'PHP'
<?php
function foo(UserService $service) {}
PHP;
        $tokens = $this->getTokens($code);

        $typeToken = $this->findToken($tokens, 1, SemanticTokenTypes::TypeType);
        $this->assertNotNull($typeToken, 'Type hint should produce a type token');
    }

    #[TestDox('skips built-in types in type hints')]
    public function testSkipsBuiltInTypes(): void
    {
        $code = <<<'PHP'
<?php
function foo(string $name, int $age) {}
PHP;
        $tokens = $this->getTokens($code);

        // Built-in types (string, int) should not produce type tokens
        $typeTokens = $this->findAllTokens($tokens, SemanticTokenTypes::TypeType);
        $this->assertEmpty($typeTokens, 'Built-in types should not produce type tokens');
    }

    /**
     * @param \PHPUnit\Framework\MockObject\MockObject|null $fileManager
     *
     * @return list<RawSemanticToken>
     */
    private function getTokens(string $code, $fileManager = null): array
    {
        if ($fileManager === null) {
            $psiFile = PsiFileFactory::fromCode($code);
            $fileManager = MockHelper::mock(InMemoryPsiFileManager::class);
            $fileManager->method('findPsiFile')->willReturn($psiFile);
        }

        $contributor = new AstSemanticTokenContributor();
        $editor = MockHelper::mock(EditorInterface::class);
        $context = new SemanticTokenContext(
            ProtocolFactory::textDocumentIdentifier(),
            $editor,
            $fileManager,
        );
        $consumer = new SemanticTokenConsumer();
        $contributor->contribute($context, $consumer);

        return $consumer->tokens;
    }

    private function findToken(array $tokens, int $line, SemanticTokenTypes $type): ?RawSemanticToken
    {
        $typeIndex = SemanticTokenLegend::typeIndex($type);
        foreach ($tokens as $token) {
            if ($token->line === $line && $token->type === $typeIndex) {
                return $token;
            }
        }

        return null;
    }

    /**
     * @return list<RawSemanticToken>
     */
    private function findAllTokens(array $tokens, SemanticTokenTypes $type): array
    {
        $typeIndex = SemanticTokenLegend::typeIndex($type);
        $result = [];
        foreach ($tokens as $token) {
            if ($token->type === $typeIndex) {
                $result[] = $token;
            }
        }

        return $result;
    }

    private function assertHasModifier(RawSemanticToken $token, SemanticTokenModifiers $modifier): void
    {
        $bit = SemanticTokenLegend::modifierBit($modifier);
        $this->assertTrue(
            ($token->modifiers & $bit) !== 0,
            sprintf('Token should have modifier %s (bit %d), got modifiers %d', $modifier->value, $bit, $token->modifiers),
        );
    }

    private function assertNotHasModifier(RawSemanticToken $token, SemanticTokenModifiers $modifier): void
    {
        $bit = SemanticTokenLegend::modifierBit($modifier);
        $this->assertTrue(
            ($token->modifiers & $bit) === 0,
            sprintf('Token should NOT have modifier %s', $modifier->value),
        );
    }
}
