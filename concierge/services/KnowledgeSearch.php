<?php

declare(strict_types=1);

final class KnowledgeSearch
{
    public function __construct(
        private PDO $database
    ) {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getReadyChunks(int $workspaceId): array
    {
        $statement = $this->database->prepare(
            '
            SELECT
                k.id,
                k.document_id,
                k.section_title,
                k.page_number,
                k.content,
                d.original_name,
                d.category,
                d.document_type,
                d.knowledge_source
            FROM knowledge AS k
            INNER JOIN documents AS d
                ON d.id = k.document_id
            WHERE k.workspace_id = :workspace_id
              AND d.workspace_id = :workspace_id
              AND d.status = "ready"
              AND d.knowledge_source != "none"
            ORDER BY k.id ASC
            '
        );

        $statement->execute([
            ':workspace_id' => $workspaceId,
        ]);

        return $statement->fetchAll();
    }

    /**
     * @param array<int, array<string, mixed>> $chunks
     * @param array<int, string> $keywords
     *
     * @return array<int, array{
     *     score: int,
     *     chunk: array<string, mixed>
     * }>
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
            static fn (array $left, array $right): int =>
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
        $content = mb_strtolower(
            (string) ($chunk['content'] ?? '')
        );

        $sectionTitle = mb_strtolower(
            (string) ($chunk['section_title'] ?? '')
        );

        $documentName = mb_strtolower(
            (string) ($chunk['original_name'] ?? '')
        );

        $category = mb_strtolower(
            (string) ($chunk['category'] ?? '')
        );

        $score = 0;

        foreach ($keywords as $keyword) {
            $score += min(
                substr_count($content, $keyword),
                6
            ) * 2;

            $score += min(
                substr_count($sectionTitle, $keyword),
                3
            ) * 7;

            $score += min(
                substr_count($documentName, $keyword),
                2
            ) * 3;

            $score += min(
                substr_count($category, $keyword),
                2
            ) * 3;
        }

        $normalizedQuestion = mb_strtolower(
            preg_replace(
                '/\s+/u',
                ' ',
                trim($question)
            ) ?? trim($question)
        );

        if (
            mb_strlen($normalizedQuestion) >= 8
            && str_contains($content, $normalizedQuestion)
        ) {
            $score += 20;
        }

        return $score;
    }
}
