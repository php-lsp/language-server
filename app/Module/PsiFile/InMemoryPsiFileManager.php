<?php

declare(strict_types=1);

namespace App\Module\PsiFile;

use Lsp\Contracts\Server\ConnectionInterface;
use Lsp\Dispatcher\DispatcherInterface;
use Lsp\Dispatcher\Result\Provider\ResultProviderInterface;
use Lsp\Extension\DocumentManager\Editor\Document\Document;
use Lsp\Extension\DocumentManager\Editor\EditorInterface;
use Lsp\Extension\DocumentManager\Editor\MutableEditorInterface;
use Lsp\Protocol\Type\Diagnostic;
use Lsp\Protocol\Type\DiagnosticSeverity;
use Lsp\Protocol\Type\Position;
use Lsp\Protocol\Type\PublishDiagnosticsParams;
use Lsp\Protocol\Type\Range;
use Lsp\Protocol\Type\TextDocumentIdentifier;
use Lsp\Rpc\Codec\RequestEncoder;
use Lsp\Rpc\Message\Notification;
use Lsp\Rpc\Message\Request;
use PhpParser\Error;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;

#[Autoconfigure]
class InMemoryPsiFileManager
{
    private array $models = [];

    public function __construct(
        private PHPPsiFileParser $fileParser,
        private DispatcherInterface $dispatcher,
        private ResultProviderInterface $resultProvider,
        private LoggerInterface $logger,
//        private ConnectionInterface $connection,
    )
    {
    }

    public function refreshFile(EditorInterface $editor, TextDocumentIdentifier $identifier): PHPPsiFile
    {
        $document = $this->getDocument($editor, $identifier);
        if ($document === null) {
            throw new \RuntimeException('Document ' . $identifier->uri . ' not found');
        }

        $root = $this->fileParser->parse($document);
        if ($root->errors) {
            // send diagnostics
//            dump($root->errors);
//            $this->dispatcher->notify(new);

            $p = new PublishDiagnosticsParams(
                uri: $identifier->uri,
                diagnostics: array_map(
                    fn(Error $error) => new Diagnostic(
                        range: new Range(
                            start: new Position($error->getStartLine() - 1, $error->getStartColumn($document->getContents())),
                            end: new Position($error->getEndLine() - 1, $error->getEndColumn($document->getContents())),
                        ),
                        message: $error->getMessage(),
                        severity: DiagnosticSeverity::Error,
                    ),
                    $root->errors,
                ),
            );

            $parameters = $this->resultProvider->getResult($p);
//            dump('PublishDiagnostics: ', $parameters);

            $notification = new Notification(
                method: 'textDocument/publishDiagnostics',
                parameters: $parameters,
            );
            $response = $this->dispatcher->notify($notification);
            if ($response) {
                $this->logger->error('Could not send diagnostics: {error}', [
                    'error' => $response->getMessage(),
                ]);
            }


//            $this->connection->notify(
//                new Notification(
//                    'textDocument/publishDiagnostics',
//                    $encoder->toArray($p),
//                )
//            );
        }

        return $this->models[$identifier->uri] = new PHPPsiFile($root);
    }

    public function findPsiFile(EditorInterface $editor, TextDocumentIdentifier $identifier): ?PHPPsiFile
    {
        $psiFile = $this->models[$identifier->uri] ?? null;
        if ($psiFile === null) {
            $psiFile = $this->refreshFile($editor, $identifier);
        }

        return $psiFile;
    }

    private function getDocument(EditorInterface $editor, TextDocumentIdentifier $identifier): ?Document
    {
        $document = $editor->findByUriString($identifier->uri);
//        if ($document === null) {
//            $this->editor->open($document);
//        }

        return $document;
    }
}
