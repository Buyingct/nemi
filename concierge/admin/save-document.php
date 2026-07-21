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
        'Please choose a PDF, Markdown, or text document.'
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
        'The document must be smaller than 50 MB.'
    );
}

$extension = strtolower(
    pathinfo($originalName, PATHINFO_EXTENSION)
);

if ($extension === 'markdown') {
    $extension = 'md';
}

$allowedExtensions = [
    'pdf',
    'md',
    'txt',
];

if (!in_array($extension, $allowedExtensions, true)) {
    redirectWithError(
        $workspacePublicId,
        'Supported formats are PDF, Markdown (.md), and Text (.txt).'
    );
}

$finfo = new finfo(FILEINFO_MIME_TYPE);
$detectedMimeType = $finfo->file($temporaryPath);

$mimeType = is_string($detectedMimeType)
    ? strtolower(trim($detectedMimeType))
    : '';

$allowedMimeTypesByExtension = [
    'pdf' => [
        'application/pdf',
        'application/x-pdf',
    ],
    'md' => [
        'text/plain',
        'text/markdown',
        'text/x-markdown',
        'application/octet-stream',
    ],
    'txt' => [
        'text/plain',
        'application/octet-stream',
    ],
];

if (
    !isset($allowedMimeTypesByExtension[$extension])
    || !in_array(
        $mimeType,
        $allowedMimeTypesByExtension[$extension],
        true
    )
) {
    redirectWithError(
        $workspacePublicId,
        'The selected file does not match its file extension.'
    );
}

/*
 * PDFs receive an additional signature check. Markdown and text files are
 * validated as readable text below.
 */
if ($extension === 'pdf') {
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
} else {
    $sample = file_get_contents(
        $temporaryPath,
        false,
        null,
        0,
        8192
    );

    if ($sample === false || trim($sample) === '') {
        redirectWithError(
            $workspacePublicId,
            'The selected text document is empty or unreadable.'
        );
    }

    if (str_contains($sample, "\0")) {
        redirectWithError(
            $workspacePublicId,
            'The selected file does not appear to be a plain-text document.'
        );
    }
}

/*
 * The filename helps detect a category when the form submitted "other."
 * A manually selected category always takes priority.
 */
if ($category === 'other') {
    $detectedCategory = detectCategoryFromFilename(
        $originalName
    );

    if ($detectedCategory !== null) {
        $category = $detectedCategory;
    }
}

$documentType = $extension;

$knowledgeSource = match ($documentType) {
    'md' => 'verified_markdown',
    'txt' => 'verified_text',
    'pdf' => 'native_pdf',
    default => 'none',
};

$storedName = bin2hex(random_bytes(16))
    . '.'
    . $documentType;

$documentPublicId = bin2hex(random_bytes(8));
$destinationPath = '';
$database = null;
$documentId = null;

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
            'The uploaded document could not be saved.'
        );
    }

    @chmod($destinationPath, 0664);

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
            document_type,
            knowledge_source,
            original_document_id,
            status
        ) VALUES (
            :workspace_id,
            :public_id,
            :original_name,
            :stored_name,
            :category,
            :mime_type,
            :file_size,
            :document_type,
            :knowledge_source,
            :original_document_id,
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
        ':document_type' => $documentType,
        ':knowledge_source' => $knowledgeSource,
        ':original_document_id' => null,
        ':status' => 'processing',
    ]);

    $documentId = (int) $database->lastInsertId();

    $processor = new DocumentProcessor($storage);

    try {
        $chunks = $processor->process(
            $workspacePublicId,
            $destinationPath,
            $documentPublicId
        );
    } catch (Throwable $processingException) {
        if (
            $documentType === 'pdf'
            && str_starts_with(
                $processingException->getMessage(),
                'NEEDS_DIGITIZATION:'
            )
        ) {
            $statusUpdate = $database->prepare(
                '
                UPDATE documents
                SET
                    status = :status,
                    knowledge_source = :knowledge_source
                WHERE id = :document_id
                '
            );

            $statusUpdate->execute([
                ':status' => 'needs_digitization',
                ':knowledge_source' => 'none',
                ':document_id' => $documentId,
            ]);

            $database->commit();

            header(
                'Location: workspace.php?id='
                . urlencode($workspacePublicId)
                . '&needs_digitization=1'
            );
            exit;
        }

        throw $processingException;
    }

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
        @unlink($destinationPath);
    }

    error_log(
        'Document upload or processing failed: '
        . $exception->getMessage()
    );

    redirectWithError(
        $workspacePublicId,
        safeProcessingErrorMessage($exception)
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
            'The document is larger than the server allows.',

        UPLOAD_ERR_PARTIAL =>
            'The document upload was interrupted. Please try again.',

        UPLOAD_ERR_NO_FILE =>
            'Please choose a PDF, Markdown, or text document.',

        UPLOAD_ERR_NO_TMP_DIR =>
            'The server upload folder is unavailable.',

        UPLOAD_ERR_CANT_WRITE =>
            'The server could not save the uploaded document.',

        UPLOAD_ERR_EXTENSION =>
            'The server stopped the document upload.',

        default =>
            'The document could not be uploaded.',
    };
}

function safeProcessingErrorMessage(
    Throwable $exception
): string {
    $message = $exception->getMessage();

    $safeMessages = [
        'The source document could not be found.',
        'The PDF text extractor is unavailable.',
        'The uploaded text document could not be read.',
        'The uploaded text document is empty.',
        'The uploaded text document does not contain enough readable text.',
        'No readable text was found in the PDF.',
        'No searchable text chunks were created.',
        'Unsupported document type. Please upload a PDF, MD, or TXT file.',
    ];

    if (in_array($message, $safeMessages, true)) {
        return $message;
    }

    return (
        'The document could not be processed. '
        . 'Please confirm that it is a valid PDF, Markdown, or text file.'
    );
}

function detectCategoryFromFilename(
    string $filename
): ?string {
    $name = strtolower(
        pathinfo($filename, PATHINFO_FILENAME)
    );

    $name = preg_replace(
        '/[_-]+/',
        ' ',
        $name
    ) ?? $name;

    $rules = [
        'declaration' => [
            '/\bdeclaration\b/',
            '/\bmaster deed\b/',
        ],
        'bylaws' => [
            '/\bbylaws?\b/',
            '/\bby laws?\b/',
        ],
        'rules' => [
            '/\brules?\b/',
            '/\bregulations?\b/',
        ],
        'budget' => [
            '/\bbudget\b/',
            '/\bfinancial statement\b/',
            '/\bincome and expense\b/',
        ],
        'insurance' => [
            '/\binsurance\b/',
            '/\bcertificate of insurance\b/',
        ],
        'reserve-study' => [
            '/\breserve study\b/',
            '/\breserve analysis\b/',
        ],
        'meeting-minutes' => [
            '/\bmeeting minutes\b/',
            '/\bminutes\b/',
        ],
        'seller-disclosure' => [
            '/\bseller disclosure\b/',
            '/\bproperty disclosure\b/',
        ],
        'inspection' => [
            '/\binspection report\b/',
            '/\binspection\b/',
        ],
    ];

    foreach ($rules as $category => $patterns) {
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $name) === 1) {
                return $category;
            }
        }
    }

    return null;
}