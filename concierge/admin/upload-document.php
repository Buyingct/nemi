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
$error = trim((string) ($_GET['error'] ?? ''));

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
            status
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
} catch (Throwable $exception) {
    error_log(
        'Upload document page failed: '
        . $exception->getMessage()
    );

    http_response_code(500);
    exit('The upload page could not be loaded right now.');
}

$categories = [
    'declaration' => 'Declaration',
    'bylaws' => 'Bylaws',
    'rules' => 'Rules & Regulations',
    'budget' => 'Budget',
    'insurance' => 'Insurance',
    'reserve-study' => 'Reserve Study',
    'meeting-minutes' => 'Meeting Minutes',
    'seller-disclosure' => 'Seller Disclosure',
    'inspection' => 'Inspection Report',
    'other' => 'Other',
];
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
        Upload Document |
        <?= htmlspecialchars(
            (string) $workspace['name'],
            ENT_QUOTES,
            'UTF-8'
        ) ?>
    </title>

    <style>
        :root {
            --ivory: #f2ede3;
            --paper: rgba(255, 255, 255, 0.68);
            --ink: #181816;
            --muted: #6f6b62;
            --gold: #9a7740;
            --line: rgba(45, 41, 34, 0.12);
            --error: #8a3f3f;
            --success: #426a4b;
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
            width: min(820px, 100%);
            margin: 54px auto;
        }

        .back-link {
            color: var(--muted);
            text-underline-offset: 5px;
        }

        .card {
            margin-top: 24px;
            padding: clamp(28px, 5vw, 52px);
            border: 1px solid rgba(255, 255, 255, 0.72);
            border-radius: 30px;
            background: var(--paper);
            box-shadow: 0 30px 90px rgba(60, 49, 31, 0.12);
            backdrop-filter: blur(28px);
            -webkit-backdrop-filter: blur(28px);
        }

        .eyebrow {
            margin: 0;
            color: var(--gold);
            font-size: 0.72rem;
            font-weight: 700;
            letter-spacing: 0.18em;
            text-transform: uppercase;
        }

        h1 {
            margin: 16px 0 0;
            font-family: Georgia, "Times New Roman", serif;
            font-size: clamp(2.8rem, 8vw, 5rem);
            font-weight: 400;
            line-height: 0.98;
            letter-spacing: -0.05em;
        }

        .workspace-name {
            margin: 16px 0 0;
            color: var(--muted);
            font-size: 1rem;
        }

        .error {
            margin-top: 24px;
            padding: 15px 17px;
            border-left: 4px solid var(--error);
            border-radius: 12px;
            background: rgba(255, 255, 255, 0.5);
            color: var(--error);
        }

        form {
            margin-top: 36px;
            display: grid;
            gap: 24px;
        }

        .field {
            display: grid;
            gap: 9px;
        }

        label {
            color: var(--muted);
            font-size: 0.76rem;
            font-weight: 650;
            letter-spacing: 0.1em;
            text-transform: uppercase;
        }

        select,
        input[type="file"] {
            width: 100%;
            min-height: 54px;
            padding: 14px 16px;
            border: 1px solid var(--line);
            border-radius: 14px;
            outline: none;
            color: var(--ink);
            background: rgba(255, 255, 255, 0.62);
            font: inherit;
        }

        select:focus,
        input[type="file"]:focus {
            border-color: rgba(154, 119, 64, 0.48);
            box-shadow: 0 0 0 4px rgba(154, 119, 64, 0.08);
        }

        .upload-note,
        .detected-note {
            margin: 0;
            color: var(--muted);
            font-size: 0.84rem;
            line-height: 1.65;
        }

        .detected-note {
            display: none;
            color: var(--success);
        }

        .detected-note.visible {
            display: block;
        }

        .actions {
            padding-top: 8px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 18px;
        }

        .cancel {
            color: var(--muted);
            text-underline-offset: 5px;
        }

        button {
            min-height: 52px;
            padding: 0 22px;
            border: 0;
            border-radius: 999px;
            color: #fff;
            background: var(--ink);
            font: inherit;
            cursor: pointer;
        }

        @media (max-width: 640px) {
            .shell {
                margin: 28px auto;
            }

            .actions {
                align-items: stretch;
                flex-direction: column-reverse;
            }

            button {
                width: 100%;
            }
        }
    </style>
</head>

<body>

<main class="shell">

    <a
        class="back-link"
        href="workspace.php?id=<?= urlencode(
            (string) $workspace['public_id']
        ) ?>"
    >
        ← Return to workspace
    </a>

    <section class="card">

        <p class="eyebrow">Document Library</p>

        <h1>Upload document</h1>

        <p class="workspace-name">
            <?= htmlspecialchars(
                (string) $workspace['name'],
                ENT_QUOTES,
                'UTF-8'
            ) ?>
        </p>

        <?php if ($error !== ''): ?>

            <div class="error">
                <?= htmlspecialchars(
                    $error,
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>
            </div>

        <?php endif; ?>

        <form
            method="post"
            action="save-document.php"
            enctype="multipart/form-data"
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

            <div class="field">
                <label for="category">Document category</label>

                <select
                    id="category"
                    name="category"
                    required
                >
                    <?php foreach ($categories as $value => $label): ?>

                        <option value="<?= htmlspecialchars(
                            $value,
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>">
                            <?= htmlspecialchars(
                                $label,
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>
                        </option>

                    <?php endforeach; ?>
                </select>

                <p
                    class="detected-note"
                    id="detected-category"
                    aria-live="polite"
                ></p>
            </div>

            <div class="field">
                <label for="document">Knowledge source</label>

                <input
                    id="document"
                    name="document"
                    type="file"
                    accept=".pdf,.md,.markdown,.txt,application/pdf,text/markdown,text/plain"
                    required
                >

                <p class="upload-note">
                    Supported formats:
                    <strong>PDF</strong>,
                    <strong>Markdown (.md)</strong>, and
                    <strong>Text (.txt)</strong>.
                    Searchable PDFs may be indexed directly. Markdown and text
                    files are treated as verified knowledge sources.
                </p>
            </div>

            <div class="actions">

                <a
                    class="cancel"
                    href="workspace.php?id=<?= urlencode(
                        (string) $workspace['public_id']
                    ) ?>"
                >
                    Cancel
                </a>

                <button type="submit">
                    Upload document
                </button>

            </div>

        </form>

    </section>

</main>

<script>
    (() => {
        const documentInput = document.getElementById('document');
        const categorySelect = document.getElementById('category');
        const detectedNote = document.getElementById('detected-category');

        if (
            !(documentInput instanceof HTMLInputElement)
            || !(categorySelect instanceof HTMLSelectElement)
            || !(detectedNote instanceof HTMLElement)
        ) {
            return;
        }

        const categoryRules = [
            {
                category: 'declaration',
                label: 'Declaration',
                patterns: [
                    /\bdeclaration\b/i,
                    /\bmaster deed\b/i
                ]
            },
            {
                category: 'bylaws',
                label: 'Bylaws',
                patterns: [
                    /\bbylaws?\b/i,
                    /\bby-laws?\b/i
                ]
            },
            {
                category: 'rules',
                label: 'Rules & Regulations',
                patterns: [
                    /\brules?\b/i,
                    /\bregulations?\b/i,
                    /\brules? and regulations?\b/i
                ]
            },
            {
                category: 'budget',
                label: 'Budget',
                patterns: [
                    /\bbudget\b/i,
                    /\bfinancial statement\b/i,
                    /\bincome and expense\b/i
                ]
            },
            {
                category: 'insurance',
                label: 'Insurance',
                patterns: [
                    /\binsurance\b/i,
                    /\bcertificate of insurance\b/i,
                    /\bpolicy\b/i
                ]
            },
            {
                category: 'reserve-study',
                label: 'Reserve Study',
                patterns: [
                    /\breserve study\b/i,
                    /\breserve analysis\b/i
                ]
            },
            {
                category: 'meeting-minutes',
                label: 'Meeting Minutes',
                patterns: [
                    /\bminutes\b/i,
                    /\bmeeting minutes\b/i
                ]
            },
            {
                category: 'seller-disclosure',
                label: 'Seller Disclosure',
                patterns: [
                    /\bseller disclosure\b/i,
                    /\bproperty disclosure\b/i
                ]
            },
            {
                category: 'inspection',
                label: 'Inspection Report',
                patterns: [
                    /\binspection\b/i,
                    /\binspection report\b/i
                ]
            }
        ];

        documentInput.addEventListener('change', () => {
            const selectedFile = documentInput.files?.[0];

            detectedNote.classList.remove('visible');
            detectedNote.textContent = '';

            if (!selectedFile) {
                return;
            }

            const normalizedName = selectedFile.name
                .replace(/\.[^.]+$/, '')
                .replace(/[_-]+/g, ' ')
                .replace(/\s+/g, ' ')
                .trim();

            const match = categoryRules.find((rule) =>
                rule.patterns.some((pattern) =>
                    pattern.test(normalizedName)
                )
            );

            if (!match) {
                return;
            }

            categorySelect.value = match.category;
            detectedNote.textContent =
                `Category detected from filename: ${match.label}. `
                + 'You can change it before uploading.';
            detectedNote.classList.add('visible');
        });
    })();
</script>

</body>
</html>