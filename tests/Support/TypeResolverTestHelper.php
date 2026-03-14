<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Module\PsiFile\InMemoryPsiFileManager;
use App\Module\TypeSystem\PHPStanBootstrap;
use App\Module\TypeSystem\TypeResolver;
use App\Module\TypeSystem\TypeResult;
use App\Module\Workspace\ProjectManager;
use Lsp\Extension\DocumentManager\Editor\EditorInterface;
use Lsp\Workspace\File\FilesystemReader\FilesystemReaderInterface;
use Lsp\Workspace\Project\Project;
use Lsp\Workspace\Uri\Uri;
use Psr\Log\NullLogger;

final class TypeResolverTestHelper
{
    private static ?PHPStanBootstrap $bootstrap = null;

    public static function bootstrap(): PHPStanBootstrap
    {
        if (self::$bootstrap !== null) {
            return self::$bootstrap;
        }

        $fs = MockHelper::mock(FilesystemReaderInterface::class);
        $fs->method('count')->willReturn(0);

        $project = new Project(
            name: 'test',
            uri: new Uri(sys_get_temp_dir(), null),
            filesystem: $fs,
        );

        $projectManager = new ProjectManager();
        $projectManager->setProject($project);

        self::$bootstrap = new PHPStanBootstrap($projectManager);

        return self::$bootstrap;
    }

    public static function createResolver(?InMemoryPsiFileManager $fileManager = null): TypeResolver
    {
        return new TypeResolver(
            self::bootstrap(),
            $fileManager ?? MockHelper::mock(InMemoryPsiFileManager::class),
            new NullLogger(),
        );
    }

    public static function resolveInCode(string $code, int $line, int $char, string $filePath): ?TypeResult
    {
        file_put_contents($filePath, $code);

        $uri = 'file://' . $filePath;

        self::bootstrap()->setAnalysedPaths([$filePath]);

        $psiFile = PsiFileFactory::fromCode($code, $uri);
        $fileManager = MockHelper::mock(InMemoryPsiFileManager::class);
        $fileManager->method('findPsiFile')->willReturn($psiFile);

        return self::createResolver($fileManager)->resolveAtPosition(
            MockHelper::mock(EditorInterface::class),
            ProtocolFactory::textDocumentIdentifier($uri),
            ProtocolFactory::position($line, $char),
        );
    }
}
