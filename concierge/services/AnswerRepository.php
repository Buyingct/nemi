<?php

declare(strict_types=1);

final class AnswerRepository
{
    public function __construct(
        private PDO $database
    ) {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function findDrafts(): array
    {
        $statement = $this->database->prepare(
            '
            SELECT
                a.id,
                a.public_id,
                a.workspace_id,
                a.canonical_question,
                a.answer_text,
                a.status,
                a.source_strength,
                a.created_at,
                a.updated_at,
                w.name AS workspace_name,
                (
                    SELECT COUNT(*)
                    FROM questions AS q
                    WHERE q.answer_id = a.id
                ) AS question_count,
                (
                    SELECT COUNT(*)
                    FROM answer_sources AS s
                    WHERE s.answer_id = a.id
                ) AS source_count
            FROM answers AS a
            INNER JOIN workspaces AS w
                ON w.id = a.workspace_id
            WHERE a.status = "draft"
            ORDER BY a.created_at ASC, a.id ASC
            '
        );

        $statement->execute();

        return $statement->fetchAll();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function findSourcesForAnswer(int $answerId): array
    {
        $statement = $this->database->prepare(
            '
            SELECT
                document_name,
                section_title,
                page_number,
                excerpt
            FROM answer_sources
            WHERE answer_id = :answer_id
            ORDER BY id ASC
            '
        );

        $statement->execute([
            ':answer_id' => $answerId,
        ]);

        return $statement->fetchAll();
    }
}
