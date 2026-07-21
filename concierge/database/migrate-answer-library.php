<?php

declare(strict_types=1);

require_once __DIR__ . '/connect.php';

$success = false;
$message = '';

try {
    $database = conciergeDatabase();
    $database->exec('PRAGMA foreign_keys = ON;');
    $database->beginTransaction();

    $database->exec(
        '
        CREATE TABLE IF NOT EXISTS answers (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            workspace_id INTEGER NOT NULL,
            public_id TEXT NOT NULL UNIQUE,
            canonical_question TEXT NOT NULL,
            normalized_question TEXT NOT NULL,
            answer_text TEXT NOT NULL,
            status TEXT NOT NULL DEFAULT "draft",
            source_strength TEXT,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,

            FOREIGN KEY (workspace_id)
                REFERENCES workspaces(id)
                ON DELETE CASCADE
        );
        '
    );

    $database->exec(
        '
        CREATE TABLE IF NOT EXISTS questions (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            workspace_id INTEGER NOT NULL,
            public_id TEXT NOT NULL UNIQUE,
            question_text TEXT NOT NULL,
            normalized_question TEXT NOT NULL,
            result_status TEXT NOT NULL DEFAULT "received",
            answer_id INTEGER DEFAULT NULL,
            api_used INTEGER NOT NULL DEFAULT 0
                CHECK (api_used IN (0, 1)),
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,

            FOREIGN KEY (workspace_id)
                REFERENCES workspaces(id)
                ON DELETE CASCADE,

            FOREIGN KEY (answer_id)
                REFERENCES answers(id)
                ON DELETE SET NULL
        );
        '
    );

    $database->exec(
        '
        CREATE TABLE IF NOT EXISTS question_variants (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            workspace_id INTEGER NOT NULL,
            answer_id INTEGER NOT NULL,
            variant_text TEXT NOT NULL,
            normalized_variant TEXT NOT NULL,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,

            FOREIGN KEY (workspace_id)
                REFERENCES workspaces(id)
                ON DELETE CASCADE,

            FOREIGN KEY (answer_id)
                REFERENCES answers(id)
                ON DELETE CASCADE,

            UNIQUE (workspace_id, normalized_variant)
        );
        '
    );

    $database->exec(
        '
        CREATE TABLE IF NOT EXISTS answer_sources (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            answer_id INTEGER NOT NULL,
            knowledge_id INTEGER DEFAULT NULL,
            document_id INTEGER DEFAULT NULL,
            document_name TEXT NOT NULL,
            section_title TEXT,
            page_number INTEGER,
            excerpt TEXT NOT NULL,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,

            FOREIGN KEY (answer_id)
                REFERENCES answers(id)
                ON DELETE CASCADE,

            FOREIGN KEY (knowledge_id)
                REFERENCES knowledge(id)
                ON DELETE SET NULL,

            FOREIGN KEY (document_id)
                REFERENCES documents(id)
                ON DELETE SET NULL
        );
        '
    );

    $indexes = [
        'CREATE INDEX IF NOT EXISTS idx_answers_workspace ON answers(workspace_id);',
        'CREATE INDEX IF NOT EXISTS idx_answers_status ON answers(status);',
        'CREATE INDEX IF NOT EXISTS idx_answers_normalized ON answers(workspace_id, normalized_question);',
        'CREATE INDEX IF NOT EXISTS idx_questions_workspace ON questions(workspace_id);',
        'CREATE INDEX IF NOT EXISTS idx_questions_normalized ON questions(workspace_id, normalized_question);',
        'CREATE INDEX IF NOT EXISTS idx_questions_status ON questions(result_status);',
        'CREATE INDEX IF NOT EXISTS idx_questions_answer ON questions(answer_id);',
        'CREATE INDEX IF NOT EXISTS idx_variants_workspace ON question_variants(workspace_id);',
        'CREATE INDEX IF NOT EXISTS idx_variants_answer ON question_variants(answer_id);',
        'CREATE INDEX IF NOT EXISTS idx_variants_normalized ON question_variants(workspace_id, normalized_variant);',
        'CREATE INDEX IF NOT EXISTS idx_answer_sources_answer ON answer_sources(answer_id);',
        'CREATE INDEX IF NOT EXISTS idx_answer_sources_knowledge ON answer_sources(knowledge_id);',
        'CREATE INDEX IF NOT EXISTS idx_answer_sources_document ON answer_sources(document_id);',
    ];

    foreach ($indexes as $indexSql) {
        $database->exec($indexSql);
    }

    $database->commit();
    $success = true;
    $message = 'Approved-answer library tables created successfully.';
} catch (Throwable $exception) {
    if (
        isset($database)
        && $database instanceof PDO
        && $database->inTransaction()
    ) {
        $database->rollBack();
    }

    error_log(
        'Answer library migration failed: '
        . $exception->getMessage()
    );

    $message = 'The answer-library migration failed. Check the server log.';
}

http_response_code($success ? 200 : 500);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Answer Library Migration</title>
    <style>
        body {
            min-height: 100vh;
            margin: 0;
            padding: 24px;
            display: grid;
            place-items: center;
            background: #f2ede3;
            color: #181816;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
        }

        .card {
            width: min(620px, 100%);
            padding: 40px;
            border-radius: 26px;
            background: rgba(255, 255, 255, 0.72);
            box-shadow: 0 30px 80px rgba(60, 49, 31, 0.12);
        }

        h1 {
            margin: 0 0 18px;
            font-family: Georgia, "Times New Roman", serif;
            font-weight: 400;
        }

        .status {
            padding: 18px;
            border-left: 4px solid <?= $success ? '#426a4b' : '#8a3f3f' ?>;
            background: rgba(255, 255, 255, 0.55);
        }
    </style>
</head>
<body>
<main class="card">
    <h1>Answer library migration</h1>

    <div class="status">
        <strong><?= $success ? 'Migration complete' : 'Migration failed' ?></strong>
        <p><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></p>
    </div>
</main>
</body>
</html>