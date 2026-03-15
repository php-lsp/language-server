<?php

declare(strict_types=1);

namespace App\Module\PsiFile;

use App\Module\Document\DocumentLoaderInterface;
use Lsp\Dispatcher\DispatcherInterface;
use Lsp\Dispatcher\Result\Provider\ResultProviderInterface;
use Lsp\Extension\DocumentManager\Editor\Document\Document;
use Lsp\Extension\DocumentManager\Editor\EditorInterface;
use Lsp\Protocol\Type\Diagnostic;
use Lsp\Protocol\Type\DiagnosticSeverity;
use Lsp\Protocol\Type\Position;
use Lsp\Protocol\Type\PublishDiagnosticsParams;
use Lsp\Protocol\Type\Range;
use Lsp\Protocol\Type\TextDocumentIdentifier;
use Lsp\Rpc\Message\Notification;
use Lsp\Workspace\Uri\Uri;
use PhpParser\Error;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;

#[Autoconfigure]
class InMemoryPsiFileManager
{
    /**
     * @var FifoCache<PHPPsiFile>
     */
    private FifoCache $cache;

    public function __construct(
        private PHPPsiFileParser $fileParser,
        private DispatcherInterface $dispatcher,
        private ResultProviderInterface $resultProvider,
        private LoggerInterface $logger,
        private DocumentLoaderInterface $documentLoader,
    ) {
        $this->cache = new FifoCache(300);
    }

    public function refreshFile(?Document $document, Uri|TextDocumentIdentifier $identifier): PHPPsiFile
    {
        $uri = match (true) {
            $identifier instanceof TextDocumentIdentifier => $identifier->uri,
            $identifier instanceof Uri => (string) $identifier,
            default => throw new \InvalidArgumentException('Unsupported identifier ' . get_debug_type($identifier)),
        };
        if ($document === null) {
            throw new \RuntimeException('Document ' . $uri . ' not found');
        }

        $root = $this->fileParser->parse($document);
        if ($root->errors) {
            $p = new PublishDiagnosticsParams(
                uri: $uri,
                diagnostics: array_map(
                    static fn(Error $error) => new Diagnostic(
                        range: new Range(
                            start: new Position(
                                $error->getStartLine() - 1,
                                $error->getStartColumn($document->getContents()),
                            ),
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

        return new PHPPsiFile($root);
    }

    public function findPsiFile(EditorInterface $editor, TextDocumentIdentifier $identifier): ?PHPPsiFile
    {
        $psiFile = $this->cache->get($identifier->uri);
        if ($psiFile !== null) {
            $document = $editor->findByUriString($identifier->uri);
            if ($psiFile->ast->document->version !== $document->version) {
                $psiFile = null;
            }
        }
        if ($psiFile === null) {
            $document = $editor->findByUriString($identifier->uri);

            if ($document === null) {
                $document = $this->documentLoader->load($identifier);
                $editor->open($document);
            }
            $psiFile = $this->refreshFile($document, $identifier);

            $this->cache->setPermanent($identifier->uri, $psiFile);
        }

        return $psiFile;
    }

    public function invalidate(string $uri): void
    {
        $this->cache->remove($uri);
    }

    public function findPsiFileByUri(Uri $uri): ?PHPPsiFile
    {
        $psiFile = $this->cache->get((string) $uri);

        if ($psiFile === null) {
            $document = $this->documentLoader->load($uri);
            $psiFile = $this->refreshFile($document, $uri);
            $this->cache->set((string) $uri, $psiFile);
        }

        return $psiFile;
    }
}
