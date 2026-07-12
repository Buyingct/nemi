<?php
$title = 'Document Concierge';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <meta
        name="description"
        content="A private document concierge for understanding property documents in plain English."
    >

    <title><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></title>

    <link rel="stylesheet" href="assets/style.css">
</head>

<body>

<div class="ambient ambient-one" aria-hidden="true"></div>
<div class="ambient ambient-two" aria-hidden="true"></div>
<div class="ambient ambient-three" aria-hidden="true"></div>

<div class="app-shell">

    <header class="topbar glass-surface">

        <a class="brand" href="#" aria-label="BuyingCT Concierge home">
            <span class="brand-primary">BuyingCT</span>
            <span class="brand-divider" aria-hidden="true"></span>
            <span class="brand-secondary">Concierge</span>
        </a>

        <div class="access-status">
            <span class="access-dot" aria-hidden="true"></span>
            <span>Private Client Access</span>
        </div>

    </header>

    <main class="workspace">

        <section class="intro">

            <p class="eyebrow">Document Concierge</p>

            <h1>
                Understand the property
                <span>before you decide.</span>
            </h1>

            <p class="intro-copy">
                Ask questions naturally. Your concierge will review the
                available property documents and return a clear explanation
                with the supporting section.
            </p>

        </section>

        <section class="concierge-grid">

            <div class="primary-panel glass-surface">

                <div class="panel-heading">

                    <div>
                        <p class="panel-kicker">Private document review</p>

                        <h2>What would you like to understand?</h2>
                    </div>

                    <div class="ready-badge">
                        <span class="ready-icon" aria-hidden="true">✦</span>
                        Ready to assist
                    </div>

                </div>

                <form class="question-form" id="question-form">

                    <label class="sr-only" for="question">
                        Ask a question about the property documents
                    </label>

                    <div class="question-field">

                        <textarea
                            id="question"
                            name="question"
                            rows="4"
                            maxlength="1000"
                            placeholder="Ask about rentals, pets, insurance, alterations, responsibilities, fees, or anything else covered by the documents."
                        ></textarea>

                        <div class="question-footer">

                            <span class="question-hint">
                                Ask in your own words
                            </span>

                            <button class="ask-button" type="submit">
                                <span>Ask the documents</span>
                                <span class="button-symbol" aria-hidden="true">↗</span>
                            </button>

                        </div>

                    </div>

                </form>

                <div class="suggestions">

                    <p>Suggested questions</p>

                    <div class="suggestion-list">

                        <button
                            type="button"
                            class="suggestion-chip"
                            data-question="Are short-term rentals allowed?"
                        >
                            Are short-term rentals allowed?
                        </button>

                        <button
                            type="button"
                            class="suggestion-chip"
                            data-question="Can I operate a business from home?"
                        >
                            Can I operate a business from home?
                        </button>

                        <button
                            type="button"
                            class="suggestion-chip"
                            data-question="Who is responsible for windows and doors?"
                        >
                            Who maintains windows and doors?
                        </button>

                    </div>

                </div>

                <div class="review-state" id="review-state" hidden>

                    <div class="review-spinner" aria-hidden="true"></div>

                    <div>
                        <p class="review-title">Reviewing the declaration</p>

                        <p class="review-copy">
                            Checking relevant sections and restrictions.
                        </p>
                    </div>

                </div>

                <article class="answer-card" id="answer-card" hidden>

                    <div class="answer-header">

                        <div>
                            <p class="answer-label">The documents indicate</p>

                            <h3>Short-term rentals are not permitted.</h3>
                        </div>

                        <span class="source-strength">
                            Directly stated
                        </span>

                    </div>

                    <div class="answer-body">

                        <div class="answer-section">

                            <p class="answer-section-title">Plain English</p>

                            <p>
                                The declaration prohibits transient, hotel,
                                motel, and short-term rental use. This would
                                generally include Airbnb, VRBO, and similar stays.
                            </p>

                        </div>

                        <div class="answer-section source-section">

                            <p class="answer-section-title">Source</p>

                            <p>
                                Condominium Declaration<br>
                                Section 9.1(a) — Residential Use
                            </p>

                            <button type="button" class="source-button">
                                View original section
                            </button>

                        </div>

                    </div>

                </article>

            </div>

            <aside class="knowledge-panel glass-surface">

                <div class="knowledge-heading">

                    <p class="panel-kicker">Currently reviewing</p>

                    <h2>Property Documents</h2>

                    <p>
                        Answers are limited to the documents available in this
                        private workspace.
                    </p>

                </div>

                <div class="document-list">

                    <div class="document-item is-active">

                        <div class="document-icon" aria-hidden="true">
                            <span></span>
                            <span></span>
                            <span></span>
                        </div>

                        <div class="document-details">
                            <strong>Condominium Declaration</strong>
                            <span>Use, ownership and association provisions</span>
                        </div>

                        <span class="document-check" aria-label="Available">✓</span>

                    </div>

                    <div class="document-item is-pending">

                        <div class="document-icon" aria-hidden="true">
                            <span></span>
                            <span></span>
                            <span></span>
                        </div>

                        <div class="document-details">
                            <strong>Rules & Regulations</strong>
                            <span>Not yet added</span>
                        </div>

                        <span class="document-state">Pending</span>

                    </div>

                    <div class="document-item is-pending">

                        <div class="document-icon" aria-hidden="true">
                            <span></span>
                            <span></span>
                            <span></span>
                        </div>

                        <div class="document-details">
                            <strong>Amendments</strong>
                            <span>Not yet added</span>
                        </div>

                        <span class="document-state">Pending</span>

                    </div>

                </div>

                <div class="property-note">

                    <p class="property-note-label">Workspace</p>

                    <p class="property-name">
                        Condominium Document Review
                    </p>

                    <p class="property-meta">
                        One document currently available
                    </p>

                </div>

            </aside>

        </section>

        <footer class="disclaimer">

            <p>
                Plain-language summaries are provided for convenience and do
                not replace legal advice or the original recorded documents.
            </p>

            <p>
                In the event of any discrepancy, the original documents govern.
            </p>

        </footer>

    </main>

</div>

<script>
    const form = document.getElementById('question-form');
    const question = document.getElementById('question');
    const reviewState = document.getElementById('review-state');
    const answerCard = document.getElementById('answer-card');
    const suggestionButtons = document.querySelectorAll('[data-question]');

    suggestionButtons.forEach((button) => {
        button.addEventListener('click', () => {
            question.value = button.dataset.question;
            question.focus();
        });
    });

    form.addEventListener('submit', (event) => {
        event.preventDefault();

        if (!question.value.trim()) {
            question.focus();
            return;
        }

        answerCard.hidden = true;
        reviewState.hidden = false;

        window.setTimeout(() => {
            reviewState.hidden = true;
            answerCard.hidden = false;
            answerCard.scrollIntoView({
                behavior: 'smooth',
                block: 'nearest'
            });
        }, 1400);
    });
</script>

</body>

</html>