<?php

declare(strict_types=1);

require_once __DIR__ . '/../database/connect.php';
require_once __DIR__ . '/../services/AnswerRepository.php';

$draftAnswers = [];
$errorMessage = '';

try {
    $database = conciergeDatabase();
    $answerRepository = new AnswerRepository($database);
    $draftAnswers = $answerRepository->findDrafts();
} catch (Throwable $exception) {
    error_log(
        'Answer review queue failed: '
        . $exception->getMessage()
    );

    $errorMessage = (
        'The review queue could not be loaded right now. '
        . 'Please try again.'
    );
}

function escapeHtml(string $value): string
{
    return htmlspecialchars(
        $value,
        ENT_QUOTES,
        'UTF-8'
    );
}

function formatReviewDate(string $value): string
{
    $timestamp = strtotime($value);

    if ($timestamp === false) {
        return $value;
    }

    return date('M j, Y \a\t g:i A', $timestamp);
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

    <title>Answer Review Queue | Rocha Circle Concierge</title>

    <style>
        :root {
            --ivory: #f2ede3;
            --paper: rgba(255, 255, 255, 0.72);
            --paper-strong: rgba(255, 255, 255, 0.9);
            --ink: #181816;
            --muted: #716d64;
            --line: rgba(24, 24, 22, 0.12);
            --gold: #9a7740;
            --gold-soft: rgba(154, 119, 64, 0.12);
            --green: #49684f;
            --red: #874646;
            --shadow: 0 24px 70px rgba(63, 50, 30, 0.11);
        }

        * {
            box-sizing: border-box;
        }

        body {
            min-height: 100vh;
            margin: 0;
            color: var(--ink);
            background:
                radial-gradient(
                    circle at 10% 0%,
                    rgba(205, 183, 140, 0.26),
                    transparent 34%
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

        .page-shell {
            width: min(1160px, calc(100% - 32px));
            margin: 0 auto;
            padding: 34px 0 70px;
        }

        .topbar {
            display: flex;
            justify-content: space-between;
            gap: 20px;
            align-items: center;
            margin-bottom: 42px;
        }

        .brand {
            margin: 0;
            font-size: 0.74rem;
            font-weight: 700;
            letter-spacing: 0.17em;
            text-transform: uppercase;
        }

        .back-link {
            color: var(--muted);
            font-size: 0.9rem;
            text-underline-offset: 5px;
        }

        .hero {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            gap: 30px;
            align-items: end;
            margin-bottom: 30px;
        }

        .eyebrow {
            margin: 0 0 14px;
            color: var(--gold);
            font-size: 0.72rem;
            font-weight: 700;
            letter-spacing: 0.16em;
            text-transform: uppercase;
        }

        h1 {
            max-width: 720px;
            margin: 0;
            font-family: Georgia, "Times New Roman", serif;
            font-size: clamp(2.7rem, 7vw, 5.4rem);
            font-weight: 400;
            line-height: 0.98;
            letter-spacing: -0.05em;
        }

        .count-card {
            min-width: 170px;
            padding: 22px 24px;
            border: 1px solid rgba(255, 255, 255, 0.72);
            border-radius: 20px;
            background: var(--paper);
            box-shadow: var(--shadow);
            backdrop-filter: blur(20px);
        }

        .count-card strong,
        .count-card span {
            display: block;
        }

        .count-card strong {
            font-family: Georgia, "Times New Roman", serif;
            font-size: 2.5rem;
            font-weight: 400;
        }

        .count-card span {
            margin-top: 4px;
            color: var(--muted);
            font-size: 0.84rem;
        }

        .notice,
        .empty-state {
            padding: 32px;
            border: 1px solid rgba(255, 255, 255, 0.75);
            border-radius: 24px;
            background: var(--paper);
            box-shadow: var(--shadow);
            backdrop-filter: blur(20px);
        }

        .notice {
            border-left: 4px solid var(--red);
        }

        .empty-state {
            min-height: 280px;
            display: grid;
            place-items: center;
            text-align: center;
        }

        .empty-state-inner {
            max-width: 520px;
        }

        .empty-state h2 {
            margin: 0;
            font-family: Georgia, "Times New Roman", serif;
            font-size: clamp(1.8rem, 5vw, 3rem);
            font-weight: 400;
        }

        .empty-state p {
            margin: 14px 0 0;
            color: var(--muted);
            line-height: 1.7;
        }

        .review-list {
            display: grid;
            gap: 22px;
        }

        .review-card {
            overflow: hidden;
            border: 1px solid rgba(255, 255, 255, 0.76);
            border-radius: 26px;
            background: var(--paper-strong);
            box-shadow: var(--shadow);
        }

        .review-header {
            display: flex;
            justify-content: space-between;
            gap: 20px;
            align-items: flex-start;
            padding: 28px 30px 22px;
            border-bottom: 1px solid var(--line);
        }

        .status-pill {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 12px;
            border: 1px solid rgba(154, 119, 64, 0.22);
            border-radius: 999px;
            color: var(--gold);
            background: var(--gold-soft);
            font-size: 0.72rem;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        .question {
            margin: 15px 0 0;
            font-family: Georgia, "Times New Roman", serif;
            font-size: clamp(1.65rem, 4vw, 2.5rem);
            font-weight: 400;
            line-height: 1.12;
        }

        .workspace-name {
            margin: 8px 0 0;
            color: var(--muted);
            font-size: 0.9rem;
        }

        .created-date {
            max-width: 180px;
            color: var(--muted);
            font-size: 0.78rem;
            line-height: 1.5;
            text-align: right;
        }

        .review-body {
            display: grid;
            grid-template-columns: minmax(0, 1fr) 250px;
        }

        .answer-panel,
        .details-panel {
            padding: 28px 30px;
        }

        .details-panel {
            border-left: 1px solid var(--line);
            background: rgba(242, 237, 227, 0.35);
        }

        .label {
            margin: 0 0 11px;
            color: var(--gold);
            font-size: 0.68rem;
            font-weight: 700;
            letter-spacing: 0.15em;
            text-transform: uppercase;
        }

        .answer-text {
            margin: 0;
            color: #403d37;
            font-family: Georgia, "Times New Roman", serif;
            font-size: 1.12rem;
            line-height: 1.75;
            white-space: pre-wrap;
        }

        .detail-row + .detail-row {
            margin-top: 22px;
        }

        .detail-value {
            margin: 0;
            color: #403d37;
            line-height: 1.55;
        }

        .actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            padding: 20px 30px 28px;
            border-top: 1px solid var(--line);
        }

        button {
            min-height: 44px;
            padding: 10px 17px;
            border-radius: 999px;
            font: inherit;
            font-weight: 650;
            cursor: not-allowed;
            opacity: 0.55;
        }

        .approve {
            border: 1px solid var(--green);
            color: white;
            background: var(--green);
        }

        .edit {
            border: 1px solid var(--ink);
            color: var(--ink);
            background: transparent;
        }

        .reject {
            border: 1px solid rgba(135, 70, 70, 0.42);
            color: var(--red);
            background: transparent;
        }

        .action-note {
            align-self: center;
            margin-left: auto;
            color: var(--muted);
            font-size: 0.78rem;
        }

        @media (max-width: 760px) {
            .page-shell {
                width: min(100% - 20px, 1160px);
                padding-top: 22px;
            }

            .hero {
                grid-template-columns: 1fr;
            }

            .count-card {
                width: 100%;
            }

            .review-header {
                display: block;
            }

            .created-date {
                max-width: none;
                margin-top: 16px;
                text-align: left;
            }

            .review-body {
                grid-template-columns: 1fr;
            }

            .details-panel {
                border-top: 1px solid var(--line);
                border-left: 0;
            }

            .action-note {
                width: 100%;
                margin: 6px 0 0;
            }
        }
    </style>
</head>

<body>

<main class="page-shell">

    <header class="topbar">

        <p class="brand">
            Rocha Circle Concierge
        </p>

        <a class="back-link" href="./">
            Return to admin
        </a>

    </header>

    <section class="hero">

        <div>
            <p class="eyebrow">Private administration</p>

            <h1>Answer Review Queue</h1>
        </div>

        <aside class="count-card">
            <strong><?= count($draftAnswers) ?></strong>
            <span>draft answer<?= count($draftAnswers) === 1 ? '' : 's' ?></span>
        </aside>

    </section>

    <?php if ($errorMessage !== ''): ?>

        <section class="notice">
            <?= escapeHtml($errorMessage) ?>
        </section>

    <?php elseif ($draftAnswers === []): ?>

        <section class="empty-state">

            <div class="empty-state-inner">

                <p class="eyebrow">Everything reviewed</p>

                <h2>No draft answers are waiting.</h2>

                <p>
                    New AI-generated answers will appear here before they can
                    become part of the approved Concierge answer library.
                </p>

            </div>

        </section>

    <?php else: ?>

        <section class="review-list">

            <?php foreach ($draftAnswers as $answer): ?>

                <article class="review-card">

                    <header class="review-header">

                        <div>

                            <span class="status-pill">
                                Draft
                            </span>

                            <h2 class="question">
                                <?= escapeHtml(
                                    (string) $answer['canonical_question']
                                ) ?>
                            </h2>

                            <p class="workspace-name">
                                <?= escapeHtml(
                                    (string) $answer['workspace_name']
                                ) ?>
                            </p>

                        </div>

                        <div class="created-date">
                            Created
                            <?= escapeHtml(
                                formatReviewDate(
                                    (string) $answer['created_at']
                                )
                            ) ?>
                        </div>

                    </header>

                    <div class="review-body">

                        <section class="answer-panel">

                            <p class="label">Draft answer</p>

                            <p class="answer-text"><?= escapeHtml(
                                (string) $answer['answer_text']
                            ) ?></p>

                        </section>

                        <aside class="details-panel">

                            <div class="detail-row">

                                <p class="label">Asked</p>

                                <p class="detail-value">
                                    <?= (int) $answer['question_count'] ?>
                                    time<?= (int) $answer['question_count'] === 1
                                        ? ''
                                        : 's'
                                    ?>
                                </p>

                            </div>

                            <div class="detail-row">

                                <p class="label">Sources</p>

                                <p class="detail-value">
                                    <?= (int) $answer['source_count'] ?>
                                    attached
                                </p>

                            </div>

                            <div class="detail-row">

                                <p class="label">Strength</p>

                                <p class="detail-value">
                                    <?= escapeHtml(
                                        (string) (
                                            $answer['source_strength']
                                            ?: 'Not yet rated'
                                        )
                                    ) ?>
                                </p>

                            </div>

                        </aside>

                    </div>

                    <footer class="actions">

                        <button class="approve" type="button" disabled>
                            Approve
                        </button>

                        <button class="edit" type="button" disabled>
                            Edit
                        </button>

                        <button class="reject" type="button" disabled>
                            Reject
                        </button>

                        <span class="action-note">
                            Actions are added in the next step.
                        </span>

                    </footer>

                </article>

            <?php endforeach; ?>

        </section>

    <?php endif; ?>

</main>

</body>
</html>
