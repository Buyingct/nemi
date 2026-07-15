<?php

declare(strict_types=1);

$error = isset($_GET['error'])
    ? trim((string) $_GET['error'])
    : '';
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

    <title>Create Workspace | Rocha Circle Concierge</title>

    <style>
        :root {
            --ivory: #f2ede3;
            --paper: rgba(255, 255, 255, 0.68);
            --ink: #181816;
            --muted: #6f6b62;
            --gold: #9a7740;
            --line: rgba(45, 41, 34, 0.12);
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

        .shell {
            width: min(820px, 100%);
            margin: 70px auto;
        }

        .top-link {
            color: var(--ink);
            font-size: 0.86rem;
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
            font-size: clamp(2.7rem, 8vw, 5rem);
            font-weight: 400;
            line-height: 0.98;
            letter-spacing: -0.05em;
        }

        .intro {
            max-width: 590px;
            margin: 22px 0 0;
            color: var(--muted);
            line-height: 1.7;
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
            margin-top: 38px;
            display: grid;
            gap: 22px;
        }

        .field-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 18px;
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

        input {
            width: 100%;
            min-height: 54px;
            padding: 0 16px;
            border: 1px solid var(--line);
            border-radius: 14px;
            outline: none;
            color: var(--ink);
            background: rgba(255, 255, 255, 0.62);
            font: inherit;
        }

        input:focus {
            border-color: rgba(154, 119, 64, 0.48);
            box-shadow: 0 0 0 4px rgba(154, 119, 64, 0.08);
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
            color: white;
            background: var(--ink);
            font: inherit;
            cursor: pointer;
        }

        @media (max-width: 650px) {
            .shell {
                margin: 28px auto;
            }

            .field-grid {
                grid-template-columns: 1fr;
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

    <a class="top-link" href="index.php">
        ← Return to workspaces
    </a>

    <section class="card">

        <p class="eyebrow">Rocha Circle Concierge</p>

        <h1>Create workspace</h1>

        <p class="intro">
            Create a private workspace for a condominium community,
            HOA, listing, or individual property.
        </p>

        <?php if ($error !== ''): ?>

            <div class="error">
                <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
            </div>

        <?php endif; ?>

        <form method="post" action="save-workspace.php">

            <div class="field">
                <label for="name">Workspace name *</label>

                <input
                    id="name"
                    name="name"
                    type="text"
                    maxlength="160"
                    required
                    placeholder="Example: Woodlake Condominiums"
                >
            </div>

            <div class="field">
                <label for="address">Street address</label>

                <input
                    id="address"
                    name="address"
                    type="text"
                    maxlength="200"
                    placeholder="123 Main Street"
                >
            </div>

            <div class="field-grid">

                <div class="field">
                    <label for="city">City</label>

                    <input
                        id="city"
                        name="city"
                        type="text"
                        maxlength="100"
                        placeholder="Wolcott"
                    >
                </div>

                <div class="field">
                    <label for="state">State</label>

                    <input
                        id="state"
                        name="state"
                        type="text"
                        maxlength="2"
                        value="CT"
                    >
                </div>

            </div>

            <div class="field">
                <label for="postal_code">ZIP code</label>

                <input
                    id="postal_code"
                    name="postal_code"
                    type="text"
                    maxlength="10"
                    placeholder="06716"
                >
            </div>

            <div class="actions">

                <a class="cancel" href="index.php">
                    Cancel
                </a>

                <button type="submit">
                    Create workspace
                </button>

            </div>

        </form>

    </section>

</main>

</body>
</html>