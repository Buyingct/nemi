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
     * Extracts searchable text from a PDF.
     *
     * If the original PDF contains too little readable text,
     * OCRmyPDF is used automatically before chunk creation.
     *
     * @return array<int, array{
     *     section_title: string|null,
     *     page_number: int|null,
     *     content: string
     * }>
     */
    public function process(
        string $workspacePublicId,
        string $storedPdfPath,
        string $documentPublicId
    ): array {
        if (!is_file($storedPdfPath)) {
            throw new RuntimeException(
                'The source PDF could not be found.'
            );
        }

        if (!is_executable($this->pdfToTextBinary)) {
            throw new RuntimeException(
                'The PDF text extractor is unavailable.'
            );
        }

        $workspaceFolders = $this->storage->createWorkspaceFolders(
            $workspacePublicId
        );

        $originalTextPath = $workspaceFolders['extracted']
            . '/'
            . $documentPublicId
            . '.txt';

        $ocrPdfPath = $workspaceFolders['temporary']
            . '/'
            . $documentPublicId
            . '-ocr.pdf';

        $ocrTextPath = $workspaceFolders['temporary']
            . '/'
            . $documentPublicId
            . '-ocr.txt';

        $this->removeFileIfPresent($originalTextPath);
        $this->removeFileIfPresent($ocrPdfPath);
        $this->removeFileIfPresent($ocrTextPath);

        /*
         * First attempt: extract any existing text layer.
         */
        $originalText = $this->extractText(
            $storedPdfPath,
            $originalTextPath
        );

        $selectedText = $originalText;

        /*
         * Mixed or scanned PDFs may contain only a partial text layer.
         * OCR is triggered when the existing text coverage is poor.
         */
        if ($this->needsOcr($originalText)) {
            try {
                $ocrText = $this->runOcrAndExtract(
                    $storedPdfPath,
                    $ocrPdfPath,
                    $ocrTextPath
                );

                /*
                 * Keep whichever result contains more useful text.
                 */
                if (
                    $this->readableCharacterCount($ocrText)
                    > $this->readableCharacterCount($originalText)
                ) {
                    $selectedText = $ocrText;

                    /*
                     * Preserve the best extracted text in the normal
                     * extracted-document location.
                     */
                    if (
                        file_put_contents(
                            $originalTextPath,
                            $ocrText
                        ) === false
                    ) {
                        throw new RuntimeException(
                            'The OCR text could not be saved.'
                        );
                    }

                    @chmod($originalTextPath, 0664);
                }
            } catch (Throwable $ocrException) {
                error_log(
                    'Document OCR fallback failed: '
                    . $ocrException->getMessage()
                );

                /*
                 * If some usable original text exists, continue with it.
                 * Otherwise the document cannot be processed safely.
                 */
                if (
                    $this->readableCharacterCount($originalText)
                    < 100
                ) {
                    throw new RuntimeException(
                        'The scanned PDF could not be converted '
                        . 'into searchable text.'
                    );
                }
            } finally {
                $this->removeFileIfPresent($ocrPdfPath);
                $this->removeFileIfPresent($ocrTextPath);
            }
        }

        $selectedText = $this->normalizeText($selectedText);

        if ($selectedText === '') {
            throw new RuntimeException(
                'No readable text was found in the PDF.'
            );
        }

        $chunks = $this->createChunks($selectedText);

        if ($chunks === []) {
            throw new RuntimeException(
                'No searchable text chunks were created.'
            );
        }

        return $chunks;
    }

    /**
     * Extracts text from a PDF with pdftotext.
     */
    private function extractText(
        string $pdfPath,
        string $outputPath
    ): string {
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
            error_log(
                'PDF extraction failed: '
                . implode(PHP_EOL, $output)
            );

            return '';
        }

        $text = file_get_contents($outputPath);

        if ($text === false) {
            throw new RuntimeException(
                'The extracted text could not be read.'
            );
        }

        @chmod($outputPath, 0664);

        return $text;
    }

    /**
     * Creates a searchable OCR copy, then extracts its text.
     */
    private function runOcrAndExtract(
        string $sourcePdfPath,
        string $ocrPdfPath,
        string $ocrTextPath
    ): string {
        if (!is_executable($this->ocrMyPdfBinary)) {
            throw new RuntimeException(
                'The OCR processor is unavailable.'
            );
        }

        $this->removeFileIfPresent($ocrPdfPath);
        $this->removeFileIfPresent($ocrTextPath);

        /*
         * --skip-text:
         * Keeps pages that already contain text and OCRs image-only pages.
         *
         * --rotate-pages:
         * Corrects pages scanned in the wrong orientation.
         *
         * --deskew:
         * Straightens slightly crooked scanned pages.
         *
         * --output-type pdf:
         * Produces a regular searchable PDF instead of requiring PDF/A.
         *
         * --optimize 0:
         * Avoids unnecessary optimization work during ingestion.
         *
         * --jobs 2:
         * Limits server CPU use while processing large documents.
         */
        $command = sprintf(
            '%s'
            . ' --skip-text'
            . ' --rotate-pages'
            . ' --deskew'
            . ' --output-type pdf'
            . ' --optimize 0'
            . ' --jobs 2'
            . ' --language eng'
            . ' %s %s 2>&1',
            escapeshellarg($this->ocrMyPdfBinary),
            escapeshellarg($sourcePdfPath),
            escapeshellarg($ocrPdfPath)
        );

        $output = [];
        $exitCode = 0;

        exec($command, $output, $exitCode);

        if ($exitCode !== 0 || !is_file($ocrPdfPath)) {
            error_log(
                'OCRmyPDF failed with exit code '
                . $exitCode
                . ': '
                . implode(PHP_EOL, $output)
            );

            throw new RuntimeException(
                'OCR could not process this PDF.'
            );
        }

        @chmod($ocrPdfPath, 0664);

        $text = $this->extractText(
            $ocrPdfPath,
            $ocrTextPath
        );

        if ($this->readableCharacterCount($text) < 100) {
            throw new RuntimeException(
                'OCR completed but did not produce enough readable text.'
            );
        }

        return $text;
    }

    /**
     * Determines whether the PDF has poor searchable-text coverage.
     */
    private function needsOcr(string $text): bool
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

        $coverage = $readablePages / $totalPages;

        /*
         * OCR mixed documents when fewer than 70% of their pages
         * contain a meaningful searchable-text layer.
         */
        return $coverage < 0.70;
    }

    private function readableCharacterCount(string $text): int
    {
        $withoutWhitespace = preg_replace(
            '/\s+/u',
            '',
            $text
        );

        if (!is_string($withoutWhitespace)) {
            return 0;
        }

        return mb_strlen($withoutWhitespace);
    }

    private function normalizeText(string $text): string
    {
        $text = str_replace(
            ["\r\n", "\r"],
            "\n",
            $text
        );

        $text = preg_replace(
            '/[ \t]+/',
            ' ',
            $text
        ) ?? $text;

        $text = preg_replace(
            "/\n{3,}/",
            "\n\n",
            $text
        ) ?? $text;

        return trim($text);
    }

    /**
     * @return array<int, array{
     *     section_title: string|null,
     *     page_number: int|null,
     *     content: string
     * }>
     */
    private function createChunks(string $text): array
    {
        /*
         * pdftotext separates PDF pages using form-feed characters.
         */
        $pages = preg_split('/\f/', $text);

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

            $pageNumber = $pageIndex + 1;

            $paragraphs = preg_split(
                "/\n\s*\n/",
                $pageText
            );

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

                if ($this->looksLikeHeading($paragraph)) {
                    if ($buffer !== '') {
                        $chunks[] = [
                            'section_title' => $sectionTitle,
                            'page_number' => $pageNumber,
                            'content' => trim($buffer),
                        ];

                        $buffer = '';
                    }

                    $sectionTitle = mb_substr(
                        $paragraph,
                        0,
                        250
                    );

                    continue;
                }

                $candidate = $buffer === ''
                    ? $paragraph
                    : $buffer . "\n\n" . $paragraph;

                if (
                    mb_strlen($candidate)
                    > $maximumChunkLength
                    && $buffer !== ''
                ) {
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
                static fn (array $chunk): bool =>
                    mb_strlen($chunk['content']) >= 40
            )
        );
    }

    private function looksLikeHeading(string $text): bool
    {
        if (mb_strlen($text) > 180) {
            return false;
        }

        if (str_contains($text, "\n")) {
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