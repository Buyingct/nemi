<?php

declare(strict_types=1);

final class QuestionLogger
{
    public function __construct(
        private PDO $database
    ) {
    }

    public function create(
        int $workspaceId,
        string $question,
        string $normalizedQuestion
    ): int {
        $statement = $this->database->prepare(
            '
            INSERT INTO questions (
                workspace_id,
                public_id,
                question_text,
                normalized_question,
                result_status,
                api_used
            ) VALUES (
                :workspace_id,
                :public_id,
                :question_text,
                :normalized_question,
                "received",
                0
            )
            '
        );

        $statement->execute([
            ':workspace_id' => $workspaceId,
            ':public_id' => bin2hex(random_bytes(8)),
            ':question_text' => $question,
            ':normalized_question' => $normalizedQuestion,
        ]);

        return (int) $this->database->lastInsertId();
    }

    public function updateStatus(
        int $questionId,
        string $status
    ): void {
        $statement = $this->database->prepare(
            '
            UPDATE questions
            SET result_status = :result_status
            WHERE id = :id
            '
        );

        $statement->execute([
            ':result_status' => $status,
            ':id' => $questionId,
        ]);
    }
}
