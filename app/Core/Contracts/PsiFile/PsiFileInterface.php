<?php

declare(strict_types=1);

namespace App\Core\Contracts\PsiFile;

use Lsp\Protocol\Type\Position;
use PhpParser\Node;

interface PsiFileInterface
{
    /**
     * @return array<Node>
     */
    public function findAtPosition(Position|int $position): array;

    public function findLastAtPosition(Position $position): ?Node;
}
