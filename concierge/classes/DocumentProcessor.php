<?php

declare(strict_types=1);

require_once __DIR__ . '/WorkspaceStorage.php';

final class DocumentProcessor
{
    private WorkspaceStorage $storage;
    private string $pdfToTextBinary;
    private string $ocrMyPdfBinary;

    public function __construct(
        ?WorkspaceStorage $storage = null,
        string $pdfToTextBinary = '/usr/bin/pdftotext',
        string $ocrMyPdfBinary = '/usr/bin/ocrmypdf'
    ) {
        $this->storage = $storage ?? new WorkspaceStorage();
        $this->pdfToTextBinary = $pdfToTextBinary;
        $this->ocrMyPdfBinary = $ocrMyPdfBinary;
    }

    /**
     * Processes a searchable PDF, verified Markdown file, or plain-text file.
     *
     * Scanned and mixed PDFs are intentionally NOT indexed. They must be
     * digitized first or reviewed through the separate OCR preview workflow.
     *
     * @return array<int, array{
     *     section_title: string|null,
     *     page_number: int|null,
     *     content: string
     * }>
     */
    public function process(
        string $workspacePublicId,
        string $storedDocumentPath,
        string $documentPublicId
    ): array {
        if (!is_file($storedDocumentPath)) {
            throw new RuntimeException('The source document could not be found.');
        }

        $extension = strtolower(pathinfo($storedDocumentPath, PATHINFO_EXTENSION));

        return match ($extension) {
            'pdf' => $this->processPdf(
                $workspacePublicId,
                $storedDocumentPath,
                $documentPublicId
            ),
            'md', 'markdown' => $this->processTextDocument(
                $workspacePublicId,
                $storedDocumentPath,
                $documentPublicId,
                true
            ),
            'txt' => $this->processTextDocument(
                $workspacePublicId,
                $storedDocumentPath,
                $documentPublicId,
                false
            ),
            default => throw new RuntimeException(
                'Unsupported document type. Please upload a PDF, MD, or TXT file.'
            ),
        };
    }

    /**
     * Creates an OCR text preview without indexing the document.
     */
    public function runOcrPreview(
        string $workspacePublicId,
        string $storedPdfPath,
        string $documentPublicId
    ): string {
        if (!is_file($storedPdfPath)) {
            throw new RuntimeException('The source PDF could not be found.');
        }

        if (strtolower(pathinfo($storedPdfPath, PATHINFO_EXTENSION)) !== 'pdf') {
            throw new RuntimeException('OCR preview is available only for PDF documents.');
        }

        $workspaceFolders = $this->storage->createWorkspaceFolders($workspacePublicId);

        $ocrPdfPath = $workspaceFolders['temporary'] . '/' . $documentPublicId . '-ocr-preview.pdf';
        $ocrTextPath = $workspaceFolders['temporary'] . '/' . $documentPublicId . '-ocr-preview.txt';

        $this->removeFileIfPresent($ocrPdfPath);
        $this->removeFileIfPresent($ocrTextPath);

        try {
            $text = $this->runOcrAndExtract(
                $storedPdfPath,
                $ocrPdfPath,
                $ocrTextPath
            );

            $text = $this->normalizeText($text);

            if ($text === '') {
                throw new RuntimeException('OCR preview did not produce readable text.');
            }

            return $text;
        } finally {
            $this->removeFileIfPresent($ocrPdfPath);
            $this->removeFileIfPresent($ocrTextPath);
        }
    }

    private function processPdf(
        string $workspacePublicId,
        string $storedPdfPath,
        string $documentPublicId
    ): array {
        if (!is_executable($this->pdfToTextBinary)) {
            throw new RuntimeException('The PDF text extractor is unavailable.');
        }

        $workspaceFolders = $this->storage->createWorkspaceFolders($workspacePublicId);
        $extractedTextPath = $workspaceFolders['extracted'] . '/' . $documentPublicId . '.txt';

        $this->removeFileIfPresent($extractedTextPath);

        $text = $this->extractText($storedPdfPath, $extractedTextPath);

        if ($this->needsDigitization($text)) {
            $this->removeFileIfPresent($extractedTextPath);

            throw new RuntimeException(
                'NEEDS_DIGITIZATION: This PDF is scanned or partially scanned. '
                . 'For precision, upload a verified digital PDF, Markdown (.md), '
                . 'or text (.txt) version. You may also run an OCR preview.'
            );
        }

        $text = $this->normalizeText($text);

        if ($text === '') {
            throw new RuntimeException('No readable text was found in the PDF.');
        }

        $chunks = $this->createChunks($text, true);

        if ($chunks === []) {
            throw new RuntimeException('No searchable text chunks were created.');
        }

        return $chunks;
    }

    private function processTextDocument(
        string $workspacePublicId,
        string $storedDocumentPath,
        string $documentPublicId,
        bool $isMarkdown
    ): array {
        $text = file_get_contents($storedDocumentPath);

        if ($text === false) {
            throw new RuntimeException('The uploaded text document could not be read.');
        }

        $text = $this->normalizeText($text);

        if ($text === '') {
            throw new RuntimeException('The uploaded text document is empty.');
        }

        if ($this->readableCharacterCount($text) < 40) {
            throw new RuntimeException(
                'The uploaded text document does not contain enough readable text.'
            );
        }

        $workspaceFolders = $this->storage->createWorkspaceFolders($workspacePublicId);
        $extractedTextPath = $workspaceFolders['extracted'] . '/' . $documentPublicId . '.txt';

        if (file_put_contents($extractedTextPath, $text) === false) {
            throw new RuntimeException('The verified text could not be stored.');
        }

        @chmod($extractedTextPath, 0664);

        $chunks = $this->createChunks($text, false, $isMarkdown);

        if ($chunks === []) {
            throw new RuntimeException('No searchable text chunks were created.');
        }

        return $chunks;
    }

    private function extractText(string $pdfPath, string $outputPath): string
    {
        $this->removeFileIfPresent($outputPath);

        $command = sprintf(
            '%s -layout -enc UTF-8 %s %s 2>&1',
            escapeshellarg($this->pdfToTextBinary),
            escapeshellarg($pdfPath),
            escapeshellarg($outputPath)
        );

        $output = [];
        $exitCode = 0;
        exec($command, $output, $exitCode);

        if ($exitCode !== 0 || !is_file($outputPath)) {
            error_log('PDF extraction failed: ' . implode(PHP_EOL, $output));
            return '';
        }

        $text = file_get_contents($outputPath);

        if ($text === false) {
            throw new RuntimeException('The extracted text could not be read.');
        }

        @chmod($outputPath, 0664);
        return $text;
    }

    private function runOcrAndExtract(
        string $sourcePdfPath,
        string $ocrPdfPath,
        string $ocrTextPath
    ): string {
        if (!is_executable($this->ocrMyPdfBinary)) {
            throw new RuntimeException('The OCR processor is unavailable.');
        }

        $this->removeFileIfPresent($ocrPdfPath);
        $this->removeFileIfPresent($ocrTextPath);

        $command = sprintf(
            '%s --skip-text --rotate-pages --deskew --output-type pdf --optimize 0 --jobs 2 --language eng %s %s 2>&1',
            escapeshellarg($this->ocrMyPdfBinary),
            escapeshellarg($sourcePdfPath),
            escapeshellarg($ocrPdfPath)
        );

        $output = [];
        $exitCode = 0;
        exec($command, $output, $exitCode);

        if ($exitCode !== 0 || !is_file($ocrPdfPath)) {
            error_log(
                'OCRmyPDF failed with exit code ' . $exitCode . ': ' . implode(PHP_EOL, $output)
            );

            throw new RuntimeException('OCR could not process this PDF.');
        }

        @chmod($ocrPdfPath, 0664);

        $text = $this->extractText($ocrPdfPath, $ocrTextPath);

        if ($this->readableCharacterCount($text) < 100) {
            throw new RuntimeException(
                'OCR completed but did not produce enough readable text.'
            );
        }

        return $text;
    }

    private function needsDigitization(string $text): bool
    {
        if ($this->readableCharacterCount($text) < 1000) {
            return true;
        }

        $pages = preg_split('/\f/', $text);

        if (!is_array($pages) || $pages === []) {
            return true;
        }

        $totalPages = count($pages);
        $readablePages = 0;

        foreach ($pages as $pageText) {
            if ($this->readableCharacterCount($pageText) >= 80) {
                $readablePages++;
            }
        }

        if ($totalPages <= 1) {
            return false;
        }

        return ($readablePages / $totalPages) < 0.90;
    }

    private function readableCharacterCount(string $text): int
    {
        $withoutWhitespace = preg_replace('/\s+/u', '', $text);

        if (!is_string($withoutWhitespace)) {
            return 0;
        }

        return mb_strlen($withoutWhitespace);
    }

    private function normalizeText(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = preg_replace('/[ \t]+/', ' ', $text) ?? $text;
        $text = preg_replace("/\n{3,}/", "\n\n", $text) ?? $text;

        return trim($text);
    }

    private function createChunks(
        string $text,
        bool $preservePdfPages,
        bool $isMarkdown = false
    ): array {
        $pages = $preservePdfPages ? preg_split('/\f/', $text) : [$text];

        if (!is_array($pages)) {
            $pages = [$text];
        }

        $chunks = [];
        $maximumChunkLength = 3500;

        foreach ($pages as $pageIndex => $pageText) {
            $pageText = trim($pageText);

            if ($pageText === '') {
                continue;
            }

            $pageNumber = $preservePdfPages ? $pageIndex + 1 : null;
            $paragraphs = preg_split("/\n\s*\n/", $pageText);

            if (!is_array($paragraphs)) {
                $paragraphs = [$pageText];
            }

            $buffer = '';
            $sectionTitle = null;

            foreach ($paragraphs as $paragraph) {
                $paragraph = trim($paragraph);

                if ($paragraph === '') {
                    continue;
                }

                if (
                    $isMarkdown
                    && preg_match('/^#{1,6}\s+(.+)$/u', $paragraph, $matches)
                ) {
                    if ($buffer !== '') {
                        $chunks[] = [
                            'section_title' => $sectionTitle,
                            'page_number' => $pageNumber,
                            'content' => trim($buffer),
                        ];
                        $buffer = '';
                    }

                    $sectionTitle = mb_substr(trim($matches[1]), 0, 250);
                    continue;
                }

                if ($this->looksLikeHeading($paragraph)) {
                    if ($buffer !== '') {
                        $chunks[] = [
                            'section_title' => $sectionTitle,
                            'page_number' => $pageNumber,
                            'content' => trim($buffer),
                        ];
                        $buffer = '';
                    }

                    $sectionTitle = mb_substr($paragraph, 0, 250);
                    continue;
                }

                $candidate = $buffer === ''
                    ? $paragraph
                    : $buffer . "\n\n" . $paragraph;

                if (mb_strlen($candidate) > $maximumChunkLength && $buffer !== '') {
                    $chunks[] = [
                        'section_title' => $sectionTitle,
                        'page_number' => $pageNumber,
                        'content' => trim($buffer),
                    ];

                    $buffer = $paragraph;
                    continue;
                }

                $buffer = $candidate;
            }

            if ($buffer !== '') {
                $chunks[] = [
                    'section_title' => $sectionTitle,
                    'page_number' => $pageNumber,
                    'content' => trim($buffer),
                ];
            }
        }

        return array_values(
            array_filter(
                $chunks,
                static fn (array $chunk): bool => mb_strlen($chunk['content']) >= 40
            )
        );
    }

    private function looksLikeHeading(string $text): bool
    {
        if (mb_strlen($text) > 180 || str_contains($text, "\n")) {
            return false;
        }

        return (bool) preg_match(
            '/^(ARTICLE|SECTION|EXHIBIT|SCHEDULE)\b|'
            . '^\d+(\.\d+)*[\s.\-]|'
            . '^[A-Z][A-Z0-9 ,&()\/\-]{5,}$/',
            trim($text)
        );
    }

    private function removeFileIfPresent(string $path): void
    {
        if (is_file($path) && !unlink($path)) {
            throw new RuntimeException(
                'A temporary processing file could not be removed.'
            );
        }
    }
}