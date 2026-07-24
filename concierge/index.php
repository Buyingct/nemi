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

                            <h3 id="answer-title">Document answer</h3>
                        </div>

                        <span
    class="source-strength"
    id="source-strength"
>
    Document supported
</span>

                    </div>

                    <div class="answer-body">

                        <div class="answer-section">

                      

    <p class="answer-section-title">
        Supporting Documents
    </p>

    <div
        class="supporting-sources"
        id="supporting-sources"
    ></div>

</div>     <p class="answer-section-title">Plain English</p>

                
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

    const answerTitle = document.getElementById('answer-title');
    const answerText = document.getElementById('answer-text');
    const supportingSources = document.getElementById(
    'supporting-sources'
);
    const sourceStrength = document.getElementById('source-strength');

    const suggestionButtons = document.querySelectorAll(
        '[data-question]'
    );

    const workspaceId = '6974c13161bf1375';

    suggestionButtons.forEach((button) => {
        button.addEventListener('click', () => {
            question.value = button.dataset.question ?? '';
            question.focus();
        });
    });

     function escapeHtml(value) {
    return String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}

function cleanDocumentName(name) {
    return String(name || 'Property Document')
        .replace(/\.(md|txt|pdf)$/i, '');
}

function findSectionTitle(source) {
    if (source.section_title) {
        return source.section_title;
    }

    const excerpt = String(source.excerpt || '');

    const match = excerpt.match(
        /(?:\\?#{1,4})\s*(Section\s+[0-9.]+\s*[–—-]\s*[^\n]+)/i
    );

    return match
        ? match[1].replaceAll('\\', '').trim()
        : 'Supporting section';
}

function findPageLabel(source) {
    if (source.page_number) {
        return `Page ${source.page_number}`;
    }

    const excerpt = String(source.excerpt || '');

    const match = excerpt.match(
        /(?:\\?\*{0,2}Source:\\?\*{0,2})\s*Page\s+([0-9]+)/i
    );

    return match
        ? `Page ${match[1]}`
        : '';
}

function cleanExcerpt(value) {
    return String(value || '')
        .replaceAll('\\*', '*')
        .replaceAll('\\#', '#')
        .replaceAll('\\---', '---')
        .replace(/^#{1,4}\s+/gm, '')
        .replace(/\*\*/g, '')
        .replace(/`/g, '')
        .replace(/^---$/gm, '')
        .replace(/\n{3,}/g, '\n\n')
        .trim();
}

function renderSources(sources) {
    if (!Array.isArray(sources) || sources.length === 0) {
        supportingSources.innerHTML = `
            <div class="source-empty">
                No supporting document section was linked to this answer.
            </div>
        `;

        return;
    }

    supportingSources.innerHTML = sources
        .map((source, index) => {
            const documentName = cleanDocumentName(
                source.document_name
            );

            const sectionTitle = findSectionTitle(source);
            const pageLabel = findPageLabel(source);
            const excerpt = cleanExcerpt(source.excerpt);

            return `
                <article class="supporting-source-card">
                    <div class="source-card-heading">
                        <div>
                            <p class="source-document-label">
                                Supporting document ${index + 1}
                            </p>

                            <h4>${escapeHtml(documentName)}</h4>
                        </div>

                        ${
                            pageLabel
                                ? `
                                    <span class="source-page">
                                        ${escapeHtml(pageLabel)}
                                    </span>
                                `
                                : ''
                        }
                    </div>

                    <p class="source-section-name">
                        ${escapeHtml(sectionTitle)}
                    </p>

                    <div class="source-excerpt">
                        ${escapeHtml(excerpt)}
                    </div>

                    <button
                        type="button"
                        class="source-button"
                        aria-expanded="false"
                    >
                        Read full section
                    </button>
                </article>
            `;
        })
        .join('');

    supportingSources
        .querySelectorAll('.supporting-source-card')
        .forEach((card) => {
            const button = card.querySelector('.source-button');

            button.addEventListener('click', () => {
                const expanded =
                    card.classList.toggle('is-expanded');

                button.setAttribute(
                    'aria-expanded',
                    expanded ? 'true' : 'false'
                );

                button.textContent =
                    expanded
                        ? 'Show less'
                        : 'Read full section';
            });
        });
}

    form.addEventListener('submit', async (event) => {
        event.preventDefault();

        const submittedQuestion = question.value.trim();

        if (!submittedQuestion) {
            question.focus();
            return;
        }

        answerCard.hidden = true;
        reviewState.hidden = false;

        try {
            const response = await fetch('ask.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    workspace_id: workspaceId,
                    question: submittedQuestion
                })
            });

            const result = await response.json();

            if (!response.ok || result.success !== true) {
                throw new Error(
                    result.error
                    || 'The documents could not answer that question.'
                );
            }

            answerTitle.textContent =
                result.title
                || 'Answer from the documents';

            answerText.textContent =
                result.answer
                || 'No answer was returned.';

            renderSources(result.sources);

            sourceStrength.textContent =
                result.strength
                || 'Document supported';

            reviewState.hidden = true;
            answerCard.hidden = false;

            answerCard.scrollIntoView({
                behavior: 'smooth',
                block: 'nearest'
            });
        } catch (error) {
            reviewState.hidden = true;
            answerCard.hidden = false;

            answerTitle.textContent =
                'The Concierge could not complete the review.';

            answerText.textContent =
                error instanceof Error
                    ? error.message
                    : 'An unexpected error occurred.';

            supportingSources.innerHTML = `
    <div class="source-empty">
        Please try again or check the server log.
    </div>
`;

            sourceStrength.textContent = 'Unavailable';

            answerCard.scrollIntoView({
                behavior: 'smooth',
                block: 'nearest'
            });
        }
    });
</script>

</body>

</html>