<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Core\Contracts\Definition\DefinitionConsumer;
use App\Core\Contracts\Definition\DefinitionContext;
use App\Core\Contracts\Definition\DefinitionContributor;
use App\Module\PsiFile\PHPPsiFile;
use Lsp\Protocol\Type\Location;
use Lsp\Protocol\Type\Position;

final class DefinitionTestHelper
{
    /**
     * @return list<Location>
     */
    public static function contribute(
        DefinitionContributor $contributor,
        ?PHPPsiFile $psiFile = null,
        ?Position $position = null,
    ): array {
        $fileManager = MockHelper::mock(\App\Module\PsiFile\InMemoryPsiFileManager::class);
        $fileManager->method('findPsiFile')->willReturn($psiFile);

        $editor = MockHelper::mock(\Lsp\Extension\DocumentManager\Editor\EditorInterface::class);

        $context = new DefinitionContext(
            ProtocolFactory::textDocumentIdentifier(),
            $position ?? ProtocolFactory::position(),
            $editor,
        );
        $consumer = new DefinitionConsumer();
        $contributor->contribute($context, $consumer);

        return $consumer->results;
    }
}
