<?php

declare(strict_types=1);

namespace App\Controller\TextDocument;

use Lsp\Kernel\Attribute\AsController;
use Lsp\Protocol\Type\Hover;
use Lsp\Protocol\Type\HoverParams;
use Lsp\Protocol\Type\MarkupContent;
use Lsp\Protocol\Type\MarkupKind;
use Lsp\Protocol\Type\Position;
use Lsp\Protocol\Type\Range;
use Lsp\Router\Attribute\Route;

#[AsController, Route('textDocument/hover')]
final class HoverController
{
    public function __invoke(HoverParams $request): Hover
    {
        var_dump($request);

        return new Hover(
            contents: new MarkupContent(
                kind: MarkupKind::Markdown,
                value: <<<Markdown
# Big big header

Markdown
            ),
            range: new Range(
                start: $request->position,
                end: new Position(
                    line: $request->position->line + 2,
                    character: 0,
                ),
            ),
        );
    }
}
