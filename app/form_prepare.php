<?php
declare(strict_types=1);
session_start();

if (empty($_SESSION['uid'])) {
    header('Location: /auth/login_form.php');
    exit;
}

$uid     = (string)($_SESSION['uid'] ?? '');
$meEmail = (string)($_SESSION['email'] ?? '');
$role    = strtolower((string)($_SESSION['role'] ?? ''));
$meName  = (string)($_SESSION['name'] ?? '');

if ($role !== 'realtor' && $role !== 'admin') {
    header('Location: /app/timeline.php');
    exit;
}

function h($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

if ($meName === '') {
    $meName = $meEmail !== ''
        ? preg_replace('/@.*$/', '', $meEmail)
        : 'Realtor';
}

/*
|--------------------------------------------------------------------------
| FORM REGISTRY — V0.1
|--------------------------------------------------------------------------
|
| Later this will move into our real Form Registry/database.
| For now we are building only the:
|
| Exclusive Right to Sell Agreement
| Connecticut Association of REALTORS®
| Revision 10/25
|
*/

$formId = (string)($_GET['form'] ?? '');

if ($formId !== 'exclusive_right_to_sell') {
    http_response_code(404);

    echo 'This form is not available yet.';
    exit;
}

$form = [
    'id' => 'exclusive_right_to_sell',

    'name' => 'Exclusive Right to Sell Agreement',

    'publisher' => 'Connecticut Association of REALTORS®, Inc.',

    'revision' => '10/25',

    'brokerage' => 'Fercodini Properties, Inc.',
];

/*
|--------------------------------------------------------------------------
| DRAFT STORAGE — TEMPORARY
|--------------------------------------------------------------------------
|
| For this first build we save the form preparation in the Realtor's PHP
| session.
|
| Later:
| transaction -> agreement -> structured agreement state -> database
|
*/

$draftKey = 'form_draft_' . $formId;

$draft = $_SESSION[$draftKey] ?? [

    /*
    |--------------------------------------------------------------------------
    | LISTING BASICS
    |--------------------------------------------------------------------------
    */

    'seller_1' => '',
    'seller_2' => '',

    'property_address' => '',

    'list_price' => '',

    'start_date' => '',
    'expiration_date' => '',

    'broker' => $form['brokerage'],


    /*
    |--------------------------------------------------------------------------
    | COMPENSATION
    |--------------------------------------------------------------------------
    */

    'service_fee_type' => 'percent',
    'service_fee_value' => '',

    'buyer_broker_authorized' => 'yes',

    'buyer_broker_fee_type' => 'percent',
    'buyer_broker_fee_value' => '',
];


/*
|--------------------------------------------------------------------------
| CURRENT STEP
|--------------------------------------------------------------------------
*/

$step = max(
    1,
    (int)($_GET['step'] ?? 1)
);


$errors = [];


/*
|--------------------------------------------------------------------------
| SAVE CURRENT STEP
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {


    /*
    |--------------------------------------------------------------------------
    | STEP 1 — LISTING BASICS
    |--------------------------------------------------------------------------
    */

    if ($step === 1) {

        $draft['seller_1'] = trim(
            (string)($_POST['seller_1'] ?? '')
        );

        $draft['seller_2'] = trim(
            (string)($_POST['seller_2'] ?? '')
        );

        $draft['property_address'] = trim(
            (string)($_POST['property_address'] ?? '')
        );

        $draft['list_price'] = trim(
            (string)($_POST['list_price'] ?? '')
        );

        $draft['start_date'] = trim(
            (string)($_POST['start_date'] ?? '')
        );

        $draft['expiration_date'] = trim(
            (string)($_POST['expiration_date'] ?? '')
        );

        $draft['broker'] = $form['brokerage'];


        if ($draft['seller_1'] === '') {
            $errors['seller_1'] =
                'Enter the seller’s name.';
        }

        if ($draft['property_address'] === '') {
            $errors['property_address'] =
                'Enter the property address.';
        }

        if ($draft['list_price'] === '') {
            $errors['list_price'] =
                'Enter the listing price.';
        }

        if ($draft['start_date'] === '') {
            $errors['start_date'] =
                'Choose the start date.';
        }

        if ($draft['expiration_date'] === '') {
            $errors['expiration_date'] =
                'Choose the expiration date.';
        }


        $_SESSION[$draftKey] = $draft;


        if (!$errors) {

            header(
                'Location: /app/form_prepare.php'
                . '?form=exclusive_right_to_sell'
                . '&step=2'
            );

            exit;
        }
    }


    /*
    |--------------------------------------------------------------------------
    | STEP 2 — COMPENSATION
    |--------------------------------------------------------------------------
    */

    if ($step === 2) {

        $serviceFeeType =
            (string)(
                $_POST['service_fee_type']
                ?? 'percent'
            );

        if (
            $serviceFeeType !== 'percent'
            &&
            $serviceFeeType !== 'amount'
        ) {
            $serviceFeeType = 'percent';
        }


        $draft['service_fee_type'] =
            $serviceFeeType;


        $draft['service_fee_value'] =
            trim(
                (string)(
                    $_POST['service_fee_value']
                    ?? ''
                )
            );


        $buyerAuthorized =
            (string)(
                $_POST['buyer_broker_authorized']
                ?? 'yes'
            );

        if (
            $buyerAuthorized !== 'yes'
            &&
            $buyerAuthorized !== 'no'
        ) {
            $buyerAuthorized = 'yes';
        }


        $draft['buyer_broker_authorized'] =
            $buyerAuthorized;


        $buyerFeeType =
            (string)(
                $_POST['buyer_broker_fee_type']
                ?? 'percent'
            );

        if (
            $buyerFeeType !== 'percent'
            &&
            $buyerFeeType !== 'amount'
        ) {
            $buyerFeeType = 'percent';
        }


        $draft['buyer_broker_fee_type'] =
            $buyerFeeType;


        $draft['buyer_broker_fee_value'] =
            trim(
                (string)(
                    $_POST['buyer_broker_fee_value']
                    ?? ''
                )
            );


        if ($draft['service_fee_value'] === '') {

            $errors['service_fee_value'] =
                'Enter the brokerage service fee.';
        }


        if (
            $draft['buyer_broker_authorized']
            === 'no'
        ) {

            $draft['buyer_broker_fee_value'] = '';
        }


        if (
            $draft['buyer_broker_authorized']
            === 'yes'
            &&
            $draft['buyer_broker_fee_value']
            === ''
        ) {

            $errors['buyer_broker_fee_value'] =
                'Enter the buyer-broker compensation.';
        }


        $_SESSION[$draftKey] = $draft;


        if (!$errors) {

            header(
                'Location: /app/form_prepare.php'
                . '?form=exclusive_right_to_sell'
                . '&step=3'
            );

            exit;
        }
    }
}



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
    Prepare <?= h($form['name']) ?> — Nemi
</title>

<link
    rel="preconnect"
    href="https://fonts.gstatic.com"
    crossorigin
>

<link
    href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap"
    rel="stylesheet"
>

<style>

:root{

    --ink:#172033;
    --muted:#757575;

    --page:#f6f9fb;

    --teal:#327d7c;
    --blue:#315b9a;

    --line:#e2e8ec;

    --white:#ffffff;

    --danger:#b74242;

    --shadow:
        0 18px 60px rgba(27,45,67,.08);
}

*{
    box-sizing:border-box;
}

body{

    margin:0;

    min-height:100vh;

    font-family:
        Poppins,
        system-ui,
        -apple-system,
        BlinkMacSystemFont,
        "Segoe UI",
        sans-serif;

    color:var(--ink);

    background:
        radial-gradient(
            circle at 15% 10%,
            rgba(72,153,153,.09),
            transparent 30%
        ),
        radial-gradient(
            circle at 85% 15%,
            rgba(49,91,154,.07),
            transparent 28%
        ),
        var(--page);
}


/* =========================================================
   HEADER
========================================================= */

.nemi-header{

    min-height:78px;

    padding:0 34px;

    display:flex;
    align-items:center;
    justify-content:space-between;

    background:
        rgba(255,255,255,.72);

    border-bottom:
        1px solid rgba(255,255,255,.9);

    backdrop-filter:
        blur(20px);

    -webkit-backdrop-filter:
        blur(20px);
}

.nemi-logo{

    font-size:21px;
    font-weight:800;

    letter-spacing:-.03em;
}

.nemi-logo span{
    color:var(--teal);
}

.account-name{

    font-size:14px;
    font-weight:600;
}


/* =========================================================
   NAV
========================================================= */

.nemi-nav{

    display:flex;

    gap:28px;

    padding:
        12px
        34px;

    background:
        rgba(255,255,255,.54);

    border-bottom:
        1px solid rgba(23,32,51,.06);
}

.nemi-nav a{

    color:var(--ink);

    text-decoration:none;

    font-size:14px;
    font-weight:600;
}

.nemi-nav a.active{
    color:var(--teal);
}


/* =========================================================
   PAGE
========================================================= */

.page{

    width:min(
        940px,
        calc(100% - 34px)
    );

    margin:
        58px
        auto
        100px;
}


/* =========================================================
   FORM TITLE
========================================================= */

.back-link{

    display:inline-flex;

    margin-bottom:30px;

    color:var(--muted);

    text-decoration:none;

    font-size:14px;
    font-weight:600;
}

.form-eyebrow{

    margin-bottom:10px;

    color:var(--teal);

    font-size:13px;
    font-weight:700;

    letter-spacing:.06em;

    text-transform:uppercase;
}

.form-title{

    margin:0;

    max-width:760px;

    font-size:
        clamp(
            30px,
            5vw,
            46px
        );

    line-height:1.08;

    letter-spacing:-.04em;
}

.form-intro{

    max-width:660px;

    margin:
        20px
        0
        46px;

    color:#56616f;

    font-size:17px;
    line-height:1.75;
}


/* =========================================================
   PROGRESS
========================================================= */

.progress-row{

    display:flex;

    align-items:center;

    gap:14px;

    margin-bottom:36px;
}

.progress-line{

    flex:1;

    height:5px;

    overflow:hidden;

    border-radius:999px;

    background:#dfe7ea;
}

.progress-fill{

    width:14%;

    height:100%;

    border-radius:inherit;

    background:
        linear-gradient(
            90deg,
            var(--teal),
            var(--blue)
        );
}

.progress-label{

    flex:0 0 auto;

    color:var(--muted);

    font-size:13px;
    font-weight:600;
}


/* =========================================================
   PREP AREA
========================================================= */

.prep-panel{

    padding:
        44px
        46px;

    border:
        1px solid rgba(255,255,255,.9);

    border-radius:28px;

    background:
        rgba(255,255,255,.76);

    box-shadow:
        var(--shadow);

    backdrop-filter:
        blur(18px);

    -webkit-backdrop-filter:
        blur(18px);
}

.section-heading{

    margin-bottom:32px;
}

.section-heading h2{

    margin:
        0
        0
        8px;

    font-size:25px;

    letter-spacing:-.025em;
}

.section-heading p{

    margin:0;

    color:var(--muted);

    font-size:15px;
}


/* =========================================================
   FIELDS
========================================================= */

.field-grid{

    display:grid;

    grid-template-columns:
        repeat(
            2,
            minmax(0,1fr)
        );

    gap:
        25px
        22px;
}

.field{

    display:flex;

    flex-direction:column;

    gap:9px;
}

.field.full{
    grid-column:1 / -1;
}

.field label{

    font-size:13px;
    font-weight:700;
}

.field input{

    width:100%;

    min-height:58px;

    padding:
        0
        17px;

    font:inherit;

    font-size:16px;

    color:var(--ink);

    outline:none;

    border:
        1px solid
        var(--line);

    border-radius:15px;

    background:
        rgba(255,255,255,.9);

    transition:
        border-color .16s ease,
        box-shadow .16s ease,
        background .16s ease;
}

.field input:focus{

    border-color:
        rgba(50,125,124,.75);

    background:#fff;

    box-shadow:
        0 0 0 4px
        rgba(50,125,124,.10);
}

.field input[readonly]{

    color:#59636e;

    background:
        rgba(239,243,245,.78);

    cursor:not-allowed;
}

.field-help{

    margin-top:-2px;

    color:var(--muted);

    font-size:12px;
    line-height:1.5;
}

.field-error{

    color:var(--danger);

    font-size:12px;
    font-weight:600;
}


/* =========================================================
   PRICE
========================================================= */

.money-wrap{

    position:relative;
}

.money-symbol{

    position:absolute;

    left:17px;

    top:50%;

    transform:
        translateY(-50%);

    color:#6c7681;

    font-weight:600;
}

.money-wrap input{

    padding-left:34px;
}


/* =========================================================
   BUTTONS
========================================================= */

.actions{

    display:flex;

    align-items:center;
    justify-content:space-between;

    gap:20px;

    margin-top:40px;

    padding-top:28px;

    border-top:
        1px solid
        rgba(23,32,51,.08);
}

.secondary-button{

    display:inline-flex;

    align-items:center;
    justify-content:center;

    min-height:52px;

    padding:
        0
        20px;

    color:#56616f;

    text-decoration:none;

    font-size:14px;
    font-weight:700;
}

.primary-button{

    min-height:54px;

    padding:
        0
        29px;

    border:0;

    border-radius:999px;

    color:#fff;

    font:inherit;

    font-size:14px;
    font-weight:700;

    cursor:pointer;

    background:
        linear-gradient(
            135deg,
            var(--teal),
            var(--blue)
        );

    box-shadow:
        0 10px 25px
        rgba(49,91,154,.18);
}

.primary-button:hover{

    transform:
        translateY(-1px);
}


/* =========================================================
   STEP 2 PLACEHOLDER
========================================================= */

.coming-next{

    padding:
        60px
        40px;

    text-align:center;

    border-radius:28px;

    background:
        rgba(255,255,255,.76);

    box-shadow:
        var(--shadow);
}

.coming-next h2{

    margin:
        0
        0
        12px;

    font-size:30px;
}

.coming-next p{

    margin:
        0
        auto
        30px;

    max-width:580px;

    color:var(--muted);

    line-height:1.7;
}


/* =========================================================
   MOBILE
========================================================= */

@media(max-width:720px){

    .nemi-header{

        min-height:68px;

        padding:
            0
            18px;
    }

    .account-name{
        display:none;
    }

    .nemi-nav{

        overflow-x:auto;

        gap:22px;

        padding:
            11px
            18px;
    }

    .page{

        width:
            calc(100% - 28px);

        margin-top:36px;
    }

    .form-intro{
        margin-bottom:32px;
    }

    .prep-panel{

        padding:
            30px
            20px;

        border-radius:22px;
    }

    .field-grid{
        grid-template-columns:1fr;
    }

    .field.full{
        grid-column:auto;
    }

    .actions{

        align-items:stretch;

        flex-direction:column-reverse;
    }

    .primary-button{
        width:100%;
    }

}

</style>

</head>

<body>

<header class="nemi-header">

    <div class="nemi-logo">
        <span>Nemi</span>
    </div>

    <div class="account-name">
        <?= h($meName) ?>
    </div>

</header>


<nav class="nemi-nav">

    <a href="/app/realtor_portal.php">
        Home
    </a>

    <a href="/app/transactions.php">
        Transactions
    </a>

    <a href="/app/clients.php">
        Clients
    </a>

    <a
        class="active"
        href="/app/forms.php"
    >
        Forms
    </a>

    <a href="/app/messages.php">
        Messages
    </a>

</nav>


<main class="page">

    <a
        class="back-link"
        href="/app/forms.php"
    >
        ← Back to Forms
    </a>


    <div class="form-eyebrow">
        <?= h($form['publisher']) ?>
        ·
        Rev. <?= h($form['revision']) ?>
    </div>


    <h1 class="form-title">
        <?= h($form['name']) ?>
    </h1>


    <p class="form-intro">
        Let’s prepare your listing agreement.
        I’ve already filled in what I know.
        I just need a few things from you.
    </p>


    <div class="progress-row">

        <div class="progress-line">
            <div class="progress-fill"></div>
        </div>

        <div class="progress-label">
            Listing basics
        </div>

    </div>


    <?php if ($step === 1): ?>


        <form
            class="prep-panel"
            method="post"
            action="/app/form_prepare.php?form=<?= h($formId) ?>"
        >

            <div class="section-heading">

                <h2>
                    Let’s start with the listing.
                </h2>

                <p>
                    These are the basic details that will appear in the agreement.
                </p>

            </div>


            <div class="field-grid">


                <!-- SELLER 1 -->

                <div class="field">

                    <label for="seller_1">
                        Seller
                    </label>

                    <input
                        id="seller_1"
                        name="seller_1"
                        type="text"
                        value="<?= h($draft['seller_1']) ?>"
                        autocomplete="name"
                    >

                    <?php if (isset($errors['seller_1'])): ?>

                        <div class="field-error">
                            <?= h($errors['seller_1']) ?>
                        </div>

                    <?php endif; ?>

                </div>


                <!-- SELLER 2 -->

                <div class="field">

                    <label for="seller_2">
                        Second seller
                    </label>

                    <input
                        id="seller_2"
                        name="seller_2"
                        type="text"
                        value="<?= h($draft['seller_2']) ?>"
                    >

                    <div class="field-help">
                        Leave blank if there is only one seller.
                    </div>

                </div>


                <!-- PROPERTY -->

                <div class="field full">

                    <label for="property_address">
                        Property address
                    </label>

                    <input
                        id="property_address"
                        name="property_address"
                        type="text"
                        value="<?= h($draft['property_address']) ?>"
                        autocomplete="street-address"
                        placeholder="123 Main Street, Wolcott, CT"
                    >

                    <?php if (isset($errors['property_address'])): ?>

                        <div class="field-error">
                            <?= h($errors['property_address']) ?>
                        </div>

                    <?php endif; ?>

                </div>


                <!-- LIST PRICE -->

                <div class="field full">

                    <label for="list_price">
                        Listing price
                    </label>

                    <div class="money-wrap">

                        <span class="money-symbol">
                            $
                        </span>

                        <input
                            id="list_price"
                            name="list_price"
                            type="text"
                            inputmode="decimal"
                            value="<?= h($draft['list_price']) ?>"
                            placeholder="650,000"
                        >

                    </div>

                    <?php if (isset($errors['list_price'])): ?>

                        <div class="field-error">
                            <?= h($errors['list_price']) ?>
                        </div>

                    <?php endif; ?>

                </div>


                <!-- START DATE -->

                <div class="field">

                    <label for="start_date">
                        Listing starts
                    </label>

                    <input
                        id="start_date"
                        name="start_date"
                        type="date"
                        value="<?= h($draft['start_date']) ?>"
                    >

                    <?php if (isset($errors['start_date'])): ?>

                        <div class="field-error">
                            <?= h($errors['start_date']) ?>
                        </div>

                    <?php endif; ?>

                </div>


                <!-- EXPIRATION DATE -->

                <div class="field">

                    <label for="expiration_date">
                        Listing expires
                    </label>

                    <input
                        id="expiration_date"
                        name="expiration_date"
                        type="date"
                        value="<?= h($draft['expiration_date']) ?>"
                    >

                    <?php if (isset($errors['expiration_date'])): ?>

                        <div class="field-error">
                            <?= h($errors['expiration_date']) ?>
                        </div>

                    <?php endif; ?>

                </div>


                <!-- BROKERAGE -->

                <div class="field full">

                    <label for="broker">
                        Brokerage
                    </label>

                    <input
                        id="broker"
                        type="text"
                        value="<?= h($draft['broker']) ?>"
                        readonly
                    >

                    <div class="field-help">
                        Nemi filled this in from your brokerage profile.
                    </div>

                </div>


            </div>


            <div class="actions">

                <a
                    class="secondary-button"
                    href="/app/forms.php"
                >
                    Cancel
                </a>

                <button
                    class="primary-button"
                    type="submit"
                >
                    Continue →
                </button>

            </div>

        </form>


    <?php elseif ($step === 2): ?>

<form
    class="prep-panel"
    method="post"
    action="/app/form_prepare.php?form=<?= h($formId) ?>&step=2"
>

    <div class="section-heading">

        <h2>
            Compensation
        </h2>

        <p>
            Set the compensation terms that will appear in the agreement.
        </p>

    </div>


    <div class="compensation-stack">


        <!-- BROKERAGE SERVICE FEE -->

        <section class="comp-block">

            <div class="comp-block-title">
                Brokerage service fee
            </div>

            <p class="comp-block-copy">
                What service fee did you agree on with the seller?
            </p>


            <div class="segmented">

                <label class="segment-option">

                    <input
                        type="radio"
                        name="service_fee_type"
                        value="percent"
                        <?= $draft['service_fee_type'] === 'percent'
                            ? 'checked'
                            : '' ?>
                    >

                    <span>
                        Percentage
                    </span>

                </label>


                <label class="segment-option">

                    <input
                        type="radio"
                        name="service_fee_type"
                        value="amount"
                        <?= $draft['service_fee_type'] === 'amount'
                            ? 'checked'
                            : '' ?>
                    >

                    <span>
                        Dollar amount
                    </span>

                </label>

            </div>


            <div class="comp-value-row">

                <div
                    class="comp-value"
                    id="service-fee-wrap"
                >

                    <span
                        class="comp-dollar"
                        id="service-dollar"
                    >
                        $
                    </span>

                    <input
                        id="service_fee_value"
                        name="service_fee_value"
                        type="text"
                        inputmode="decimal"
                        value="<?= h($draft['service_fee_value']) ?>"
                        autocomplete="off"
                    >

                    <span
                        class="comp-unit"
                        id="service-percent"
                    >
                        %
                    </span>

                </div>

            </div>


            <?php if (isset($errors['service_fee_value'])): ?>

                <div class="field-error">
                    <?= h($errors['service_fee_value']) ?>
                </div>

            <?php endif; ?>

        </section>



        <!-- BUYER-BROKER COMPENSATION -->

        <section class="comp-block">

            <div class="comp-block-title">
                Buyer-broker compensation
            </div>

            <p class="comp-block-copy">
                Will your recommendation include offering compensation
                to a buyer’s brokerage?
            </p>


            <div class="authorization-choices">


                <label class="authorization-choice">

                    <input
                        type="radio"
                        name="buyer_broker_authorized"
                        value="yes"
                        <?= $draft['buyer_broker_authorized'] === 'yes'
                            ? 'checked'
                            : '' ?>
                    >

                    <span class="authorization-card">

                        <strong>
                            Yes
                        </strong>

                        <small>
                            Include a buyer-broker compensation amount.
                        </small>

                    </span>

                </label>


                <label class="authorization-choice">

                    <input
                        type="radio"
                        name="buyer_broker_authorized"
                        value="no"
                        <?= $draft['buyer_broker_authorized'] === 'no'
                            ? 'checked'
                            : '' ?>
                    >

                    <span class="authorization-card">

                        <strong>
                            No
                        </strong>

                        <small>
                            Do not include buyer-broker compensation.
                        </small>

                    </span>

                </label>


            </div>


            <div
                class="buyer-comp-details"
                id="buyer-comp-details"
            >

                <div
                    class="field"
                    style="margin-top:24px;"
                >

                    <label>
                        Amount to offer
                    </label>

                </div>


                <div
                    class="segmented"
                    style="margin-top:10px;"
                >

                    <label class="segment-option">

                        <input
                            type="radio"
                            name="buyer_broker_fee_type"
                            value="percent"
                            <?= $draft['buyer_broker_fee_type'] === 'percent'
                                ? 'checked'
                                : '' ?>
                        >

                        <span>
                            Percentage
                        </span>

                    </label>


                    <label class="segment-option">

                        <input
                            type="radio"
                            name="buyer_broker_fee_type"
                            value="amount"
                            <?= $draft['buyer_broker_fee_type'] === 'amount'
                                ? 'checked'
                                : '' ?>
                        >

                        <span>
                            Dollar amount
                        </span>

                    </label>

                </div>


                <div class="comp-value-row">

                    <div
                        class="comp-value"
                        id="buyer-fee-wrap"
                    >

                        <span
                            class="comp-dollar"
                            id="buyer-dollar"
                        >
                            $
                        </span>

                        <input
                            id="buyer_broker_fee_value"
                            name="buyer_broker_fee_value"
                            type="text"
                            inputmode="decimal"
                            value="<?= h($draft['buyer_broker_fee_value']) ?>"
                            autocomplete="off"
                        >

                        <span
                            class="comp-unit"
                            id="buyer-percent"
                        >
                            %
                        </span>

                    </div>

                </div>


                <?php if (isset($errors['buyer_broker_fee_value'])): ?>

                    <div class="field-error">
                        <?= h($errors['buyer_broker_fee_value']) ?>
                    </div>

                <?php endif; ?>

            </div>

        </section>

    </div>


    <div class="actions">

        <a
            class="secondary-button"
            href="/app/form_prepare.php?form=<?= h($formId) ?>&step=1"
        >
            ← Back
        </a>


        <button
            class="primary-button"
            type="submit"
        >
            Continue →
        </button>

    </div>

</form>


<?php elseif ($step === 3): ?>


<div class="coming-next">

    <div class="form-eyebrow">
        Next
    </div>

    <h2>
        Protection Period
    </h2>

    <p>
        Compensation is saved.
        Next we’ll set the protection period.
    </p>


    <a
        class="secondary-button"
        href="/app/form_prepare.php?form=<?= h($formId) ?>&step=2"
    >
        ← Back to compensation
    </a>

</div>


<?php endif; ?>

</main>

</body>

</html>