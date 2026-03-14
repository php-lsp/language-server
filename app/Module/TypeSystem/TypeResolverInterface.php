<?php

declare(strict_types=1);

namespace App\Module\TypeSystem;

use Lsp\Extension\DocumentManager\Editor\EditorInterface;
use Lsp\Protocol\Type\Position;
use Lsp\Protocol\Type\TextDocumentIdentifier;
use PHPStan\Type\Type;

interface TypeResolverInterface
{
    public function resolveAtPosition(
        EditorInterface $editor,
        TextDocumentIdentifier $textDocumentIdentifier,
        Position $position,
    ): ?TypeResult;

    public function resolveVariableAtPosition(
        EditorInterface $editor,
        TextDocumentIdentifier $textDocumentIdentifier,
        Position $position,
        string $variableName,
    ): ?Type;
}
