<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Core\Contracts\Declaration\DeclarationConsumer;
use App\Core\Contracts\Declaration\DeclarationContext;
use App\Core\Contracts\Declaration\DeclarationContributor;
use App\Module\PsiFile\PHPPsiFile;
use Lsp\Protocol\Type\Location;
use Lsp\Protocol\Type\Position;
use Lsp\Protocol\Type\TextDocumentIdentifier;

final class DeclarationTestHelper
{
    /**
     * @return list<Location>
     */
    public static function contribute(
        DeclarationContributor $contributor,
        ?PHPPsiFile $psiFile = null,
        ?Position $position = null,
    ): array {
        $fileManager = MockHelper::mock(\App\Module\PsiFile\InMemoryPsiFileManager::class);
        $fileManager->method('findPsiFile')->willReturn($psiFile);

        $editor = MockHelper::mock(\Lsp\Extension\DocumentManager\Editor\EditorInterface::class);

        $context = new DeclarationContext(
            ProtocolFactory::textDocumentIdentifier(),
            $position ?? ProtocolFactory::position(),
            $editor,
        );
        $consumer = new DeclarationConsumer();
        $contributor->contribute($context, $consumer);

        return $consumer->results;
    }
}
