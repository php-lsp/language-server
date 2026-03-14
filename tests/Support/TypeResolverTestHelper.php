<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Module\PsiFile\InMemoryPsiFileManager;
use App\Module\TypeSystem\PHPStanBootstrap;
use App\Module\TypeSystem\TypeResolver;
use App\Module\TypeSystem\TypeResult;
use Lsp\Extension\DocumentManager\Editor\EditorInterface;
use Psr\Log\NullLogger;

final class TypeResolverTestHelper
{
    private static ?PHPStanBootstrap $bootstrap = null;
    private static int $counter = 0;

    public static function bootstrap(): PHPStanBootstrap
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

    public static function resolve(string $code, int $line, int $char): ?TypeResult
    {
        $filePath = sys_get_temp_dir() . '/phpstan_lsp_test_' . (++self::$counter) . '.php';
        file_put_contents($filePath, $code);

        try {
            $uri = 'file://' . $filePath;

            self::bootstrap()->setAnalysedPaths([$filePath]);

            $psiFile = PsiFileFactory::fromCode($code, $uri);

            $fileManager = MockHelper::mock(InMemoryPsiFileManager::class);
            $fileManager->method('findPsiFile')->willReturn($psiFile);

            $resolver = new TypeResolver(
                self::bootstrap(),
                $fileManager,
                new NullLogger(),
            );

            return $resolver->resolveAtPosition(
                MockHelper::mock(EditorInterface::class),
                ProtocolFactory::textDocumentIdentifier($uri),
                ProtocolFactory::position($line, $char),
            );
        } finally {
            @unlink($filePath);
        }
    }
}
