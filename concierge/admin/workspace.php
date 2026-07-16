<?php

declare(strict_types=1);

require_once __DIR__ . '/../database/connect.php';
require_once __DIR__ . '/../classes/WorkspaceStorage.php';

$publicId = trim((string) ($_GET['id'] ?? ''));

if ($publicId === '') {
    http_response_code(400);
    exit('Workspace ID is required.');
}

$workspace = null;
$documents = [];
$uploaded = isset($_GET['uploaded']) && $_GET['uploaded'] === '1';
$deleted = isset($_GET['deleted']) && $_GET['deleted'] === '1';
$deleteError = isset($_GET['delete_error']);
$error = '';

try {
    $database = conciergeDatabase();

    $statement = $database->prepare(
        '
        SELECT
            id,
            public_id,
            name,
            address,
            city,
            state,
            postal_code,
            status,
            created_at
        FROM workspaces
        WHERE public_id = :public_id
        LIMIT 1
        '
    );

    $statement->execute([
        ':public_id' => $publicId,
    ]);

    $workspace = $statement->fetch();

    if (!$workspace) {
        http_response_code(404);
        exit('Workspace not found.');
    }

    $storage = new WorkspaceStorage();

    $storage->createWorkspaceFolders(
        (string) $workspace['public_id']
    );

    $documentsStatement = $database->prepare(
        '
        SELECT
            d.id,
            d.public_id,
            d.original_name,
            d.category,
            d.uploaded_at,
            d.file_size,
            d.status,
            COUNT(k.id) AS chunk_count,
            MIN(k.page_number) AS first_page,
            MAX(k.page_number) AS last_page
        FROM documents d
        LEFT JOIN knowledge k
            ON k.document_id = d.id
        WHERE d.workspace_id = :workspace_id
        GROUP BY
            d.id,
            d.public_id,
            d.original_name,
            d.category,
            d.uploaded_at,
            d.file_size,
            d.status
        ORDER BY d.uploaded_at DESC, d.id DESC
        '
    );

    $documentsStatement->execute([
        ':workspace_id' => (int) $workspace['id'],
    ]);

    $documents = $documentsStatement->fetchAll();
} catch (Throwable $exception) {
    error_log(
        'Workspace detail failed: '
        . $exception->getMessage()
    );

    $error = 'This workspace could not be loaded right now.';
}

$locationParts = [];

if ($workspace) {
    $locationParts = array_filter([
        $workspace['address'] ?? null,
        $workspace['city'] ?? null,
        $workspace['state'] ?? null,
        $workspace['postal_code'] ?? null,
    ]);
}

$location = implode(', ', $locationParts);

function formatFileSize(int $bytes): string
{
    if ($bytes >= 1024 * 1024) {
        return number_format($bytes / (1024 * 1024), 1) . ' MB';
    }

    if ($bytes >= 1024) {
        return number_format($bytes / 1024, 1) . ' KB';
    }

    return $bytes . ' bytes';
}

function formatCategory(string $category): string
{
    return ucwords(str_replace('-', ' ', $category));
}

function formatPageCoverage(
    mixed $firstPage,
    mixed $lastPage
): string {
    if ($firstPage === null || $lastPage === null) {
        return 'No page data';
    }

    $first = (int) $firstPage;
    $last = (int) $lastPage;

    if ($first === $last) {
        return 'Page ' . $first;
    }

    return 'Pages ' . $first . '–' . $last;
}
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

    <title>
        <?= htmlspecialchars(
            (string) ($workspace['name'] ?? 'Workspace'),
            ENT_QUOTES,
            'UTF-8'
        ) ?>
        | Rocha Circle Concierge
    </title>

    <style>
        :root {
            --ivory: #f2ede3;
            --paper: rgba(255, 255, 255, 0.68);
            --paper-strong: rgba(255, 255, 255, 0.82);
            --ink: #181816;
            --muted: #6f6b62;
            --gold: #9a7740;
            --line: rgba(45, 41, 34, 0.12);
            --success: #426a4b;
            --warning: #8a6d2f;
            --error: #8a3f3f;
        }

        * {
            box-sizing: border-box;
        }

        body {
            min-height: 100vh;
            margin: 0;
            padding: 24px;
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

        a {
            color: inherit;
        }

        .shell {
            width: min(1180px, 100%);
            margin: 48px auto;
        }

        .topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
        }

        .back-link {
            color: var(--muted);
            text-underline-offset: 5px;
        }

        .status {
            padding: 8px 11px;
            border: 1px solid rgba(66, 106, 75, 0.18);
            border-radius: 999px;
            color: var(--success);
            background: rgba(66, 106, 75, 0.06);
            font-size: 0.7rem;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        .hero {
            margin-top: 62px;
            max-width: 900px;
        }

        .eyebrow,
        .section-label,
        .document-label {
            margin: 0;
            color: var(--gold);
            font-size: 0.72rem;
            font-weight: 700;
            letter-spacing: 0.18em;
            text-transform: uppercase;
        }

     .document-actions {
    display: grid;
    justify-items: end;
    gap: 10px;
}

.document-actions form {
    margin: 0;
}

.delete-button {
    padding: 0;
    border: 0;
    color: var(--error);
    background: transparent;
    font: inherit;
    font-size: 0.76rem;
    text-decoration: underline;
    text-underline-offset: 4px;
    cursor: pointer;
}

.delete-button:hover {
    text-decoration-thickness: 2px;
}

        h1 {
            margin: 16px 0 0;
            font-family: Georgia, "Times New Roman", serif;
            font-size: clamp(3.2rem, 8vw, 6.2rem);
            font-weight: 400;
            line-height: 0.95;
            letter-spacing: -0.055em;
        }

        .location {
            margin: 22px 0 0;
            color: var(--muted);
            font-size: 1.02rem;
            line-height: 1.7;
        }

        .notice,
        .error {
            margin-top: 28px;
            padding: 16px 18px;
            border-radius: 14px;
            background: rgba(255, 255, 255, 0.5);
        }

        .notice {
            border-left: 4px solid var(--success);
        }

        .error {
            border-left: 4px solid var(--error);
            color: var(--error);
        }

        .workspace-grid {
            margin-top: 46px;
            display: grid;
            grid-template-columns: minmax(0, 1.5fr) minmax(280px, 0.7fr);
            gap: 22px;
            align-items: start;
        }

        .panel {
            padding: 30px;
            border: 1px solid rgba(255, 255, 255, 0.72);
            border-radius: 30px;
            background: var(--paper);
            box-shadow: 0 30px 90px rgba(60, 49, 31, 0.12);
            backdrop-filter: blur(28px);
            -webkit-backdrop-filter: blur(28px);
        }

        .panel-heading {
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            gap: 20px;
        }

        .panel-heading h2 {
            margin: 10px 0 0;
            font-family: Georgia, "Times New Roman", serif;
            font-size: 2rem;
            font-weight: 400;
        }

        .primary-button {
            min-height: 48px;
            padding: 0 18px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 999px;
            color: #fff;
            background: var(--ink);
            text-decoration: none;
        }

        .empty-state {
            margin-top: 24px;
            padding: 54px 24px;
            text-align: center;
            border: 1px dashed var(--line);
            border-radius: 18px;
            background: rgba(255, 255, 255, 0.32);
        }

        .empty-state h3 {
            margin: 0;
            font-family: Georgia, "Times New Roman", serif;
            font-size: 1.7rem;
            font-weight: 400;
        }

        .empty-state p {
            max-width: 520px;
            margin: 13px auto 0;
            color: var(--muted);
            line-height: 1.65;
        }

        .empty-state .primary-button {
            margin-top: 22px;
        }

        .document-list {
            margin-top: 24px;
            display: grid;
            gap: 12px;
        }

        .document-card {
            padding: 20px;
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            gap: 20px;
            align-items: center;
            border: 1px solid var(--line);
            border-radius: 18px;
            background: var(--paper-strong);
        }

        .document-card h3 {
            margin: 8px 0 0;
            overflow-wrap: anywhere;
            font-family: Georgia, "Times New Roman", serif;
            font-size: 1.28rem;
            font-weight: 400;
        }

        .document-meta {
            margin: 9px 0 0;
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            color: var(--muted);
            font-size: 0.76rem;
        }

        .document-status {
            padding: 8px 11px;
            border-radius: 999px;
            font-size: 0.68rem;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        .document-status.ready {
            border: 1px solid rgba(66, 106, 75, 0.18);
            color: var(--success);
            background: rgba(66, 106, 75, 0.06);
        }

        .document-status.processing {
            border: 1px solid rgba(138, 109, 47, 0.18);
            color: var(--warning);
            background: rgba(138, 109, 47, 0.07);
        }

        .summary-list {
            margin-top: 24px;
            display: grid;
            gap: 12px;
        }

        .summary-item {
            padding: 16px 0;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            border-bottom: 1px solid var(--line);
        }

        .summary-item:last-child {
            border-bottom: 0;
        }

        .summary-item span:first-child {
            color: var(--muted);
        }

        .summary-item strong {
            font-weight: 600;
            text-align: right;
        }

        @media (max-width: 860px) {
            .workspace-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 640px) {
            .shell {
                margin: 26px auto;
            }

            .topbar,
            .panel-heading {
                align-items: stretch;
                flex-direction: column;
            }

            .primary-button {
                width: 100%;
            }

            .panel {
                padding: 22px;
            }

            .document-card {
                grid-template-columns: 1fr;
            }

            .document-status {
                justify-self: start;
            }
            .document-actions {
    justify-items: start;
}
        }
    </style>
</head>

<body>

<main class="shell">

    <header class="topbar">

        <a class="back-link" href="index.php">
            ← Return to workspaces
        </a>

        <?php if ($workspace): ?>

            <span class="status">
                <?= htmlspecialchars(
                    (string) $workspace['status'],
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>
            </span>

        <?php endif; ?>

    </header>

    <?php if ($uploaded): ?>

        <div class="notice">
            Document uploaded and processed successfully.
        </div>

    <?php endif; ?>

    <?php if ($deleted): ?>

    <div class="notice">
        Document and its knowledge entries were deleted successfully.
    </div>

<?php endif; ?>

<?php if ($deleteError): ?>

    <div class="error">
        The document could not be deleted. Please try again.
    </div>

<?php endif; ?>

    <?php if ($error !== ''): ?>

        <div class="error">
            <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
        </div>

    <?php endif; ?>

    <?php if ($workspace): ?>

        <section class="hero">

            <p class="eyebrow">Property Workspace</p>

            <h1>
                <?= htmlspecialchars(
                    (string) $workspace['name'],
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>
            </h1>

            <?php if ($location !== ''): ?>

                <p class="location">
                    <?= htmlspecialchars(
                        $location,
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>
                </p>

            <?php endif; ?>

        </section>

        <section class="workspace-grid">

            <div class="panel">

                <div class="panel-heading">

                    <div>
                        <p class="section-label">Document Library</p>
                        <h2>Workspace documents</h2>
                    </div>

                    <a
                        class="primary-button"
                        href="upload-document.php?id=<?= urlencode(
                            (string) $workspace['public_id']
                        ) ?>"
                    >
                        Upload document
                    </a>

                </div>

                <?php if ($documents === []): ?>

                    <div class="empty-state">

                        <h3>No documents yet</h3>

                        <p>
                            Upload the declaration, bylaws, rules,
                            budget, reserve study, insurance documents,
                            meeting minutes, or other property records.
                        </p>

                        <a
                            class="primary-button"
                            href="upload-document.php?id=<?= urlencode(
                                (string) $workspace['public_id']
                            ) ?>"
                        >
                            Upload the first document
                        </a>

                    </div>

                <?php else: ?>

                    <div class="document-list">

                        <?php foreach ($documents as $document): ?>

                            <?php
                            $documentStatus = (string) $document['status'];

                            $statusClass = in_array(
                                $documentStatus,
                                ['ready', 'processing'],
                                true
                            )
                                ? $documentStatus
                                : 'processing';
                            ?>

                            <article class="document-card">

                                <div>

                                    <p class="document-label">
                                        <?= htmlspecialchars(
                                            formatCategory(
                                                (string) $document['category']
                                            ),
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>
                                    </p>

                                    <h3>
                                        <?= htmlspecialchars(
                                            (string) $document['original_name'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>
                                    </h3>

                                    <div class="document-meta">

                                        <span>
                                            <?= htmlspecialchars(
                                                formatFileSize(
                                                    (int) $document['file_size']
                                                ),
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>
                                        </span>

                                        <span>
                                            <?= (int) $document['chunk_count'] ?>
                                            knowledge chunks
                                        </span>

                                        <span>
                                            <?= htmlspecialchars(
                                                formatPageCoverage(
                                                    $document['first_page'],
                                                    $document['last_page']
                                                ),
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>
                                        </span>

                                        <span>
                                            Uploaded:
                                            <?= htmlspecialchars(
                                                (string) $document['uploaded_at'],
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>
                                        </span>

                                    </div>

                                </div>

                                <div class="document-actions">

    <span
        class="document-status <?= htmlspecialchars(
            $statusClass,
            ENT_QUOTES,
            'UTF-8'
        ) ?>"
    >
        <?= htmlspecialchars(
            $documentStatus,
            ENT_QUOTES,
            'UTF-8'
        ) ?>
    </span>

    <form
        method="post"
        action="delete-document.php"
        onsubmit="return confirm(
            'Delete this document and all of its extracted knowledge?'
        );"
    >
        <input
            type="hidden"
            name="workspace_id"
            value="<?= htmlspecialchars(
                (string) $workspace['public_id'],
                ENT_QUOTES,
                'UTF-8'
            ) ?>"
        >

        <input
            type="hidden"
            name="document_id"
            value="<?= htmlspecialchars(
                (string) $document['public_id'],
                ENT_QUOTES,
                'UTF-8'
            ) ?>"
        >

        <button
            class="delete-button"
            type="submit"
        >
            Delete
        </button>
    </form>

</div>

                            </article>

                        <?php endforeach; ?>

                    </div>

                <?php endif; ?>

            </div>

            <aside class="panel">

                <p class="section-label">Workspace Summary</p>

                <div class="summary-list">

                    <div class="summary-item">
                        <span>Documents</span>
                        <strong><?= count($documents) ?></strong>
                    </div>

                    <div class="summary-item">
                        <span>Knowledge chunks</span>
                        <strong>
                            <?= array_sum(
                                array_map(
                                    static fn (array $document): int =>
                                        (int) $document['chunk_count'],
                                    $documents
                                )
                            ) ?>
                        </strong>
                    </div>

                    <div class="summary-item">
                        <span>Questions</span>
                        <strong>0</strong>
                    </div>

                    <div class="summary-item">
                        <span>Knowledge entries</span>
                        <strong>0</strong>
                    </div>

                    <div class="summary-item">
                        <span>Created</span>
                        <strong>
                            <?= htmlspecialchars(
                                (string) $workspace['created_at'],
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>
                        </strong>
                    </div>

                </div>

            </aside>

        </section>

    <?php endif; ?>

</main>

</body>
</html>