<?php

declare(strict_types=1);

namespace App\Core\Contracts\PsiFile;

use Lsp\Extension\DocumentManager\Editor\Document\Document;
use Lsp\Protocol\Type\Position;
use PhpParser\Node;

interface PsiFileInterface
{
    /**
     * @return array<Node>
     */
    public function findAtPosition(Position|int $position): array;

    public function findLastAtPosition(Position $position): ?Node;

    public function getDocument(): Document;

    /**
     * @return array<Node>
     */
    public function getChildren(): array;
}
