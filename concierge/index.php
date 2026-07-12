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

    <title><?= htmlspecialchars($title) ?></title>

    <link rel="stylesheet" href="assets/style.css">
</head>

<body>

<header class="site-header">
    <a class="brand" href="#">
        <span class="brand-name">BuyingCT</span>
        <span class="brand-service">Concierge</span>
    </a>

    <span class="private-access">Private Client Access</span>
</header>

<main class="page-shell">

    <section class="hero">

        <p class="eyebrow">Document Concierge</p>

        <h1>
            Understand the property<br>
            before you decide.
        </h1>

        <p class="hero-copy">
            Ask questions in your own words and receive clear answers
            drawn from the documents associated with this residence.
        </p>

    </section>

    <section class="concierge-panel">

        <div class="document-summary">

            <div>
                <p class="section-label">Currently reviewing</p>

                <h2>Condominium Declaration</h2>

                <p class="document-description">
                    Use restrictions, ownership responsibilities,
                    association rules and related provisions.
                </p>
            </div>

            <div class="document-status">
                <span class="status-dot"></span>
                Document available
            </div>

        </div>

        <div class="question-area">

            <label for="question">
                What would you like to understand?
            </label>

            <div class="question-form">

                <textarea
                    id="question"
                    name="question"
                    rows="3"
                    placeholder="For example: Can I rent this unit?"
                ></textarea>

                <button type="button">
                    Ask the documents
                </button>

            </div>

            <div class="suggested-questions">

                <span>Popular questions</span>

                <button type="button">
                    Are short-term rentals allowed?
                </button>

                <button type="button">
                    Can I operate a business from home?
                </button>

                <button type="button">
                    What does the association insure?
                </button>

            </div>

        </div>

    </section>

    <section class="trust-note">

        <p>
            Answers are based only on the documents available in this
            private workspace.
        </p>

        <p>
            Plain-language summaries are provided for convenience and
            do not replace legal advice or the original documents.
        </p>

    </section>

</main>

</body>

</html>