<?php

declare(strict_types=1);

session_start();

if (empty($_SESSION['uid'])) {
    header('Location: /auth/login_form.php');
    exit;
}

$role =
    strtolower(
        (string)($_SESSION['role'] ?? '')
    );

if (
    $role !== 'realtor'
    &&
    $role !== 'admin'
) {
    http_response_code(403);
    exit(
        'You do not have permission to access this document.'
    );
}


/*
|--------------------------------------------------------------------------
| FORM
|--------------------------------------------------------------------------
*/

$formId =
    (string)($_GET['form'] ?? '');

$version =
    (string)($_GET['version'] ?? 'prepared');

if ($formId !== 'exclusive_right_to_sell') {
    http_response_code(404);
    exit('Form not found.');
}


/*
|--------------------------------------------------------------------------
| NAMED-FIELD PDF TEMPLATE
|--------------------------------------------------------------------------
*/

$templatePath =
    dirname(__DIR__)
    . '/forms/templates/'
    . 'exclusive_right_to_sell_10_25_nemi.pdf';

if (!is_file($templatePath)) {
    http_response_code(500);
    exit(
        'PDF template could not be found.'
    );
}


/*
|--------------------------------------------------------------------------
| CURRENT FORM DRAFT
|--------------------------------------------------------------------------
*/

$draftKey =
    'form_draft_' . $formId;

$draft =
    $_SESSION[$draftKey]
    ?? null;

if (
    !is_array($draft)
    ||
    empty($draft)
) {
    http_response_code(400);

    exit(
        'No prepared agreement was found. '
        . 'Please prepare the form first.'
    );
}


/*
|--------------------------------------------------------------------------
| HELPERS
|--------------------------------------------------------------------------
*/

function pdfFieldText(
    mixed $value
): string {

    return trim(
        (string)$value
    );
}


function pdfDate(
    mixed $value
): string {

    $value =
        trim(
            (string)$value
        );

    if ($value === '') {
        return '';
    }

    $timestamp =
        strtotime($value);

    if ($timestamp === false) {
        return $value;
    }

    return date(
        'm/d/Y',
        $timestamp
    );
}


function pdfMoney(
    mixed $value,
    bool $includeDollarSign = false
): string {

    $value =
        trim(
            (string)$value
        );

    if ($value === '') {
        return '';
    }

    $numeric =
        str_replace(
            [
                '$',
                ','
            ],
            '',
            $value
        );

    if (!is_numeric($numeric)) {
        return $value;
    }

    $formatted =
        number_format(
            (float)$numeric,
            0,
            '.',
            ','
        );

    return
        $includeDollarSign
            ? '$' . $formatted
            : $formatted;
}


/*
|--------------------------------------------------------------------------
| FDF ESCAPING
|--------------------------------------------------------------------------
*/

function fdfEscape(
    string $value
): string {

    $value =
        str_replace(
            '\\',
            '\\\\',
            $value
        );

    $value =
        str_replace(
            '(',
            '\\(',
            $value
        );

    $value =
        str_replace(
            ')',
            '\\)',
            $value
        );

    $value =
        str_replace(
            [
                "\r\n",
                "\r"
            ],
            "\n",
            $value
        );

    return $value;
}


function fdfTextField(
    string $name,
    string $value
): string {

    return
        '<< /T ('
        . fdfEscape($name)
        . ') /V ('
        . fdfEscape($value)
        . ') >>';
}


function fdfCheckboxField(
    string $name,
    bool $checked
): string {

    return
        '<< /T ('
        . fdfEscape($name)
        . ') /V /'
        . (
            $checked
                ? 'Yes'
                : 'Off'
        )
        . ' >>';
}


/*
|--------------------------------------------------------------------------
| SELLER NAMES
|--------------------------------------------------------------------------
*/

$seller1 =
    pdfFieldText(
        $draft['seller_1']
        ?? ''
    );

$seller2 =
    pdfFieldText(
        $draft['seller_2']
        ?? ''
    );

$sellerNames =
    $seller1;

if ($seller2 !== '') {

    $sellerNames .=
        (
            $sellerNames !== ''
                ? ' & '
                : ''
        )
        . $seller2;
}


/*
|--------------------------------------------------------------------------
| LIST PRICE
|--------------------------------------------------------------------------
|
| If Realtor selected Decide later,
| the actual PDF field remains blank.
|--------------------------------------------------------------------------
*/

$listPrice = '';

if (
    empty(
        $draft['list_price_decide_later']
    )
) {

    $listPrice =
        pdfMoney(
            $draft['list_price']
            ?? ''
        );
}


/*
|--------------------------------------------------------------------------
| LISTING PERIOD
|--------------------------------------------------------------------------
|
| One shared Decide Later state controls
| both start and expiration.
|--------------------------------------------------------------------------
*/

$startDate = '';
$expirationDate = '';

if (
    empty(
        $draft['listing_period_decide_later']
    )
) {

    $startDate =
        pdfDate(
            $draft['start_date']
            ?? ''
        );

    $expirationDate =
        pdfDate(
            $draft['expiration_date']
            ?? ''
        );
}


/*
|--------------------------------------------------------------------------
| BROKER COMPENSATION
|--------------------------------------------------------------------------
*/

$commissionPercentage = '';
$commissionDollar = '';

$serviceFeeValue =
    pdfFieldText(
        $draft['service_fee_value']
        ?? ''
    );

$serviceFeeType =
    pdfFieldText(
        $draft['service_fee_type']
        ?? ''
    );

if (
    $serviceFeeType === 'percent'
) {

    $commissionPercentage =
        $serviceFeeValue;

} elseif (
    $serviceFeeValue !== ''
) {

    $commissionDollar =
        pdfMoney(
            $serviceFeeValue
        );
}


/*
|--------------------------------------------------------------------------
| BUYER BROKER AUTHORIZATION
|--------------------------------------------------------------------------
*/

$buyerBrokerAuthorized =
    (
        $draft['buyer_broker_authorized']
        ?? 'yes'
    ) === 'yes';


/*
|--------------------------------------------------------------------------
| BUYER BROKER COMPENSATION
|--------------------------------------------------------------------------
*/

$buyerBrokerPercentage = '';
$buyerBrokerDollar = '';

if ($buyerBrokerAuthorized) {

    $buyerBrokerFeeValue =
        pdfFieldText(
            $draft['buyer_broker_fee_value']
            ?? ''
        );

    $buyerBrokerFeeType =
        pdfFieldText(
            $draft['buyer_broker_fee_type']
            ?? ''
        );

    if (
        $buyerBrokerFeeType === 'percent'
    ) {

        $buyerBrokerPercentage =
            $buyerBrokerFeeValue;

    } elseif (
        $buyerBrokerFeeValue !== ''
    ) {

        $buyerBrokerDollar =
            pdfMoney(
                $buyerBrokerFeeValue
            );
    }
}


/*
|--------------------------------------------------------------------------
| PROTECTION PERIOD
|--------------------------------------------------------------------------
|
| Supports either current or older draft key.
|--------------------------------------------------------------------------
*/

$protectionPeriodDays =
    pdfFieldText(
        $draft['protection_period_days']
        ?? $draft['protection_period']
        ?? ''
    );


/*
|--------------------------------------------------------------------------
| SHOWING / SURVEILLANCE
|--------------------------------------------------------------------------
*/

$showingInstructions =
    pdfFieldText(
        $draft['showing_instructions']
        ?? ''
    );

$audioSurveillance =
    !empty(
        $draft['audio_surveillance']
    );

$videoSurveillance =
    !empty(
        $draft['video_surveillance']
    );


/*
|--------------------------------------------------------------------------
| OTHER TERMS
|--------------------------------------------------------------------------
*/

$specialInstructions =
    pdfFieldText(
        $draft['other_terms']
        ?? $draft['special_instructions']
        ?? ''
    );


/*
|--------------------------------------------------------------------------
| FAIR HOUSING INITIALS
|--------------------------------------------------------------------------
|
| Blank until seller initials are captured.
|--------------------------------------------------------------------------
*/

$sellerInitialsFairHousing =
    pdfFieldText(
        $draft['seller_initials_fair_housing']
        ?? ''
    );


/*
|--------------------------------------------------------------------------
| AGREEMENT-LEVEL SELLER CONTACT DETAILS
|--------------------------------------------------------------------------
|
| Seller 1 first.
| Seller 2 is fallback when Seller 1 lacks
| a particular contact value.
|--------------------------------------------------------------------------
*/

$sellerEmail =
    pdfFieldText(
        $draft['seller_1_email']
        ?? ''
    );

if ($sellerEmail === '') {

    $sellerEmail =
        pdfFieldText(
            $draft['seller_2_email']
            ?? ''
        );
}


$sellerStreet =
    pdfFieldText(
        $draft['seller_1_street']
        ?? ''
    );

if ($sellerStreet === '') {

    $sellerStreet =
        pdfFieldText(
            $draft['seller_2_street']
            ?? ''
        );
}


$sellerCity =
    pdfFieldText(
        $draft['seller_1_city']
        ?? ''
    );

if ($sellerCity === '') {

    $sellerCity =
        pdfFieldText(
            $draft['seller_2_city']
            ?? ''
        );
}


$sellerState =
    pdfFieldText(
        $draft['seller_1_state']
        ?? ''
    );

if ($sellerState === '') {

    $sellerState =
        pdfFieldText(
            $draft['seller_2_state']
            ?? ''
        );
}


$sellerZip =
    pdfFieldText(
        $draft['seller_1_zip']
        ?? ''
    );

if ($sellerZip === '') {

    $sellerZip =
        pdfFieldText(
            $draft['seller_2_zip']
            ?? ''
        );
}


$sellerCityStateZip =
    trim(
        $sellerCity
        . (
            $sellerCity !== ''
            &&
            $sellerState !== ''
                ? ', '
                : ''
        )
        . $sellerState
        . (
            $sellerZip !== ''
                ? ' ' . $sellerZip
                : ''
        )
    );


/*
|--------------------------------------------------------------------------
| BROKERAGE
|--------------------------------------------------------------------------
|
| Name currently comes from the draft.
| Address remains temporary Fercodini data
| until brokerage profiles own these values.
|--------------------------------------------------------------------------
*/

$brokerageName =
    pdfFieldText(
        $draft['broker']
        ?? ''
    );

$brokerageAddress =
    '484 Wolcott Road';

$brokerageCityStateZip =
    'Wolcott, CT 06716';


/*
|--------------------------------------------------------------------------
| AGENT
|--------------------------------------------------------------------------
*/

$agentEmail =
    pdfFieldText(
        $_SESSION['email']
        ?? ''
    );


/*
|--------------------------------------------------------------------------
| SIGNATURE FIELDS
|--------------------------------------------------------------------------
|
| Prepared version intentionally leaves
| signatures and dates blank.
|
| Later the Nemi signing workflow will populate
| these fields from the signed-version data.
|--------------------------------------------------------------------------
*/

$seller1Signature = '';
$seller1SignatureDate = '';

$seller2Signature = '';
$seller2SignatureDate = '';

$agentSignature = '';
$agentSignatureDate = '';


/*
|--------------------------------------------------------------------------
| BUILD FDF
|--------------------------------------------------------------------------
*/

$fields = [];


/*
|--------------------------------------------------------------------------
| PAGE 1
|--------------------------------------------------------------------------
*/

$fields[] =
    fdfTextField(
        'seller_names',
        $sellerNames
    );

$fields[] =
    fdfTextField(
        'brokerage_name',
        $brokerageName
    );

$fields[] =
    fdfTextField(
        'property_address',
        pdfFieldText(
            $draft['property_address']
            ?? ''
        )
    );

$fields[] =
    fdfTextField(
        'list_price',
        $listPrice
    );

$fields[] =
    fdfTextField(
        'start_date',
        $startDate
    );

$fields[] =
    fdfTextField(
        'expiration_date',
        $expirationDate
    );

$fields[] =
    fdfTextField(
        'commission_percentage',
        $commissionPercentage
    );

$fields[] =
    fdfTextField(
        'commission_dollar',
        $commissionDollar
    );

$fields[] =
    fdfCheckboxField(
        'buyer_broker_authorized_yes',
        $buyerBrokerAuthorized
    );

$fields[] =
    fdfCheckboxField(
        'buyer_broker_authorized_no',
        !$buyerBrokerAuthorized
    );

$fields[] =
    fdfTextField(
        'buyer_broker_compensation_percentage',
        $buyerBrokerPercentage
    );

$fields[] =
    fdfTextField(
        'buyer_broker_compensation_dollar',
        $buyerBrokerDollar
    );

$fields[] =
    fdfTextField(
        'protection_period_days',
        $protectionPeriodDays
    );


/*
|--------------------------------------------------------------------------
| PAGE 2
|--------------------------------------------------------------------------
*/

$fields[] =
    fdfTextField(
        'showing_instructions',
        $showingInstructions
    );

$fields[] =
    fdfCheckboxField(
        'audio_surveillance',
        $audioSurveillance
    );

$fields[] =
    fdfCheckboxField(
        'video_surveillance',
        $videoSurveillance
    );


/*
|--------------------------------------------------------------------------
| PAGE 3
|--------------------------------------------------------------------------
*/

$fields[] =
    fdfTextField(
        'special_instructions',
        $specialInstructions
    );

$fields[] =
    fdfTextField(
        'seller_initials_fair_housing',
        $sellerInitialsFairHousing
    );

$fields[] =
    fdfTextField(
        'seller_1_signature',
        $seller1Signature
    );

$fields[] =
    fdfTextField(
        'seller_1_signature_date',
        $seller1SignatureDate
    );

$fields[] =
    fdfTextField(
        'seller_2_signature',
        $seller2Signature
    );

$fields[] =
    fdfTextField(
        'seller_2_signature_date',
        $seller2SignatureDate
    );

$fields[] =
    fdfTextField(
        'seller_address',
        $sellerStreet
    );

$fields[] =
    fdfTextField(
        'seller_city_state_zip',
        $sellerCityStateZip
    );

$fields[] =
    fdfTextField(
        'seller_email_address',
        $sellerEmail
    );

$fields[] =
    fdfTextField(
        'brokerage_address',
        $brokerageAddress
    );

$fields[] =
    fdfTextField(
        'brokerage_city_state_zip',
        $brokerageCityStateZip
    );

$fields[] =
    fdfTextField(
        'agent_signature',
        $agentSignature
    );

$fields[] =
    fdfTextField(
        'agent_signature_date',
        $agentSignatureDate
    );

$fields[] =
    fdfTextField(
        'agent_email',
        $agentEmail
    );


$fdf =
    "%FDF-1.2\n"
    . "1 0 obj\n"
    . "<<\n"
    . "/FDF\n"
    . "<<\n"
    . "/Fields [\n"
    . implode(
        "\n",
        $fields
    )
    . "\n]\n"
    . ">>\n"
    . ">>\n"
    . "endobj\n"
    . "trailer\n"
    . "<< /Root 1 0 R >>\n"
    . "%%EOF\n";


/*
|--------------------------------------------------------------------------
| TEMP FILES
|--------------------------------------------------------------------------
*/

$tempBase =
    tempnam(
        sys_get_temp_dir(),
        'nemi_pdf_'
    );

if ($tempBase === false) {
    http_response_code(500);
    exit(
        'Could not create temporary PDF files.'
    );
}

$fdfPath =
    $tempBase . '.fdf';

$outputPath =
    $tempBase . '.pdf';

@unlink($tempBase);


if (
    file_put_contents(
        $fdfPath,
        $fdf
    ) === false
) {

    http_response_code(500);

    exit(
        'Could not prepare PDF field data.'
    );
}


/*
|--------------------------------------------------------------------------
| FILL PDF BY FIELD NAME
|--------------------------------------------------------------------------
*/

$pdftk =
    '/usr/bin/pdftk';

if (!is_executable($pdftk)) {

    @unlink($fdfPath);

    http_response_code(500);

    exit(
        'PDF form engine is not available.'
    );
}


$command =
    escapeshellarg($pdftk)
    . ' '
    . escapeshellarg($templatePath)
    . ' fill_form '
    . escapeshellarg($fdfPath)
    . ' output '
    . escapeshellarg($outputPath)
    . ' need_appearances';


$output = [];
$returnCode = 0;

exec(
    $command . ' 2>&1',
    $output,
    $returnCode
);


@unlink($fdfPath);


if (
    $returnCode !== 0
    ||
    !is_file($outputPath)
) {

    @unlink($outputPath);

    http_response_code(500);

    exit(
        'The prepared PDF could not be generated.'
    );
}


/*
|--------------------------------------------------------------------------
| SEND PDF TO BROWSER
|--------------------------------------------------------------------------
*/

$fileName =
    $version === 'signed'
        ? 'Exclusive-Right-to-Sell-Signed.pdf'
        : 'Exclusive-Right-to-Sell-Prepared.pdf';


header(
    'Content-Type: application/pdf'
);

header(
    'Content-Disposition: inline; filename="'
    . $fileName
    . '"'
);

header(
    'Content-Length: '
    . filesize($outputPath)
);

header(
    'Cache-Control: private, no-store, max-age=0'
);


readfile($outputPath);

@unlink($outputPath);

exit;