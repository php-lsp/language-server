<?php

declare(strict_types=1);

namespace App\Controller\TextDocument;

use Lsp\Extension\DocumentManager\Editor\EditorInterface;
use Lsp\Kernel\Attribute\AsController;
use Lsp\Protocol\Type\DocumentRangeFormattingParams;
use Lsp\Protocol\Type\Position;
use Lsp\Protocol\Type\Range;
use Lsp\Protocol\Type\TextEdit;
use Lsp\Router\Attribute\Route;
use Psr\Log\LoggerInterface;

#[AsController, Route('textDocument/rangeFormatting')]
final class RangeFormattingController
{
    public function __construct(
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * @return list<TextEdit>|null
     */
    public function __invoke(EditorInterface $editor, DocumentRangeFormattingParams $params): ?array
    {
        $document = $editor->findByUriString($params->textDocument->uri);
        if ($document === null) {
            return null;
        }

        $originalContent = $document->getContents();
        $filePath = $this->uriToPath($params->textDocument->uri);
        if ($filePath === null) {
            return null;
        }

        $lines = explode("\n", $originalContent);
        $startLine = $params->range->start->line;
        $endLine = $params->range->end->line;

        $rangeLines = array_slice($lines, $startLine, $endLine - $startLine + 1);
        $rangeContent = implode("\n", $rangeLines);

        $wrappedContent = "<?php\n" . $rangeContent;
        $formatted = $this->runFormatter($filePath, $wrappedContent);

        if ($formatted === null) {
            return [];
        }

        $formattedContent = preg_replace(pattern: '/^<\?php\n/', replacement: '', subject: $formatted);
        if ($formattedContent === $rangeContent) {
            return [];
        }

        return [
            new TextEdit(
                range: new Range(
                    start: new Position($startLine, 0),
                    end: new Position($endLine, strlen($lines[$endLine] ?? '')),
                ),
                newText: $formattedContent,
            ),
        ];
    }

    private function uriToPath(string $uri): ?string
    {
        if (str_starts_with($uri, 'file://')) {
            return substr($uri, offset: 7);
        }

        return null;
    }

    private function runFormatter(string $filePath, string $content): ?string
    {
        $tempFile = tempnam(sys_get_temp_dir(), prefix: 'lsp_rfmt_');
        if ($tempFile === false) {
            return null;
        }

        try {
            file_put_contents($tempFile, $content);

            $command = sprintf(
                'php-cs-fixer fix %s --using-cache=no --quiet 2>/dev/null'
                . ' || phpcbf --standard=PSR12 %s 2>/dev/null'
                . ' || true',
                escapeshellarg($tempFile),
                escapeshellarg($tempFile),
            );

            exec($command, $output, $exitCode);

            $result = file_get_contents($tempFile);

            return $result !== false ? $result : null;
        } catch (\Throwable $e) {
            $this->logger->error('Range formatting failed: ' . $e->getMessage());

            return null;
        } finally {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
    }
}
