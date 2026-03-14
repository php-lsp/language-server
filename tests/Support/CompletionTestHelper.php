<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Core\Contracts\Completion\CompletionConsumer;
use App\Core\Contracts\Completion\CompletionContext;
use App\Core\Contracts\Completion\CompletionContributor;
use App\Module\PsiFile\InMemoryPsiFileManager;
use App\Module\PsiFile\PHPPsiFile;
use Lsp\Extension\DocumentManager\Editor\EditorInterface;
use Lsp\Protocol\Type\CompletionItem;
use Lsp\Protocol\Type\Position;
use Lsp\Protocol\Type\TextDocumentIdentifier;
use PHPUnit\Framework\MockObject\Generator\Generator;

final class CompletionTestHelper
{
    private static ?Generator $mockGenerator = null;

    private static function generator(): Generator
    {
        return self::$mockGenerator ??= new Generator();
    }

    public static function createContext(
        ?TextDocumentIdentifier $textDoc = null,
        ?Position $position = null,
        ?PHPPsiFile $psiFile = null,
    ): CompletionContext {
        $fileManager = self::generator()->testDouble(
            InMemoryPsiFileManager::class,
            mockObject: true,
            markAsMockObject: true,
            callOriginalConstructor: false,
        );
        $fileManager->method('findPsiFile')->willReturn($psiFile);

        $editor = self::generator()->testDouble(
            EditorInterface::class,
            mockObject: true,
            markAsMockObject: true,
        );

        return new CompletionContext(
            $textDoc ?? ProtocolFactory::textDocumentIdentifier(),
            $position ?? ProtocolFactory::position(),
            $editor,
            $fileManager,
        );
    }

    /**
     * Run a contributor and return the collected results.
     *
     * @return list<CompletionItem>
     */
    public static function contribute(
        CompletionContributor $contributor,
        ?PHPPsiFile $psiFile = null,
        ?Position $position = null,
    ): array {
        $context = self::createContext(psiFile: $psiFile, position: $position);
        $consumer = new CompletionConsumer();
        $contributor->contribute($context, $consumer);

        return $consumer->results;
    }
}
