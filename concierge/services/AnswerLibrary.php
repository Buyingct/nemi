<?php

declare(strict_types=1);

final class AnswerLibrary
{
    public function __construct(
        private PDO $database
    ) {
    }

    /**
     * @return array<string, mixed>|false
     */
    public function findApprovedExact(
        int $workspaceId,
        string $normalizedQuestion
    ): array|false {
        $statement = $this->database->prepare(
            '
            SELECT
                id,
                public_id,
                canonical_question,
                normalized_question,
                answer_text,
                source_strength
            FROM answers
            WHERE workspace_id = :workspace_id
              AND normalized_question = :normalized_question
              AND status IN ("approved", "corrected")
            ORDER BY updated_at DESC, id DESC
            LIMIT 1
            '
        );

        $statement->execute([
            ':workspace_id' => $workspaceId,
            ':normalized_question' => $normalizedQuestion,
        ]);

        return $statement->fetch();
    }

    /**
     * @return array<string, mixed>|false
     */
    public function findDraftExact(
        int $workspaceId,
        string $normalizedQuestion
    ): array|false {
        $statement = $this->database->prepare(
            '
            SELECT
                id,
                public_id,
                canonical_question,
                normalized_question,
                answer_text,
                source_strength
            FROM answers
            WHERE workspace_id = :workspace_id
              AND normalized_question = :normalized_question
              AND status = "draft"
            ORDER BY updated_at DESC, id DESC
            LIMIT 1
            '
        );

        $statement->execute([
            ':workspace_id' => $workspaceId,
            ':normalized_question' => $normalizedQuestion,
        ]);

        return $statement->fetch();
    }

    /**
     * Creates one draft answer and preserves its supporting source.
     *
     * @param array<string, mixed> $chunk
     *
     * @return array<string, mixed>
     */
    public function createDraftFromDocument(
        int $workspaceId,
        string $question,
        string $normalizedQuestion,
        string $answerText,
        string $sourceStrength,
        array $chunk
    ): array {
        $existingDraft = $this->findDraftExact(
            $workspaceId,
            $normalizedQuestion
        );

        if ($existingDraft) {
            return $existingDraft;
        }

        $this->database->beginTransaction();

        try {
            $publicId = bin2hex(random_bytes(8));

            $answerStatement = $this->database->prepare(
                '
                INSERT INTO answers (
                    workspace_id,
                    public_id,
                    canonical_question,
                    normalized_question,
                    answer_text,
                    status,
                    source_strength
                ) VALUES (
                    :workspace_id,
                    :public_id,
                    :canonical_question,
                    :normalized_question,
                    :answer_text,
                    "draft",
                    :source_strength
                )
                '
            );

            $answerStatement->execute([
                ':workspace_id' => $workspaceId,
                ':public_id' => $publicId,
                ':canonical_question' => $question,
                ':normalized_question' => $normalizedQuestion,
                ':answer_text' => $answerText,
                ':source_strength' => $sourceStrength,
            ]);

            $answerId = (int) $this->database->lastInsertId();

            $sourceStatement = $this->database->prepare(
                '
                INSERT INTO answer_sources (
                    answer_id,
                    knowledge_id,
                    document_id,
                    document_name,
                    section_title,
                    page_number,
                    excerpt
                ) VALUES (
                    :answer_id,
                    :knowledge_id,
                    :document_id,
                    :document_name,
                    :section_title,
                    :page_number,
                    :excerpt
                )
                '
            );

            $sourceStatement->execute([
                ':answer_id' => $answerId,
                ':knowledge_id' => (
                    isset($chunk['id'])
                        ? (int) $chunk['id']
                        : null
                ),
                ':document_id' => (
                    isset($chunk['document_id'])
                        ? (int) $chunk['document_id']
                        : null
                ),
                ':document_name' => (string) (
                    $chunk['original_name'] ?? 'Unknown document'
                ),
                ':section_title' => (
                    trim((string) ($chunk['section_title'] ?? '')) !== ''
                        ? (string) $chunk['section_title']
                        : null
                ),
                ':page_number' => (
                    isset($chunk['page_number'])
                    && (int) $chunk['page_number'] > 0
                        ? (int) $chunk['page_number']
                        : null
                ),
                ':excerpt' => (string) ($chunk['content'] ?? ''),
            ]);

            $this->database->commit();

            return [
                'id' => $answerId,
                'public_id' => $publicId,
                'canonical_question' => $question,
                'normalized_question' => $normalizedQuestion,
                'answer_text' => $answerText,
                'source_strength' => $sourceStrength,
            ];
        } catch (Throwable $exception) {
            if ($this->database->inTransaction()) {
                $this->database->rollBack();
            }

            throw $exception;
        }
    }
}
