<?php

declare(strict_types=1);

require_once __DIR__ . '/../database/connect.php';

$created = isset($_GET['created']) && $_GET['created'] === '1';
$workspaces = [];
$error = '';

try {
    $database = conciergeDatabase();

    $statement = $database->query(
        '
        SELECT
            public_id,
            name,
            address,
            city,
            state,
            postal_code,
            status,
            created_at
        FROM workspaces
        ORDER BY created_at DESC, id DESC
        '
    );

    $workspaces = $statement->fetchAll();
} catch (Throwable $exception) {
    error_log(
        'Workspace list failed: '
        . $exception->getMessage()
    );

    $error = 'Workspaces could not be loaded right now.';
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

    <title>Workspaces | Rocha Circle Concierge</title>

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
            margin: 56px auto;
        }

        .topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
        }

        .brand {
            text-decoration: none;
        }

        .brand span {
            display: block;
        }

        .brand-name {
            font-family: Georgia, "Times New Roman", serif;
            font-size: 1.35rem;
        }

        .brand-service {
            margin-top: 4px;
            color: var(--muted);
            font-size: 0.68rem;
            font-weight: 700;
            letter-spacing: 0.16em;
            text-transform: uppercase;
        }

        .primary-button {
            min-height: 50px;
            padding: 0 20px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 999px;
            color: #fff;
            background: var(--ink);
            text-decoration: none;
        }

        .hero {
            margin-top: 70px;
            max-width: 820px;
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
            font-size: clamp(3.2rem, 8vw, 6.4rem);
            font-weight: 400;
            line-height: 0.95;
            letter-spacing: -0.055em;
        }

        .intro {
            max-width: 650px;
            margin: 24px 0 0;
            color: var(--muted);
            line-height: 1.75;
        }

        .notice {
            margin-top: 30px;
            padding: 16px 18px;
            border-radius: 14px;
            background: rgba(255, 255, 255, 0.5);
        }

        .notice.success {
            border-left: 4px solid var(--success);
        }

        .notice.error {
            border-left: 4px solid var(--error);
        }

        .workspace-panel {
            margin-top: 46px;
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
            margin: 0;
            font-family: Georgia, "Times New Roman", serif;
            font-size: 2rem;
            font-weight: 400;
        }

        .workspace-count {
            color: var(--muted);
            font-size: 0.82rem;
        }

        .workspace-list {
            margin-top: 24px;
            display: grid;
            gap: 12px;
        }

        .workspace-card {
            padding: 20px;
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            gap: 20px;
            align-items: center;
            border: 1px solid var(--line);
            border-radius: 18px;
            background: var(--paper-strong);
        }

        .workspace-card h3 {
            margin: 0;
            font-family: Georgia, "Times New Roman", serif;
            font-size: 1.35rem;
            font-weight: 400;
        }

        .workspace-location {
            margin: 7px 0 0;
            color: var(--muted);
            line-height: 1.5;
        }

        .workspace-meta {
            margin-top: 12px;
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            color: var(--muted);
            font-size: 0.75rem;
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

        .empty-state {
            padding: 54px 24px;
            text-align: center;
            border: 1px dashed var(--line);
            border-radius: 18px;
        }

        .empty-state h3 {
            margin: 0;
            font-family: Georgia, "Times New Roman", serif;
            font-size: 1.7rem;
            font-weight: 400;
        }

        .empty-state p {
            max-width: 480px;
            margin: 13px auto 0;
            color: var(--muted);
            line-height: 1.65;
        }

        .empty-state a {
            margin-top: 22px;
        }

        .footer-link {
            display: inline-block;
            margin-top: 24px;
            color: var(--muted);
            text-underline-offset: 5px;
        }

        @media (max-width: 680px) {
            .shell {
                margin: 28px auto;
            }

            .topbar,
            .panel-heading {
                align-items: stretch;
                flex-direction: column;
            }

            .primary-button {
                width: 100%;
            }

            .hero {
                margin-top: 52px;
            }

            .workspace-panel {
                padding: 20px;
            }

            .workspace-card {
                grid-template-columns: 1fr;
            }

            .status {
                justify-self: start;
            }
        }
    </style>
</head>

<body>

<main class="shell">

    <header class="topbar">

        <a class="brand" href="../">
            <span class="brand-name">Rocha Circle</span>
            <span class="brand-service">Concierge Administration</span>
        </a>

        <a class="primary-button" href="create-workspace.php">
            Create workspace
        </a>

    </header>

    <section class="hero">

        <p class="eyebrow">Workspace Manager</p>

        <h1>Your private property workspaces.</h1>

        <p class="intro">
            Each condominium community, HOA, listing, or individual
            property receives its own documents, questions, knowledge,
            and access controls.
        </p>

    </section>

    <?php if ($created): ?>

        <div class="notice success">
            Workspace created successfully.
        </div>

    <?php endif; ?>

    <?php if ($error !== ''): ?>

        <div class="notice error">
            <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
        </div>

    <?php endif; ?>

    <section class="workspace-panel">

        <div class="panel-heading">

            <h2>Workspaces</h2>

            <span class="workspace-count">
                <?= count($workspaces) ?>
                <?= count($workspaces) === 1 ? 'workspace' : 'workspaces' ?>
            </span>

        </div>

        <div class="workspace-list">

            <?php if ($workspaces === []): ?>

                <div class="empty-state">

                    <h3>No workspaces yet</h3>

                    <p>
                        Create the first workspace for the condominium
                        connected to your public offering statement.
                    </p>

                    <a
                        class="primary-button"
                        href="create-workspace.php"
                    >
                        Create the first workspace
                    </a>

                </div>

            <?php else: ?>

                <?php foreach ($workspaces as $workspace): ?>

                    <?php
                    $locationParts = array_filter([
                        $workspace['address'] ?? null,
                        $workspace['city'] ?? null,
                        $workspace['state'] ?? null,
                        $workspace['postal_code'] ?? null,
                    ]);

                    $location = implode(', ', $locationParts);
                    ?>

                    <article class="workspace-card">

                        <div>

                            <h3>
                                <?= htmlspecialchars(
                                    (string) $workspace['name'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>
                            </h3>

                            <?php if ($location !== ''): ?>

                                <p class="workspace-location">
                                    <?= htmlspecialchars(
                                        $location,
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>
                                </p>

                            <?php endif; ?>

                            <div class="workspace-meta">

                                <span>
                                    ID:
                                    <?= htmlspecialchars(
                                        (string) $workspace['public_id'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>
                                </span>

                                <span>
                                    Created:
                                    <?= htmlspecialchars(
                                        (string) $workspace['created_at'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>
                                </span>

                            </div>

                        </div>

                        <span class="status">
                            <?= htmlspecialchars(
                                (string) $workspace['status'],
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>
                        </span>

                    </article>

                <?php endforeach; ?>

            <?php endif; ?>

        </div>

    </section>

    <a class="footer-link" href="../">
        ← Return to Document Concierge
    </a>

</main>

</body>
</html>