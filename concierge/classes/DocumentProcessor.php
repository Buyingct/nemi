<?php

declare(strict_types=1);

require_once __DIR__ . '/WorkspaceStorage.php';

final class DocumentProcessor
{
    private WorkspaceStorage $storage;
    private string $pdfToTextBinary;

    public function __construct(
        ?WorkspaceStorage $storage = null,
        string $pdfToTextBinary = '/usr/bin/pdftotext'
    ) {
        $this->storage = $storage ?? new WorkspaceStorage();
        $this->pdfToTextBinary = $pdfToTextBinary;
    }

    /**
     * Extracts text from one stored PDF and returns searchable chunks.
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

        $extractedDirectory = $this->storage->extractedPath(
            $workspacePublicId
        );

        $outputPath = $extractedDirectory
            . '/'
            . $documentPublicId
            . '.txt';

        $command = sprintf(
            '%s -layout -enc UTF-8 %s %s 2>&1',
            escapeshellarg($this->pdfToTextBinary),
            escapeshellarg($storedPdfPath),
            escapeshellarg($outputPath)
        );

        exec($command, $output, $exitCode);

        if ($exitCode !== 0 || !is_file($outputPath)) {
            error_log(
                'PDF extraction failed: '
                . implode(PHP_EOL, $output)
            );

            throw new RuntimeException(
                'The PDF text could not be extracted.'
            );
        }

        $text = file_get_contents($outputPath);

        if ($text === false) {
            throw new RuntimeException(
                'The extracted text could not be read.'
            );
        }

        $text = $this->normalizeText($text);

        if ($text === '') {
            throw new RuntimeException(
                'No readable text was found in the PDF.'
            );
        }

        return $this->createChunks($text);
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
         * pdftotext separates PDF pages with form-feed characters.
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

        $singleLine = !str_contains($text, "\n");

        if (!$singleLine) {
            return false;
        }

        return (bool) preg_match(
            '/^(ARTICLE|SECTION|EXHIBIT|SCHEDULE)\b|'
            . '^\d+(\.\d+)*[\s.\-]|'
            . '^[A-Z][A-Z0-9 ,&()\/\-]{5,}$/',
            trim($text)
        );
    }
}