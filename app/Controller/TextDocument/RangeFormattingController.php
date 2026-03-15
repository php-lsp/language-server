<?php

declare(strict_types=1);

namespace App\Controller\TextDocument;

use App\Core\UriHelper;
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
    private ?string $cachedMagoBinary = null;

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
        $filePath = UriHelper::toFilePath($params->textDocument->uri);
        if ($filePath === null) {
            return null;
        }

        $formatted = $this->runFormatter($filePath, $originalContent);
        if ($formatted === null || $formatted === $originalContent) {
            return [];
        }

        $originalLines = explode("\n", $originalContent);
        $formattedLines = explode("\n", $formatted);

        $startLine = $params->range->start->line;
        $endLine = min($params->range->end->line, count($originalLines) - 1);

        $originalRange = array_slice($originalLines, $startLine, $endLine - $startLine + 1);
        $formattedRange = array_slice($formattedLines, $startLine, $endLine - $startLine + 1);

        $originalRangeText = implode("\n", $originalRange);
        $formattedRangeText = implode("\n", $formattedRange);

        if ($originalRangeText === $formattedRangeText) {
            return [];
        }

        return [
            new TextEdit(
                range: new Range(
                    start: new Position($startLine, 0),
                    end: new Position($endLine, strlen($originalLines[$endLine] ?? '')),
                ),
                newText: $formattedRangeText,
            ),
        ];
    }

    private function runFormatter(string $filePath, string $content): ?string
    {
        $tempFile = tempnam(sys_get_temp_dir(), prefix: 'lsp_rfmt_');
        if ($tempFile === false) {
            return null;
        }

        try {
            file_put_contents($tempFile, $content);

            $magoPath = $this->findMagoBinary($filePath);
            $command = sprintf(
                '%s format %s 2>/dev/null',
                escapeshellarg($magoPath),
                escapeshellarg($tempFile),
            );

            exec($command, $output, $exitCode);

            if ($exitCode !== 0) {
                $this->logger->warning('Mago format exited with code {code}', ['code' => $exitCode]);

                return null;
            }

            $result = file_get_contents($tempFile);

            return $result !== false ? $result : null;
        } catch (\Throwable $e) {
            $this->logger->error('Range formatting failed: {error}', ['error' => $e->getMessage()]);

            return null;
        } finally {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
    }

    private function findMagoBinary(string $filePath): string
    {
        if ($this->cachedMagoBinary !== null) {
            return $this->cachedMagoBinary;
        }

        $dir = dirname($filePath);
        while ($dir !== '/' && $dir !== '') {
            $vendorBin = $dir . '/vendor/bin/mago';
            if (file_exists($vendorBin)) {
                $this->cachedMagoBinary = $vendorBin;

                return $vendorBin;
            }
            $dir = dirname($dir);
        }

        $this->cachedMagoBinary = 'mago';

        return 'mago';
    }
}
