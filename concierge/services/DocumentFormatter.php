<?php

declare(strict_types=1);

final class DocumentFormatter
{
    public static function friendlyDocumentName(string $filename): string
    {
        $name = pathinfo($filename, PATHINFO_FILENAME);

        $name = preg_replace(
            '/[_-]+/',
            ' ',
            $name
        ) ?? $name;

        $name = preg_replace(
            '/\s+/',
            ' ',
            trim($name)
        ) ?? trim($name);

        return mb_convert_case(
            $name,
            MB_CASE_TITLE,
            'UTF-8'
        );
    }

    public static function cleanAnswerText(string $content): string
    {
        $content = preg_replace(
            '/\s+/u',
            ' ',
            trim($content)
        ) ?? trim($content);

        if (mb_strlen($content) > 1400) {
            $content = mb_substr($content, 0, 1400);

            $lastPeriod = mb_strrpos($content, '.');

            if (
                $lastPeriod !== false
                && $lastPeriod > 700
            ) {
                $content = mb_substr(
                    $content,
                    0,
                    $lastPeriod + 1
                );
            } else {
                $content = rtrim($content) . '…';
            }
        }

        return $content;
    }

    public static function relevanceLabel(int $score): string
    {
        if ($score >= 30) {
            return 'Strong match';
        }

        if ($score >= 15) {
            return 'Relevant match';
        }

        return 'Possible match';
    }

    /**
     * @param array<string, mixed> $chunk
     */
    public static function sourceLabel(array $chunk): string
    {
        $parts = [
            self::friendlyDocumentName(
                (string) ($chunk['original_name'] ?? '')
            ),
        ];

        $sectionTitle = trim(
            (string) ($chunk['section_title'] ?? '')
        );

        if ($sectionTitle !== '') {
            $parts[] = $sectionTitle;
        }

        $pageNumber = $chunk['page_number'] ?? null;

        if ($pageNumber !== null && (int) $pageNumber > 0) {
            $parts[] = 'Page ' . (int) $pageNumber;
        }

        return implode(' — ', $parts);
    }
}
