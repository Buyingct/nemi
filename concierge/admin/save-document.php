<?php

declare(strict_types=1);

require_once __DIR__ . '/../database/connect.php';
require_once __DIR__ . '/../classes/WorkspaceStorage.php';
require_once __DIR__ . '/../classes/DocumentProcessor.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$workspacePublicId = trim(
    (string) ($_POST['workspace_id'] ?? '')
);

$category = trim(
    (string) ($_POST['category'] ?? 'other')
);

$allowedCategories = [
    'declaration',
    'bylaws',
    'rules',
    'budget',
    'insurance',
    'reserve-study',
    'meeting-minutes',
    'seller-disclosure',
    'inspection',
    'other',
];

if (
    $workspacePublicId === ''
    || !preg_match('/^[a-f0-9]{16}$/', $workspacePublicId)
) {
    http_response_code(400);
    exit('Invalid workspace.');
}

if (!in_array($category, $allowedCategories, true)) {
    $category = 'other';
}

if (
    !isset($_FILES['document'])
    || !is_array($_FILES['document'])
) {
    redirectWithError(
        $workspacePublicId,
        'Please choose a PDF document.'
    );
}

$file = $_FILES['document'];
$uploadError = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);

if ($uploadError !== UPLOAD_ERR_OK) {
    redirectWithError(
        $workspacePublicId,
        uploadErrorMessage($uploadError)
    );
}

$temporaryPath = (string) ($file['tmp_name'] ?? '');
$originalName = trim((string) ($file['name'] ?? ''));
$fileSize = (int) ($file['size'] ?? 0);

if (
    $temporaryPath === ''
    || !is_uploaded_file($temporaryPath)
) {
    redirectWithError(
        $workspacePublicId,
        'The uploaded file could not be verified.'
    );
}

if ($originalName === '') {
    redirectWithError(
        $workspacePublicId,
        'The uploaded file does not have a valid name.'
    );
}

$maximumFileSize = 50 * 1024 * 1024;

if ($fileSize <= 0 || $fileSize > $maximumFileSize) {
    redirectWithError(
        $workspacePublicId,
        'The PDF must be smaller than 50 MB.'
    );
}

$extension = strtolower(
    pathinfo($originalName, PATHINFO_EXTENSION)
);

if ($extension !== 'pdf') {
    redirectWithError(
        $workspacePublicId,
        'Only PDF documents are allowed.'
    );
}

$finfo = new finfo(FILEINFO_MIME_TYPE);
$mimeType = (string) $finfo->file($temporaryPath);

$allowedMimeTypes = [
    'application/pdf',
    'application/x-pdf',
];

if (!in_array($mimeType, $allowedMimeTypes, true)) {
    redirectWithError(
        $workspacePublicId,
        'The selected file is not a valid PDF.'
    );
}

$handle = fopen($temporaryPath, 'rb');

if ($handle === false) {
    redirectWithError(
        $workspacePublicId,
        'The uploaded PDF could not be read.'
    );
}

$signature = fread($handle, 5);
fclose($handle);

if ($signature !== '%PDF-') {
    redirectWithError(
        $workspacePublicId,
        'The selected file is not a valid PDF.'
    );
}

$storedName = bin2hex(random_bytes(16)) . '.pdf';
$documentPublicId = bin2hex(random_bytes(8));
$destinationPath = '';
$database = null;

try {
    $database = conciergeDatabase();

    $workspaceStatement = $database->prepare(
        '
        SELECT
            id,
            public_id,
            name
        FROM workspaces
        WHERE public_id = :public_id
        LIMIT 1
        '
    );

    $workspaceStatement->execute([
        ':public_id' => $workspacePublicId,
    ]);

    $workspace = $workspaceStatement->fetch();

    if (!$workspace) {
        http_response_code(404);
        exit('Workspace not found.');
    }

    $storage = new WorkspaceStorage();

    $documentsPath = $storage->documentsPath(
        $workspacePublicId
    );

    $categoryPath = $documentsPath . '/' . $category;

    if (
        !is_dir($categoryPath)
        && !mkdir($categoryPath, 0775, true)
        && !is_dir($categoryPath)
    ) {
        throw new RuntimeException(
            'The document category folder could not be created.'
        );
    }

    $destinationPath = $categoryPath . '/' . $storedName;

    if (!move_uploaded_file($temporaryPath, $destinationPath)) {
        throw new RuntimeException(
            'The uploaded PDF could not be saved.'
        );
    }

    chmod($destinationPath, 0664);

    $database->beginTransaction();

    $insert = $database->prepare(
        '
        INSERT INTO documents (
            workspace_id,
            public_id,
            original_name,
            stored_name,
            category,
            mime_type,
            file_size,
            status
        ) VALUES (
            :workspace_id,
            :public_id,
            :original_name,
            :stored_name,
            :category,
            :mime_type,
            :file_size,
            :status
        )
        '
    );

    $insert->execute([
        ':workspace_id' => (int) $workspace['id'],
        ':public_id' => $documentPublicId,
        ':original_name' => $originalName,
        ':stored_name' => $storedName,
        ':category' => $category,
        ':mime_type' => $mimeType,
        ':file_size' => $fileSize,
        ':status' => 'processing',
    ]);

    $documentId = (int) $database->lastInsertId();

    $processor = new DocumentProcessor($storage);

    $chunks = $processor->process(
        $workspacePublicId,
        $destinationPath,
        $documentPublicId
    );

    if ($chunks === []) {
        throw new RuntimeException(
            'No searchable text chunks were created.'
        );
    }

    $knowledgeInsert = $database->prepare(
        '
        INSERT INTO knowledge (
            workspace_id,
            document_id,
            section_title,
            page_number,
            content
        ) VALUES (
            :workspace_id,
            :document_id,
            :section_title,
            :page_number,
            :content
        )
        '
    );

    foreach ($chunks as $chunk) {
        $knowledgeInsert->execute([
            ':workspace_id' => (int) $workspace['id'],
            ':document_id' => $documentId,
            ':section_title' => $chunk['section_title'],
            ':page_number' => $chunk['page_number'],
            ':content' => $chunk['content'],
        ]);
    }

    $statusUpdate = $database->prepare(
        '
        UPDATE documents
        SET status = :status
        WHERE id = :document_id
        '
    );

    $statusUpdate->execute([
        ':status' => 'ready',
        ':document_id' => $documentId,
    ]);

    $database->commit();

    header(
        'Location: workspace.php?id='
        . urlencode($workspacePublicId)
        . '&uploaded=1'
    );
    exit;
} catch (Throwable $exception) {
    if (
        $database instanceof PDO
        && $database->inTransaction()
    ) {
        $database->rollBack();
    }

    if (
        $destinationPath !== ''
        && is_file($destinationPath)
    ) {
        unlink($destinationPath);
    }

    error_log(
        'Document upload or processing failed: '
        . $exception->getMessage()
    );

    redirectWithError(
        $workspacePublicId,
        'The document could not be processed. Please try again.'
    );
}

function redirectWithError(
    string $workspacePublicId,
    string $message
): never {
    header(
        'Location: upload-document.php?id='
        . urlencode($workspacePublicId)
        . '&error='
        . urlencode($message)
    );

    exit;
}

function uploadErrorMessage(int $uploadError): string
{
    return match ($uploadError) {
        UPLOAD_ERR_INI_SIZE,
        UPLOAD_ERR_FORM_SIZE =>
            'The PDF is larger than the server allows.',

        UPLOAD_ERR_PARTIAL =>
            'The PDF upload was interrupted. Please try again.',

        UPLOAD_ERR_NO_FILE =>
            'Please choose a PDF document.',

        UPLOAD_ERR_NO_TMP_DIR =>
            'The server upload folder is unavailable.',

        UPLOAD_ERR_CANT_WRITE =>
            'The server could not save the uploaded PDF.',

        UPLOAD_ERR_EXTENSION =>
            'The server stopped the PDF upload.',

        default =>
            'The PDF could not be uploaded.',
    };
}