<?php

declare(strict_types=1);

namespace App\Controller\TextDocument;

use App\Module\Document\DocumentLoaderInterface;
use App\Module\PsiFile\PHPPsiFileParser;
use Lsp\Extension\DocumentManager\Editor\Document\Document;
use Lsp\Extension\DocumentManager\Editor\EditorInterface;
use Lsp\Kernel\Attribute\AsController;
use Lsp\Protocol\Type\Diagnostic;
use Lsp\Protocol\Type\DiagnosticSeverity;
use Lsp\Protocol\Type\DocumentDiagnosticParams;
use Lsp\Protocol\Type\DocumentDiagnosticReportKind;
use Lsp\Protocol\Type\Position;
use Lsp\Protocol\Type\Range;
use Lsp\Protocol\Type\RelatedFullDocumentDiagnosticReport;
use Lsp\Protocol\Type\TextDocumentIdentifier;
use Lsp\Router\Attribute\Route;
use PhpParser\Error;

#[AsController, Route('textDocument/diagnostic')]
final class DiagnosticController
{
    public function __construct(
        private PHPPsiFileParser $fileParser,
        private DocumentLoaderInterface $documentLoader,
    ) {}

    public function __invoke(
        EditorInterface $editor,
        DocumentDiagnosticParams $params,
    ): RelatedFullDocumentDiagnosticReport {
        $identifier = $params->textDocument;
        $document = $this->getDocument($editor, $identifier);

        $root = $this->fileParser->parse($document);

        $result = array_map(
            static fn(Error $error) => new Diagnostic(
                range: new Range(
                    start: new Position($error->getStartLine() - 1, $error->getStartColumn($document->getContents())),
                    end: new Position($error->getEndLine() - 1, $error->getEndColumn($document->getContents())),
                ),
                message: $error->getMessage(),
                severity: DiagnosticSeverity::Error,
            ),
            $root->errors,
        );

        return new RelatedFullDocumentDiagnosticReport(
            kind: DocumentDiagnosticReportKind::Full->value,
            items: $result,
        );
    }

    private function getDocument(EditorInterface $editor, TextDocumentIdentifier $identifier): ?Document
    {
        $document = $editor->findByUriString($identifier->uri);
        if ($document === null) {
            $document = $this->documentLoader->load($identifier);
            $editor->open($document);
        }

        return $document;
    }
}
