<?php

declare(strict_types=1);

namespace App\Module\PsiFile;

use App\Core\Contracts\PsiFile\PsiFileManagerInterface;
use App\Module\Document\DocumentLoaderInterface;
use Lsp\Dispatcher\DispatcherInterface;
use Lsp\Dispatcher\Result\Provider\ResultProviderInterface;
use Lsp\Extension\DocumentManager\Editor\Document\Document;
use Lsp\Extension\DocumentManager\Editor\EditorInterface;
use Lsp\Protocol\Type\Diagnostic;
use Lsp\Protocol\Type\DiagnosticSeverity;
use Lsp\Protocol\Type\PublishDiagnosticsParams;
use Lsp\Protocol\Type\TextDocumentIdentifier;
use Lsp\Rpc\Message\Notification;
use Lsp\Workspace\Uri\Uri;
use Override;
use PhpParser\Error;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;

#[Autoconfigure]
class InMemoryPsiFileManager implements PsiFileManagerInterface
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
                        range: Tree::errorRange($error, $document),
                        message: $error->getMessage(),
                        severity: DiagnosticSeverity::Error,
                    ),
                    $root->errors,
                ),
            );

            $parameters = $this->resultProvider->getResult($p);

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
        }

        return new PHPPsiFile($root);
    }

    #[Override]
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

    #[Override]
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
