<?php

declare(strict_types=1);

namespace App\Controller\TextDocument;

use App\Controller\PHPStanAnalyzer;
use Lsp\Extension\DocumentManager\Editor\EditorInterface;
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
    public function __construct(
        private PHPStanAnalyzer $analyzer
    )
    {
    }

    public function __invoke(EditorInterface $editor, HoverParams $request): Hover
    {
        $type = $this->analyzer->getTypeAtPosition(
            $editor,
            $request->textDocument,
            $request->position
        );

        if ($type === null) {
            return new Hover(contents: new MarkupContent(
                kind: MarkupKind::Markdown,
                value: 'No type information available'
            ));
        }
        return new Hover(
            contents: new MarkupContent(
                kind: MarkupKind::Markdown,
                value: $this->formatType($type)
            )
        );
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

    private function formatType(\PHPStan\Type\Type $type): string
    {
        return sprintf(
            <<<MARKDOWN
            PHPStan Type:
            %s (%s)
            MARKDOWN,
            $type->describe(\PHPStan\Type\VerbosityLevel::precise()),
            $type->describe(\PHPStan\Type\VerbosityLevel::typeOnly())
        );
    }
}
