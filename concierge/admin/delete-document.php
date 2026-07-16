<?php

declare(strict_types=1);

require_once __DIR__ . '/../database/connect.php';
require_once __DIR__ . '/../classes/WorkspaceStorage.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$workspacePublicId = strtolower(
    trim((string) ($_POST['workspace_id'] ?? ''))
);

$documentPublicId = strtolower(
    trim((string) ($_POST['document_id'] ?? ''))
);

if (
    !preg_match('/^[a-f0-9]{16}$/', $workspacePublicId)
    || !preg_match('/^[a-f0-9]{16}$/', $documentPublicId)
) {
    http_response_code(400);
    exit('Invalid deletion request.');
}

$database = null;
$movedFiles = [];

try {
    $database = conciergeDatabase();

    $statement = $database->prepare(
        '
        SELECT
            d.id,
            d.public_id,
            d.original_name,
            d.stored_name,
            d.category,
            w.public_id AS workspace_public_id
        FROM documents d
        INNER JOIN workspaces w
            ON w.id = d.workspace_id
        WHERE d.public_id = :document_public_id
          AND w.public_id = :workspace_public_id
        LIMIT 1
        '
    );

    $statement->execute([
        ':document_public_id' => $documentPublicId,
        ':workspace_public_id' => $workspacePublicId,
    ]);

    $document = $statement->fetch();

    if (!$document) {
        http_response_code(404);
        exit('Document not found.');
    }

    $storage = new WorkspaceStorage();

    $folders = $storage->createWorkspaceFolders(
        $workspacePublicId
    );

    $pdfPath = $folders['documents']
        . '/'
        . (string) $document['category']
        . '/'
        . (string) $document['stored_name'];

    $extractedTextPath = $folders['extracted']
        . '/'
        . (string) $document['public_id']
        . '.txt';

    /*
     * Move files to the private temporary folder first.
     * If the database deletion fails, they can be restored.
     */
    $filesToMove = [
        $pdfPath,
        $extractedTextPath,
    ];

    foreach ($filesToMove as $filePath) {
        if (!is_file($filePath)) {
            continue;
        }

        $temporaryPath = $folders['temporary']
            . '/'
            . bin2hex(random_bytes(12))
            . '-'
            . basename($filePath);

        if (!rename($filePath, $temporaryPath)) {
            throw new RuntimeException(
                'A document file could not be prepared for deletion.'
            );
        }

        $movedFiles[] = [
            'original' => $filePath,
            'temporary' => $temporaryPath,
        ];
    }

    $database->beginTransaction();

    /*
     * Knowledge records are removed automatically because
     * the knowledge table uses ON DELETE CASCADE.
     */
    $delete = $database->prepare(
        '
        DELETE FROM documents
        WHERE id = :document_id
        '
    );

    $delete->execute([
        ':document_id' => (int) $document['id'],
    ]);

    if ($delete->rowCount() !== 1) {
        throw new RuntimeException(
            'The document database record was not deleted.'
        );
    }

    $database->commit();

    /*
     * Database deletion succeeded, so permanently remove
     * the files that were moved into the temporary folder.
     */
    foreach ($movedFiles as $movedFile) {
        if (is_file($movedFile['temporary'])) {
            unlink($movedFile['temporary']);
        }
    }

    header(
        'Location: workspace.php?id='
        . urlencode($workspacePublicId)
        . '&deleted=1'
    );

    exit;
} catch (Throwable $exception) {
    if (
        $database instanceof PDO
        && $database->inTransaction()
    ) {
        $database->rollBack();
    }

    /*
     * Restore files if the database operation failed.
     */
    foreach (array_reverse($movedFiles) as $movedFile) {
        if (
            is_file($movedFile['temporary'])
            && !is_file($movedFile['original'])
        ) {
            rename(
                $movedFile['temporary'],
                $movedFile['original']
            );
        }
    }

    error_log(
        'Document deletion failed: '
        . $exception->getMessage()
    );

    header(
        'Location: workspace.php?id='
        . urlencode($workspacePublicId)
        . '&delete_error=1'
    );

    exit;
}