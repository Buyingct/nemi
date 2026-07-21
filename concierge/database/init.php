<?php

declare(strict_types=1);

require_once __DIR__ . '/connect.php';

$success = false;
$message = '';

/**
 * Returns true when a column already exists on a SQLite table.
 */
function conciergeColumnExists(
    PDO $database,
    string $tableName,
    string $columnName
): bool {
    $statement = $database->query(
        'PRAGMA table_info(' . $tableName . ')'
    );

    if ($statement === false) {
        return false;
    }

    $columns = $statement->fetchAll(PDO::FETCH_ASSOC);

    foreach ($columns as $column) {
        if (
            isset($column['name'])
            && $column['name'] === $columnName
        ) {
            return true;
        }
    }

    return false;
}

/**
 * Adds a column only when it does not already exist.
 */
function conciergeAddColumnIfMissing(
    PDO $database,
    string $tableName,
    string $columnName,
    string $columnDefinition
): void {
    if (
        conciergeColumnExists(
            $database,
            $tableName,
            $columnName
        )
    ) {
        return;
    }

    $database->exec(
        sprintf(
            'ALTER TABLE %s ADD COLUMN %s %s;',
            $tableName,
            $columnName,
            $columnDefinition
        )
    );
}

try {
    $database = conciergeDatabase();

    $database->exec('PRAGMA foreign_keys = ON;');

    /*
     * Workspaces
     */
    $database->exec(
        '
        CREATE TABLE IF NOT EXISTS workspaces (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            public_id TEXT NOT NULL UNIQUE,
            name TEXT NOT NULL,
            address TEXT,
            city TEXT,
            state TEXT NOT NULL DEFAULT "CT",
            postal_code TEXT,
            status TEXT NOT NULL DEFAULT "active",
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        );
        '
    );

    $database->exec(
        '
        CREATE INDEX IF NOT EXISTS idx_workspaces_public_id
        ON workspaces(public_id);
        '
    );

    $database->exec(
        '
        CREATE INDEX IF NOT EXISTS idx_workspaces_status
        ON workspaces(status);
        '
    );

    /*
     * Documents
     *
     * document_type:
     *     The uploaded file format: pdf, md, or txt.
     *
     * knowledge_source:
     *     Describes what the Concierge is allowed to answer from:
     *     native_pdf, verified_markdown, verified_text, or none.
     *
     * original_document_id:
     *     Links a verified Markdown/text document back to the original PDF.
     *     It remains NULL when the document is itself the original source.
     */
    $database->exec(
        '
        CREATE TABLE IF NOT EXISTS documents (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            workspace_id INTEGER NOT NULL,
            public_id TEXT NOT NULL UNIQUE,
            original_name TEXT NOT NULL,
            stored_name TEXT NOT NULL,
            category TEXT NOT NULL DEFAULT "other",
            mime_type TEXT NOT NULL,
            file_size INTEGER NOT NULL DEFAULT 0,
            document_type TEXT NOT NULL DEFAULT "pdf",
            knowledge_source TEXT NOT NULL DEFAULT "native_pdf",
            original_document_id INTEGER DEFAULT NULL,
            status TEXT NOT NULL DEFAULT "uploaded",
            uploaded_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,

            FOREIGN KEY (workspace_id)
                REFERENCES workspaces(id)
                ON DELETE CASCADE,

            FOREIGN KEY (original_document_id)
                REFERENCES documents(id)
                ON DELETE SET NULL
        );
        '
    );

    /*
     * Safe migration for existing databases.
     * SQLite CREATE TABLE IF NOT EXISTS does not add newly introduced columns,
     * so each missing column is added separately.
     */
    conciergeAddColumnIfMissing(
        $database,
        'documents',
        'document_type',
        'TEXT NOT NULL DEFAULT "pdf"'
    );

    conciergeAddColumnIfMissing(
        $database,
        'documents',
        'knowledge_source',
        'TEXT NOT NULL DEFAULT "native_pdf"'
    );

    conciergeAddColumnIfMissing(
        $database,
        'documents',
        'original_document_id',
        'INTEGER DEFAULT NULL REFERENCES documents(id) ON DELETE SET NULL'
    );

    /*
     * Preserve sensible values for previously uploaded records.
     */
    $database->exec(
        '
        UPDATE documents
        SET document_type = "pdf"
        WHERE document_type IS NULL
           OR TRIM(document_type) = "";
        '
    );

    $database->exec(
        '
        UPDATE documents
        SET knowledge_source = CASE
            WHEN status = "needs_digitization" THEN "none"
            ELSE "native_pdf"
        END
        WHERE knowledge_source IS NULL
           OR TRIM(knowledge_source) = "";
        '
    );

    $database->exec(
        '
        CREATE INDEX IF NOT EXISTS idx_documents_workspace_id
        ON documents(workspace_id);
        '
    );

    $database->exec(
        '
        CREATE INDEX IF NOT EXISTS idx_documents_category
        ON documents(category);
        '
    );

    $database->exec(
        '
        CREATE INDEX IF NOT EXISTS idx_documents_status
        ON documents(status);
        '
    );

    $database->exec(
        '
        CREATE INDEX IF NOT EXISTS idx_documents_type
        ON documents(document_type);
        '
    );

    $database->exec(
        '
        CREATE INDEX IF NOT EXISTS idx_documents_knowledge_source
        ON documents(knowledge_source);
        '
    );

    $database->exec(
        '
        CREATE INDEX IF NOT EXISTS idx_documents_original_document
        ON documents(original_document_id);
        '
    );

    /*
     * Knowledge
     */
    $database->exec(
        '
        CREATE TABLE IF NOT EXISTS knowledge (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            workspace_id INTEGER NOT NULL,
            document_id INTEGER NOT NULL,
            section_title TEXT,
            page_number INTEGER,
            content TEXT NOT NULL,
            embedding TEXT,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,

            FOREIGN KEY (workspace_id)
                REFERENCES workspaces(id)
                ON DELETE CASCADE,

            FOREIGN KEY (document_id)
                REFERENCES documents(id)
                ON DELETE CASCADE
        );
        '
    );

    $database->exec(
        '
        CREATE INDEX IF NOT EXISTS idx_knowledge_workspace
        ON knowledge(workspace_id);
        '
    );

    $database->exec(
        '
        CREATE INDEX IF NOT EXISTS idx_knowledge_document
        ON knowledge(document_id);
        '
    );

    $success = true;

    $message = (
        'Database updated successfully. Document type, knowledge source, '
        . 'and original-document linking are ready.'
    );
} catch (Throwable $exception) {
    error_log(
        'Concierge database initialization failed: '
        . $exception->getMessage()
    );

    $message = (
        'The database could not be initialized or migrated. '
        . 'Check the server logs.'
    );
}

http_response_code($success ? 200 : 500);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >

    <meta name="robots" content="noindex, nofollow">

    <title>Concierge Setup</title>

    <style>
        :root {
            --ivory: #f2ede3;
            --paper: rgba(255, 255, 255, 0.72);
            --ink: #181816;
            --muted: #6f6b62;
            --gold: #9a7740;
            --success: #426a4b;
            --error: #8a3f3f;
        }

        * {
            box-sizing: border-box;
        }

        body {
            min-height: 100vh;
            margin: 0;
            padding: 24px;
            display: grid;
            place-items: center;
            color: var(--ink);
            background:
                radial-gradient(
                    circle at top left,
                    rgba(205, 183, 140, 0.35),
                    transparent 42%
                ),
                var(--ivory);
            font-family:
                -apple-system,
                BlinkMacSystemFont,
                "Segoe UI",
                sans-serif;
        }

        .setup-card {
            width: min(620px, 100%);
            padding: 42px;
            border: 1px solid rgba(255, 255, 255, 0.7);
            border-radius: 28px;
            background: var(--paper);
            box-shadow: 0 30px 80px rgba(60, 49, 31, 0.12);
            backdrop-filter: blur(24px);
            -webkit-backdrop-filter: blur(24px);
        }

        .eyebrow {
            margin: 0 0 18px;
            color: var(--gold);
            font-size: 0.72rem;
            font-weight: 700;
            letter-spacing: 0.17em;
            text-transform: uppercase;
        }

        h1 {
            margin: 0;
            font-family: Georgia, "Times New Roman", serif;
            font-size: clamp(2.3rem, 7vw, 4rem);
            font-weight: 400;
            line-height: 1;
            letter-spacing: -0.04em;
        }

        .status {
            margin-top: 30px;
            padding: 18px 20px;
            border-radius: 15px;
            background: rgba(255, 255, 255, 0.55);
        }

        .status.success {
            border-left: 4px solid var(--success);
        }

        .status.error {
            border-left: 4px solid var(--error);
        }

        .status strong,
        .status span {
            display: block;
        }

        .status span {
            margin-top: 6px;
            color: var(--muted);
            line-height: 1.6;
        }

        .details {
            margin-top: 25px;
            color: var(--muted);
            font-size: 0.86rem;
            line-height: 1.7;
        }

        a {
            display: inline-block;
            margin-top: 24px;
            color: var(--ink);
            text-underline-offset: 5px;
        }
    </style>
</head>

<body>

<main class="setup-card">

    <p class="eyebrow">Rocha Circle Concierge</p>

    <h1>Database setup</h1>

    <div class="status <?= $success ? 'success' : 'error' ?>">

        <strong>
            <?= $success ? 'Setup complete' : 'Setup failed' ?>
        </strong>

        <span>
            <?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?>
        </span>

    </div>

    <?php if ($success): ?>

        <div class="details">
            <strong>Created, verified, or migrated:</strong><br>
            SQLite database<br>
            Workspaces table<br>
            Documents table<br>
            Document type field<br>
            Knowledge source field<br>
            Original-document relationship<br>
            Knowledge table<br>
            Database indexes
        </div>

        <a href="../">
            Return to Document Concierge
        </a>

    <?php endif; ?>

</main>

</body>
</html>