<?php

declare(strict_types=1);

namespace App\Tests\Unit\Module\TypeSystem;

use App\Module\PsiFile\InMemoryPsiFileManager;
use App\Module\TypeSystem\PHPStanBootstrap;
use App\Module\TypeSystem\TypeResolver;
use App\Tests\Support\MockHelper;
use App\Tests\Support\ProtocolFactory;
use App\Tests\Support\PsiFileFactory;
use App\Tests\TestCase;
use Lsp\Extension\DocumentManager\Editor\EditorInterface;
use PHPStan\Type\VerbosityLevel;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;
use Psr\Log\NullLogger;

#[Group('unit')]
final class TypeResolverTest extends TestCase
{
    private static ?PHPStanBootstrap $bootstrap = null;

    /** @var list<string> */
    private array $tempFiles = [];

    private static function bootstrap(): PHPStanBootstrap
    {
        if (self::$bootstrap !== null) {
            return self::$bootstrap;
        }

        $fs = MockHelper::mock(\Lsp\Workspace\File\FilesystemReader\FilesystemReaderInterface::class);
        $fs->method('count')->willReturn(0);

        $project = new \Lsp\Workspace\Project\Project(
            name: 'test',
            uri: new \Lsp\Workspace\Uri\Uri(sys_get_temp_dir(), null),
            filesystem: $fs,
        );

        $projectManager = new \App\Module\Workspace\ProjectManager();
        $projectManager->setProject($project);

        self::$bootstrap = new PHPStanBootstrap($projectManager);

        return self::$bootstrap;
    }

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $file) {
            @unlink($file);
        }
        $this->tempFiles = [];

        parent::tearDown();
    }

    private function createResolver(?InMemoryPsiFileManager $fileManager = null): TypeResolver
    {
        return new TypeResolver(
            self::bootstrap(),
            $fileManager ?? MockHelper::mock(InMemoryPsiFileManager::class),
            new NullLogger(),
        );
    }

    private function resolve(string $code, int $line, int $char, ?string $filePath = null): ?\App\Module\TypeSystem\TypeResult
    {
        static $counter = 0;
        $filePath ??= sys_get_temp_dir() . '/phpstan_lsp_test_' . (++$counter) . '_' . getmypid() . '.php';

        file_put_contents($filePath, $code);
        $this->tempFiles[] = $filePath;

        $uri = 'file://' . $filePath;

        self::bootstrap()->setAnalysedPaths([$filePath]);

        $psiFile = PsiFileFactory::fromCode($code, $uri);
        $fileManager = MockHelper::mock(InMemoryPsiFileManager::class);
        $fileManager->method('findPsiFile')->willReturn($psiFile);

        return $this->createResolver($fileManager)->resolveAtPosition(
            MockHelper::mock(EditorInterface::class),
            ProtocolFactory::textDocumentIdentifier($uri),
            ProtocolFactory::position($line, $char),
        );
    }

    #[TestDox('resolves type of new expression')]
    public function testResolvesNewExpression(): void
    {
        $result = $this->resolve('<?php
class Foo {}
$x = new Foo();
', 2, 8);

        $this->assertNotNull($result);
        $this->assertSame('Foo', $result->describeShort());
    }

    #[TestDox('resolves string type from variable')]
    public function testResolvesStringVariable(): void
    {
        $result = $this->resolve('<?php
$name = "hello";
echo $name;
', 2, 6);

        $this->assertNotNull($result);
        $this->assertStringContainsString('string', $result->describeShort());
    }

    #[TestDox('resolves method return type')]
    public function testResolvesMethodReturnType(): void
    {
        $result = $this->resolve('<?php
class User {
    public function getName(): string { return ""; }
}
$user = new User();
$name = $user->getName();
', 5, 15);

        $this->assertNotNull($result);
        $this->assertSame('string', $result->describeShort());
    }

    #[TestDox('resolves property type')]
    public function testResolvesPropertyType(): void
    {
        $result = $this->resolve('<?php
class Config {
    public int $port = 8080;
}
$cfg = new Config();
echo $cfg->port;
', 5, 10);

        $this->assertNotNull($result);
        $this->assertSame('int', $result->describeShort());
    }

    #[TestDox('resolves integer literal type')]
    public function testResolvesIntegerLiteral(): void
    {
        $result = $this->resolve('<?php
$x = 42;
', 1, 5);

        $this->assertNotNull($result);
        $this->assertStringContainsString('int', $result->describeShort());
    }

    #[TestDox('resolves array type')]
    public function testResolvesArrayType(): void
    {
        $result = $this->resolve('<?php
$items = [1, 2, 3];
', 1, 10);

        $this->assertNotNull($result);
        $this->assertStringContainsString('array', $result->describeShort());
    }

    #[TestDox('resolves static method call type')]
    public function testResolvesStaticMethodCall(): void
    {
        $result = $this->resolve('<?php
class Math {
    public static function add(int $a, int $b): int { return $a + $b; }
}
$result = Math::add(1, 2);
', 4, 16);

        $this->assertNotNull($result);
        $this->assertSame('int', $result->describeShort());
    }

    #[TestDox('resolves union return type from class method')]
    public function testResolvesUnionReturnType(): void
    {
        $result = $this->resolve('<?php
class Service {
    public function maybe(): string|false { return false; }
}
$svc = new Service();
$val = $svc->maybe();
', 5, 15);

        $this->assertNotNull($result);
        $desc = $result->describeShort();
        $this->assertStringContainsString('string', $desc);
        $this->assertStringContainsString('false', $desc);
    }

    #[TestDox('resolves nullable return type from class method')]
    public function testResolvesNullableType(): void
    {
        $result = $this->resolve('<?php
class Repo {
    public function find(): ?string { return null; }
}
$repo = new Repo();
$user = $repo->find();
', 5, 16);

        $this->assertNotNull($result);
        $desc = $result->describeShort();
        $this->assertStringContainsString('string', $desc);
        $this->assertStringContainsString('null', $desc);
    }

    #[TestDox('resolves chained method calls')]
    public function testResolvesChainedMethodCalls(): void
    {
        $result = $this->resolve('<?php
class Builder {
    public function where(): self { return $this; }
    public function get(): array { return []; }
}
$b = new Builder();
$items = $b->where()->get();
', 6, 22);

        $this->assertNotNull($result);
        $this->assertSame('array', $result->describeShort());
    }

    #[TestDox('returns null when file not found')]
    public function testReturnsNullWhenFileNotFound(): void
    {
        $fileManager = MockHelper::mock(InMemoryPsiFileManager::class);
        $fileManager->method('findPsiFile')->willReturn(null);

        $resolver = $this->createResolver($fileManager);
        $result = $resolver->resolveAtPosition(
            MockHelper::mock(EditorInterface::class),
            ProtocolFactory::textDocumentIdentifier(),
            ProtocolFactory::position(),
        );

        $this->assertNull($result);
    }

    #[TestDox('returns null when no node at position')]
    public function testReturnsNullWhenNoNodeAtPosition(): void
    {
        $result = $this->resolve('<?php
// just a comment
', 1, 5);

        $this->assertNull($result);
    }

    #[TestDox('resolves variable type at position')]
    public function testResolvesVariableType(): void
    {
        $filePath = sys_get_temp_dir() . '/phpstan_lsp_test_var_' . getmypid() . '.php';
        $code = '<?php
$count = 10;
echo $count;
';
        file_put_contents($filePath, $code);
        $this->tempFiles[] = $filePath;

        $uri = 'file://' . $filePath;

        self::bootstrap()->setAnalysedPaths([$filePath]);

        $psiFile = PsiFileFactory::fromCode($code, $uri);
        $fileManager = MockHelper::mock(InMemoryPsiFileManager::class);
        $fileManager->method('findPsiFile')->willReturn($psiFile);

        $type = $this->createResolver($fileManager)->resolveVariableAtPosition(
            MockHelper::mock(EditorInterface::class),
            ProtocolFactory::textDocumentIdentifier($uri),
            ProtocolFactory::position(2, 6),
            'count',
        );

        $this->assertNotNull($type);
        $this->assertStringContainsString('int', $type->describe(VerbosityLevel::typeOnly()));
    }

    #[TestDox('resolveNodeInFile resolves expression types')]
    public function testResolveNodeInFile(): void
    {
        $filePath = sys_get_temp_dir() . '/phpstan_lsp_test_node_' . getmypid() . '.php';
        $code = '<?php
$x = "hello";
';
        file_put_contents($filePath, $code);
        $this->tempFiles[] = $filePath;

        $uri = 'file://' . $filePath;

        self::bootstrap()->setAnalysedPaths([$filePath]);

        $psiFile = PsiFileFactory::fromCode($code, $uri);
        $nodes = $psiFile->findAtPosition(ProtocolFactory::position(1, 6));
        $targetNode = end($nodes);

        $this->assertNotFalse($targetNode);

        /** @var array<\PhpParser\Node\Stmt> $stmts */
        $stmts = $psiFile->ast->children;
        $result = $this->createResolver()->resolveNodeInFile($targetNode, $stmts, $filePath);

        $this->assertNotNull($result);
        $this->assertStringContainsString('string', $result->describeShort());
    }

    #[TestDox('resolves $this type inside class method')]
    public function testResolvesThisInsideMethod(): void
    {
        $result = $this->resolve('<?php
class Person {
    public string $name;
    public function getName(): string { return $this->name; }
}
', 3, 54);

        $this->assertNotNull($result);
        $this->assertSame('string', $result->describeShort());
    }
}
