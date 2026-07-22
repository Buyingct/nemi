<?php

declare(strict_types=1);

final class KnowledgeSearch
{
    public function __construct(private PDO $database)
    {
    }

    /** @return array<int, array<string, mixed>> */
    public function getReadyChunks(int $workspaceId): array
    {
        $statement = $this->database->prepare(
            'SELECT k.id, k.document_id, k.section_title, k.page_number,
                    k.content, d.original_name, d.category, d.document_type,
                    d.knowledge_source
             FROM knowledge AS k
             INNER JOIN documents AS d ON d.id = k.document_id
             WHERE k.workspace_id = :workspace_id
               AND d.workspace_id = :workspace_id
               AND d.status = "ready"
               AND d.knowledge_source != "none"
             ORDER BY k.id ASC'
        );

        $statement->execute([
            ':workspace_id' => $workspaceId,
        ]);

        return $statement->fetchAll();
    }

    /**
     * @param array<int, array<string, mixed>> $chunks
     * @param array<int, string> $keywords
     * @return array<int, array{score:int,chunk:array<string,mixed>}>
     */
    public function rankChunks(
        array $chunks,
        array $keywords,
        string $question
    ): array {
        $ranked = [];

        foreach ($chunks as $chunk) {
            $score = $this->scoreChunk(
                $chunk,
                $keywords,
                $question
            );

            if ($score > 0) {
                $ranked[] = [
                    'score' => $score,
                    'chunk' => $chunk,
                ];
            }
        }

        usort(
            $ranked,
            static fn(array $left, array $right): int =>
                $right['score'] <=> $left['score']
        );

        return $ranked;
    }

    /**
     * @param array<string, mixed> $chunk
     * @param array<int, string> $keywords
     */
    private function scoreChunk(
        array $chunk,
        array $keywords,
        string $question
    ): int {
        $content = $this->normalizeSearchText(
            (string) ($chunk['content'] ?? '')
        );

        $sectionTitle = $this->normalizeSearchText(
            (string) ($chunk['section_title'] ?? '')
        );

        $documentName = $this->normalizeSearchText(
            (string) ($chunk['original_name'] ?? '')
        );

        $category = $this->normalizeSearchText(
            (string) ($chunk['category'] ?? '')
        );

        $normalizedQuestion = $this->normalizeSearchText(
            $question
        );

        $score = 0;
        $distinctMatches = 0;

        foreach ($keywords as $keyword) {
            $normalizedKeyword = $this->normalizeSearchText(
                $keyword
            );

            if ($normalizedKeyword === '') {
                continue;
            }

            $contentCount = substr_count(
                $content,
                $normalizedKeyword
            );

            if ($contentCount > 0) {
                $distinctMatches++;
            }

            $score += min($contentCount, 6) * 2;

            $score += min(
                substr_count($sectionTitle, $normalizedKeyword),
                3
            ) * 8;

            $score += min(
                substr_count($documentName, $normalizedKeyword),
                2
            ) * 3;

            $score += min(
                substr_count($category, $normalizedKeyword),
                2
            ) * 3;
        }

        if ($distinctMatches >= 2) {
            $score += 8;
        }

        if ($distinctMatches >= 4) {
            $score += 12;
        }

        $isQuantityQuestion =
            str_contains($normalizedQuestion, 'how many')
            || str_contains($normalizedQuestion, 'number of')
            || str_contains($normalizedQuestion, 'size of');

        if ($isQuantityQuestion) {
            if (
                preg_match(
                    '/\b(one|two|three|four|five|six|seven|eight|nine|ten)\b/u',
                    $content
                ) === 1
            ) {
                $score += 10;
            }

            if (
                preg_match(
                    '/\b\d+\s*\(\d+\)|\(\d+\)|\b\d+\b/u',
                    $content
                ) === 1
            ) {
                $score += 10;
            }

            foreach (
                [
                    'shall consist of',
                    'consists of',
                    'consist of',
                    'composed of',
                    'number of directors',
                    'number of members',
                ] as $phrase
            ) {
                if (str_contains($content, $phrase)) {
                    $score += 28;
                }
            }
        }

        $isBoardQuestion =
            str_contains($normalizedQuestion, 'board')
            || str_contains($normalizedQuestion, 'director');

        if ($isBoardQuestion) {
            if (
                str_contains($content, 'executive board')
                && (
                    str_contains($content, 'shall consist of')
                    || str_contains($content, 'consists of')
                )
            ) {
                $score += 35;
            }

            if (
                str_contains($content, 'vacancy')
                || str_contains($content, 'vacancies')
            ) {
                $score -= 8;
            }

            if (
                str_contains($content, 'records of')
                || str_contains($content, 'minutes of')
            ) {
                $score -= 8;
            }
        }

        return max($score, 0);
    }

    private function normalizeSearchText(
        string $text
    ): string {
        $text = mb_strtolower(trim($text));

        $text = str_replace(
            ['-', '–', '—'],
            ' ',
            $text
        );

        $text = preg_replace(
            '/[^\p{L}\p{N}\s]+/u',
            ' ',
            $text
        ) ?? $text;

        $text = preg_replace(
            '/\s+/u',
            ' ',
            $text
        ) ?? $text;

        $text = preg_replace(
            '/\btime\s+shares?\b/u',
            'timeshare',
            $text
        ) ?? $text;

        return trim($text);
    }
}