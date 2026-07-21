<?php

declare(strict_types=1);

final class WorkspaceRepository
{
    public function __construct(
        private PDO $database
    ) {
    }

    /**
     * @return array<string, mixed>|false
     */
    public function findActiveByPublicId(string $publicId): array|false
    {
        $statement = $this->database->prepare(
            '
            SELECT id, public_id, name
            FROM workspaces
            WHERE public_id = :public_id
              AND status = "active"
            LIMIT 1
            '
        );

        $statement->execute([
            ':public_id' => $publicId,
        ]);

        return $statement->fetch();
    }
}
