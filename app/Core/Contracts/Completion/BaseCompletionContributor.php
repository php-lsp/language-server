<?php
declare(strict_types=1);

namespace App\Core\Contracts\Completion;

use App\Module\PsiFile\InMemoryPsiFileManager;
use PhpParser\Node;

abstract class BaseCompletionContributor implements CompletionContributor
{
    public function __construct(
        private InMemoryPsiFileManager $fileManager
    )
    {
    }

    public function getElement(CompletionContext $context): ?Node
    {
        $editor = $context->editor;
        $file = $this->fileManager->findPsiFile($editor, $context->textDocumentIdentifier);
        if ($file === null) {
            return null;
        }

        return $file->findLastAtPosition($context->position);
    }
}
