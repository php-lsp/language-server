<?php
declare(strict_types=1);

namespace App\Module\PsiFile;

use Lsp\Extension\DocumentManager\Editor\Document\Document;
use PhpParser\Error;

class SourceFileRoot
{
    public function __construct(
        public array $children,
        public Document $document,
        /**
         * @var array<Error>
         */
        public array $errors,
    )
    {
    }
}
