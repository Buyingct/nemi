<?php

declare(strict_types=1);

session_start();


/*
|--------------------------------------------------------------------------
| AUTH
|--------------------------------------------------------------------------
*/

if (
    empty($_SESSION['uid'])
) {

    header(
        'Location: /auth/login_form.php'
    );

    exit;
}


$role =
    (string)(
        $_SESSION['role']
        ?? ''
    );


if (
    $role !== 'realtor'
    &&
    $role !== 'admin'
) {

    header(
        'Location: /app/timeline.php'
    );

    exit;
}


$userId =
    (string)$_SESSION['uid'];

$displayName =
    trim(
        (string)(
            $_SESSION['name']
            ?? ''
        )
    );


require_once __DIR__
    . '/contact_storage.php';



/*
|--------------------------------------------------------------------------
| HTML ESCAPE
|--------------------------------------------------------------------------
*/

function h(
    mixed $value
): string {

    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        'UTF-8'
    );
}



/*
|--------------------------------------------------------------------------
| SAVE CONTACT
|--------------------------------------------------------------------------
*/

$error = '';

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
) {

    $firstName =
        trim(
            (string)(
                $_POST['first_name']
                ?? ''
            )
        );

    $lastName =
        trim(
            (string)(
                $_POST['last_name']
                ?? ''
            )
        );


    if (
        $firstName === ''
        ||
        $lastName === ''
    ) {

        $error =
            'Please enter a first and last name.';
    }

    else {

        $contactId =
            createPersonalContact(
                $userId,
                $_POST
            );


        header(
            'Location: /app/clients.php'
            . '?created='
            . $contactId
        );

        exit;
    }
}



/*
|--------------------------------------------------------------------------
| SEARCH
|--------------------------------------------------------------------------
*/

$search =
    trim(
        (string)(
            $_GET['q']
            ?? ''
        )
    );


$contacts =
    getPersonalContacts(
        $userId,
        $search
    );


$showAdd =
    isset($_GET['add']);

?>
<!doctype html>

<html lang="en">

<head>

    <meta charset="utf-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >

    <title>
        Clients | Nemi
    </title>


    <link
        rel="stylesheet"
        href="/css/nemi-shell.css"
    >

    <link
        rel="stylesheet"
        href="/css/nemi-forms.css"
    >


    <style>

        .contacts-page {
            max-width: 1120px;
            margin: 0 auto;
            padding: 48px 24px 90px;
        }


        .contacts-heading-row {

            display: flex;
            align-items: flex-end;
            justify-content: space-between;

            gap: 24px;

            margin-bottom: 30px;
        }


        .contacts-eyebrow {

            margin-bottom: 7px;

            color:
                var(--nemi-primary, #327d7c);

            font-size: 12px;
            font-weight: 800;

            letter-spacing: .08em;
        }


        .contacts-title {

            margin: 0;

            color:
                var(--nemi-text, #172033);

            font-size: 36px;
        }


        .contacts-subtitle {

            margin:
                8px
                0
                0;

            color:
                var(--nemi-muted, #757575);
        }


        .contacts-add {

            display: inline-flex;

            align-items: center;
            justify-content: center;

            min-height: 48px;

            padding:
                0
                22px;

            border-radius: 999px;

            background:
                var(--nemi-primary, #327d7c);

            color: #fff;

            text-decoration: none;

            font-weight: 700;
        }


        .contacts-search {

            display: flex;

            gap: 12px;

            margin-bottom: 28px;
        }


        .contacts-search input {

            flex: 1;

            min-height: 52px;

            padding:
                0
                18px;

            border:
                1px solid
                rgba(23, 32, 51, .10);

            border-radius: 16px;

            background:
                rgba(255, 255, 255, .72);

            color:
                var(--nemi-text, #172033);

            font: inherit;
        }


        .contacts-search button {

            min-height: 52px;

            padding:
                0
                22px;

            border: 0;

            border-radius: 16px;

            background:
                rgba(23, 32, 51, .07);

            color:
                var(--nemi-text, #172033);

            font: inherit;
            font-weight: 700;

            cursor: pointer;
        }


        .contacts-tabs {

            display: flex;

            gap: 10px;

            margin-bottom: 22px;
        }


        .contacts-tab {

            padding:
                10px
                15px;

            border-radius: 999px;

            background:
                rgba(23, 32, 51, .05);

            color:
                var(--nemi-muted, #757575);

            font-size: 13px;
            font-weight: 700;
        }


        .contacts-tab.active {

            background:
                rgba(50, 125, 124, .10);

            color:
                var(--nemi-primary, #327d7c);
        }


        .contact-list {

            display: flex;

            flex-direction: column;

            gap: 12px;
        }


        .contact-card {

            display: flex;

            align-items: center;
            justify-content: space-between;

            gap: 22px;

            padding: 22px 24px;

            border:
                1px solid
                rgba(23, 32, 51, .075);

            border-radius: 20px;

            background:
                rgba(255, 255, 255, .70);
        }


        .contact-name {

            margin: 0;

            color:
                var(--nemi-text, #172033);

            font-size: 17px;
            font-weight: 700;
        }


        .contact-company {

            margin-top: 3px;

            color:
                var(--nemi-muted, #757575);

            font-size: 12px;
        }


        .contact-meta {

            display: flex;
            flex-wrap: wrap;

            gap:
                7px
                16px;

            margin-top: 9px;

            color:
                var(--nemi-muted, #757575);

            font-size: 13px;
        }


        .contact-actions {

            display: flex;
            align-items: center;

            gap: 8px;
        }


        .contact-action {

            display: inline-flex;

            align-items: center;
            justify-content: center;

            min-height: 38px;

            padding:
                0
                14px;

            border:
                1px solid
                rgba(23, 32, 51, .09);

            border-radius: 999px;

            background: transparent;

            color:
                var(--nemi-text, #172033);

            text-decoration: none;

            font-size: 12px;
            font-weight: 700;
        }


        .contacts-empty {

            padding:
                60px
                30px;

            border:
                1px dashed
                rgba(23, 32, 51, .12);

            border-radius: 22px;

            text-align: center;
        }


        .contacts-empty h3 {

            margin:
                0
                0
                8px;
        }


        .contacts-empty p {

            margin: 0;

            color:
                var(--nemi-muted, #757575);
        }


        .contact-form-grid {

            display: grid;

            grid-template-columns:
                repeat(2, minmax(0, 1fr));

            gap: 18px;
        }


        .contact-form-grid .full {

            grid-column: 1 / -1;
        }


        @media (max-width: 720px) {

            .contacts-heading-row {

                align-items: stretch;

                flex-direction: column;
            }


            .contacts-add {

                align-self: flex-start;
            }


            .contact-card {

                align-items: stretch;

                flex-direction: column;
            }


            .contact-actions {

                justify-content: flex-start;
            }


            .contact-form-grid {

                grid-template-columns: 1fr;
            }


            .contact-form-grid .full {

                grid-column: auto;
            }

        }

    </style>

</head>


<body>


<div class="contacts-page">


    <a
        href="/app/realtor_portal.php"
        class="back-link"
    >
        ← Home
    </a>



    <div class="contacts-heading-row">

        <div>

            <div class="contacts-eyebrow">
                NEMI
            </div>

            <h1 class="contacts-title">
                Clients
            </h1>

            <p class="contacts-subtitle">
                Your contacts, ready whenever you need them.
            </p>

        </div>


        <a
            href="/app/clients.php?add=1"
            class="contacts-add"
        >
            + Add Contact
        </a>

    </div>



    <?php if ($showAdd): ?>


        <form
            method="post"
            class="form-panel"
        >

            <div class="form-section-heading">

                <h2>
                    Add Contact
                </h2>

                <p>
                    Enter their information once.
                    Nemi can reuse it across your forms.
                </p>

            </div>


            <?php if ($error !== ''): ?>

                <div class="form-field-error">
                    <?= h($error) ?>
                </div>

            <?php endif; ?>


            <div class="contact-form-grid">


                <div class="form-field">

                    <label for="first_name">
                        First name
                    </label>

                    <input
                        id="first_name"
                        name="first_name"
                        type="text"
                        required
                        autocomplete="given-name"
                    >

                </div>


                <div class="form-field">

                    <label for="last_name">
                        Last name
                    </label>

                    <input
                        id="last_name"
                        name="last_name"
                        type="text"
                        required
                        autocomplete="family-name"
                    >

                </div>


                <div class="form-field full">

                    <label for="company">
                        Company
                    </label>

                    <input
                        id="company"
                        name="company"
                        type="text"
                    >

                </div>


                <div class="form-field">

                    <label for="email">
                        Email
                    </label>

                    <input
                        id="email"
                        name="email"
                        type="email"
                        autocomplete="email"
                    >

                </div>


                <div class="form-field">

                    <label for="phone">
                        Phone
                    </label>

                    <input
                        id="phone"
                        name="phone"
                        type="tel"
                        autocomplete="tel"
                    >

                </div>


                <div class="form-field full">

                    <label for="street">
                        Street address
                    </label>

                    <input
                        id="street"
                        name="street"
                        type="text"
                        autocomplete="street-address"
                    >

                </div>


                <div class="form-field">

                    <label for="city">
                        City
                    </label>

                    <input
                        id="city"
                        name="city"
                        type="text"
                        autocomplete="address-level2"
                    >

                </div>


                <div class="form-field">

                    <label for="state">
                        State
                    </label>

                    <input
                        id="state"
                        name="state"
                        type="text"
                        value="CT"
                        autocomplete="address-level1"
                    >

                </div>


                <div class="form-field">

                    <label for="zip">
                        ZIP
                    </label>

                    <input
                        id="zip"
                        name="zip"
                        type="text"
                        autocomplete="postal-code"
                    >

                </div>


                <div class="form-field">

                    <label for="contact_type">
                        Contact type
                    </label>

                    <select
                        id="contact_type"
                        name="contact_type"
                    >

                        <option value="client">
                            Client
                        </option>

                        <option value="attorney">
                            Attorney
                        </option>

                        <option value="lender">
                            Lender
                        </option>

                        <option value="agent">
                            Realtor
                        </option>

                        <option value="inspector">
                            Inspector
                        </option>

                        <option value="builder">
                            Builder
                        </option>

                        <option value="other">
                            Other
                        </option>

                    </select>

                </div>


            </div>


            <div class="form-actions">

                <a
                    href="/app/clients.php"
                    class="form-button-secondary"
                >
                    Cancel
                </a>


                <button
                    type="submit"
                    class="form-button-primary"
                >
                    Save Contact
                </button>

            </div>

        </form>


    <?php else: ?>


        <form
            class="contacts-search"
            method="get"
        >

            <input
                type="search"
                name="q"
                value="<?= h($search) ?>"
                placeholder="Search by name, email, phone, or company..."
            >

            <button type="submit">
                Search
            </button>

        </form>



        <div class="contacts-tabs">

            <div class="contacts-tab active">
                My Contacts
            </div>

            <div
                class="contacts-tab"
                title="Coming next"
            >
                Office Contacts
            </div>

        </div>



        <?php if (!$contacts): ?>


            <div class="contacts-empty">

                <h3>
                    <?= $search !== ''
                        ? 'No contacts found.'
                        : 'Your contacts will appear here.' ?>
                </h3>

                <p>

                    <?php if ($search !== ''): ?>

                        Try another search.

                    <?php else: ?>

                        Add someone once and Nemi can reuse
                        their information across your forms.

                    <?php endif; ?>

                </p>

            </div>


        <?php else: ?>


            <div class="contact-list">


                <?php foreach ($contacts as $contact): ?>


                    <article class="contact-card">


                        <div>


                            <h3 class="contact-name">

                                <?= h(
                                    $contact['first_name']
                                    . ' '
                                    . $contact['last_name']
                                ) ?>

                            </h3>


                            <?php if (
                                trim(
                                    (string)$contact['company']
                                ) !== ''
                            ): ?>

                                <div class="contact-company">
                                    <?= h($contact['company']) ?>
                                </div>

                            <?php endif; ?>


                            <div class="contact-meta">


                                <?php if (
                                    $contact['email'] !== ''
                                ): ?>

                                    <span>
                                        <?= h($contact['email']) ?>
                                    </span>

                                <?php endif; ?>


                                <?php if (
                                    $contact['phone'] !== ''
                                ): ?>

                                    <span>
                                        <?= h($contact['phone']) ?>
                                    </span>

                                <?php endif; ?>


                                <?php if (
                                    $contact['city'] !== ''
                                ): ?>

                                    <span>

                                        <?= h($contact['city']) ?>

                                        <?php if (
                                            $contact['state'] !== ''
                                        ): ?>

                                            , <?= h($contact['state']) ?>

                                        <?php endif; ?>

                                    </span>

                                <?php endif; ?>


                            </div>


                        </div>



                        <div class="contact-actions">

                            <a
                                href="#"
                                class="contact-action"
                            >
                                Share
                            </a>

                            <a
                                href="#"
                                class="contact-action"
                            >
                                Open
                            </a>

                        </div>


                    </article>


                <?php endforeach; ?>


            </div>


        <?php endif; ?>


    <?php endif; ?>


</div>


</body>

</html>