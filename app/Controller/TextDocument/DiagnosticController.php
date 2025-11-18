<?php

declare(strict_types=1);

namespace App\Controller\TextDocument;

use App\Module\PsiFile\PHPPsiFileParser;
use Lsp\Contracts\Rpc\Message\MessageInterface;
use Lsp\Contracts\Rpc\Message\NotificationInterface;
use Lsp\Contracts\Server\ConnectionInterface;
use Lsp\Dispatcher\Result\Provider\ResultProviderInterface;
use Lsp\Extension\DocumentManager\Editor\Document\Document;
use Lsp\Extension\DocumentManager\Editor\Document\DocumentFactoryInterface;
use Lsp\Extension\DocumentManager\Editor\Document\UriFactoryInterface;
use Lsp\Extension\DocumentManager\Editor\EditorInterface;
use Lsp\Kernel\Attribute\AsController;
use Lsp\Protocol\Type\CodeLens;
use Lsp\Protocol\Type\CodeLensParams;
use Lsp\Protocol\Type\Command;
use Lsp\Protocol\Type\Diagnostic;
use Lsp\Protocol\Type\DiagnosticSeverity;
use Lsp\Protocol\Type\DocumentDiagnosticParams;
use Lsp\Protocol\Type\DocumentDiagnosticReportKind;
use Lsp\Protocol\Type\FullDocumentDiagnosticReport;
use Lsp\Protocol\Type\Hover;
use Lsp\Protocol\Type\HoverParams;
use Lsp\Protocol\Type\MarkupContent;
use Lsp\Protocol\Type\MarkupKind;
use Lsp\Protocol\Type\Position;
use Lsp\Protocol\Type\PublishDiagnosticsParams;
use Lsp\Protocol\Type\Range;
use Lsp\Protocol\Type\RelatedFullDocumentDiagnosticReport;
use Lsp\Protocol\Type\TextDocumentIdentifier;
use Lsp\Router\Attribute\Route;
use Lsp\Rpc\Codec\RequestEncoder;
use Lsp\Rpc\Message\Notification;
use Lsp\Server\ConnectionProviderInterface;
use Lsp\Server\ConnectionStore;
use PhpParser\Error;

#[AsController, Route('textDocument/diagnostic')]
final class DiagnosticController
{
    public function __construct(
        private PHPPsiFileParser $fileParser,
//        private DocumentFactoryInterface $documentFactory,
    )
    {
    }

    public function __invoke(EditorInterface $editor, DocumentDiagnosticParams $params): RelatedFullDocumentDiagnosticReport
    {
//        dump('textDocument/diagnostic:', $params);

        $identifier = $params->textDocument;
        $document = $this->getDocument($editor, $identifier);

        $root = $this->fileParser->parse($document);

        $result = array_map(
            fn(Error $error) => new Diagnostic(
                range: new Range(
                    start: new Position($error->getStartLine() - 1, $error->getStartColumn($document->getContents())),
                    end: new Position($error->getEndLine() - 1, $error->getEndColumn($document->getContents())),
                ),
                message: $error->getMessage(),
                severity: DiagnosticSeverity::Error,
            ),
            $root->errors,
        );

//        dump('textDocument/diagnostic/result: ', $result);
        return new RelatedFullDocumentDiagnosticReport(
            kind: DocumentDiagnosticReportKind::Full->value,
            items: $result,
        );
    }

    private function getDocument(EditorInterface $editor, TextDocumentIdentifier $identifier): ?Document
    {
        $document = $editor->findByUriString($identifier->uri);
        if ($document === null) {
            $content = file_get_contents($identifier->uri);
            $document = $this->documentFactory->create($identifier->uri, $content);
            $editor->open($document);
        }

        return $document;
    }
}
