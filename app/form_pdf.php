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
    exit('You do not have permission to access this document.');
}

require_once dirname(__DIR__) . '/vendor/autoload.php';

use setasign\Fpdi\Fpdi;

$formId =
    (string)($_GET['form'] ?? '');

$version =
    (string)($_GET['version'] ?? 'prepared');

$debugGrid =
    (string)($_GET['debug'] ?? '')
    === 'grid';

if ($formId !== 'exclusive_right_to_sell') {
    http_response_code(404);
    exit('Form not found.');
}

$templatePath =
    dirname(__DIR__)
    . '/forms/templates/exclusive_right_to_sell_10_25.pdf';

if (!is_file($templatePath)) {
    http_response_code(500);
    exit('PDF template could not be found.');
}

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
        'No prepared agreement was found. Please prepare the form first.'
    );
}

/*
|--------------------------------------------------------------------------
| PDF COORDINATE GRID
|--------------------------------------------------------------------------
|
| Temporary development tool.
|
| Every major line = 10 mm
| Every minor line = 5 mm
|
| Use:
| &debug=grid
|
*/

function drawCoordinateGrid(
    Fpdi $pdf,
    float $pageWidth,
    float $pageHeight
): void {

    /*
    |--------------------------------------------------------------------------
    | MINOR GRID — 5 MM
    |--------------------------------------------------------------------------
    */

    $pdf->SetDrawColor(
        210,
        220,
        225
    );

    $pdf->SetLineWidth(
        0.1
    );


    for (
        $x = 5;
        $x < $pageWidth;
        $x += 5
    ) {

        $pdf->Line(
            $x,
            0,
            $x,
            $pageHeight
        );
    }


    for (
        $y = 5;
        $y < $pageHeight;
        $y += 5
    ) {

        $pdf->Line(
            0,
            $y,
            $pageWidth,
            $y
        );
    }


    /*
    |--------------------------------------------------------------------------
    | MAJOR GRID — 10 MM
    |--------------------------------------------------------------------------
    */

    $pdf->SetDrawColor(
        120,
        145,
        155
    );

    $pdf->SetTextColor(
        80,
        100,
        110
    );

    $pdf->SetFont(
        'Helvetica',
        '',
        6
    );


    for (
        $x = 10;
        $x < $pageWidth;
        $x += 10
    ) {

        $pdf->Line(
            $x,
            0,
            $x,
            $pageHeight
        );

        $pdf->SetXY(
            $x + 0.5,
            1
        );

        $pdf->Cell(
            9,
            3,
            (string)$x,
            0,
            0
        );
    }


    for (
        $y = 10;
        $y < $pageHeight;
        $y += 10
    ) {

        $pdf->Line(
            0,
            $y,
            $pageWidth,
            $y
        );

        $pdf->SetXY(
            1,
            $y + 0.5
        );

        $pdf->Cell(
            8,
            3,
            (string)$y,
            0,
            0
        );
    }


    /*
    |--------------------------------------------------------------------------
    | RESTORE NORMAL COLOR
    |--------------------------------------------------------------------------
    */

    $pdf->SetDrawColor(
        0,
        0,
        0
    );

    $pdf->SetTextColor(
        0,
        0,
        0
    );
}

$pdf = new Fpdi();

$pageCount =
    $pdf->setSourceFile($templatePath);

for (
    $pageNumber = 1;
    $pageNumber <= $pageCount;
    $pageNumber++
) {

    $templateId =
        $pdf->importPage($pageNumber);

    $size =
        $pdf->getTemplateSize($templateId);

    $pdf->AddPage(
        $size['orientation'],
        [
            $size['width'],
            $size['height']
        ]
    );

    $pdf->useTemplate($templateId);

    /*
|--------------------------------------------------------------------------
| DEVELOPMENT COORDINATE GRID
|--------------------------------------------------------------------------
*/

if ($debugGrid) {

    drawCoordinateGrid(
        $pdf,
        (float)$size['width'],
        (float)$size['height']
    );
}

    /*
    |--------------------------------------------------------------------------
    | DEFAULT FIELD FONT
    |--------------------------------------------------------------------------
    */

    $pdf->SetFont(
        'Helvetica',
        '',
        9
    );

    $pdf->SetTextColor(
        0,
        0,
        0
    );


    /*
    |--------------------------------------------------------------------------
    | PAGE 1
    |--------------------------------------------------------------------------
    */
/*
|--------------------------------------------------------------------------
| PAGE 1
|--------------------------------------------------------------------------
*/

if ($pageNumber === 1) {

    /*
    |--------------------------------------------------------------------------
    | SELLER(S)
    |--------------------------------------------------------------------------
    */

    $sellerNames =
        trim(
            (string)($draft['seller_1'] ?? '')
        );

    if (!empty($draft['seller_2'])) {

        $sellerNames .=
            ' & '
            . trim(
                (string)$draft['seller_2']
            );
    }

    $pdf->SetFont(
        'Helvetica',
        '',
        8
    );

    $pdf->SetXY(
        55,
        31.5
    );

    $pdf->Cell(
        110,
        4,
        $sellerNames,
        0,
        0
    );


    /*
    |--------------------------------------------------------------------------
    | BROKERAGE
    |--------------------------------------------------------------------------
    */

    $pdf->SetXY(
        18,
        37.5
    );

    $pdf->Cell(
        94,
        4,
        (string)($draft['broker'] ?? ''),
        0,
        0
    );


    /*
    |--------------------------------------------------------------------------
    | PROPERTY ADDRESS
    |--------------------------------------------------------------------------
    */

    $pdf->SetXY(
        18,
        44
    );

    $pdf->Cell(
        112,
        4,
        (string)($draft['property_address'] ?? ''),
        0,
        0
    );


    /*
    |--------------------------------------------------------------------------
    | LIST PRICE
    |--------------------------------------------------------------------------
    */

    $listPrice =
        trim(
            (string)($draft['list_price'] ?? '')
        );

    if ($listPrice !== '') {

        $listPrice =
            '$'
            . number_format(
                (float)str_replace(
                    [',', '$'],
                    '',
                    $listPrice
                ),
                0
            );
    }

    $pdf->SetXY(
        96,
        50
    );

    $pdf->Cell(
        43,
        4,
        $listPrice,
        0,
        0
    );


    /*
    |--------------------------------------------------------------------------
    | START DATE
    |--------------------------------------------------------------------------
    */

    $startDate =
        (string)($draft['start_date'] ?? '');

    if ($startDate !== '') {

        $timestamp =
            strtotime($startDate);

        if ($timestamp !== false) {

            $startDate =
                date(
                    'm/d/Y',
                    $timestamp
                );
        }
    }

    $pdf->SetXY(
        72,
        56
    );

    $pdf->Cell(
        34,
        4,
        $startDate,
        0,
        0
    );


    /*
    |--------------------------------------------------------------------------
    | EXPIRATION DATE
    |--------------------------------------------------------------------------
    */

    $expirationDate =
        (string)(
            $draft['expiration_date']
            ?? ''
        );

    if ($expirationDate !== '') {

        $timestamp =
            strtotime($expirationDate);

        if ($timestamp !== false) {

            $expirationDate =
                date(
                    'm/d/Y',
                    $timestamp
                );
        }
    }

    $pdf->SetXY(
        22,
        62
    );

    $pdf->Cell(
        39,
        4,
        $expirationDate,
        0,
        0
    );


    /*
    |--------------------------------------------------------------------------
    | BROKERAGE SERVICE FEE
    |--------------------------------------------------------------------------
    */

    $serviceFeeValue =
        trim(
            (string)(
                $draft['service_fee_value']
                ?? ''
            )
        );

    if (
        ($draft['service_fee_type'] ?? '')
        === 'percent'
    ) {

        $pdf->SetXY(
            136,
            89
        );

        $pdf->Cell(
            22,
            4,
            $serviceFeeValue,
            0,
            0
        );

    } elseif ($serviceFeeValue !== '') {

        $pdf->SetXY(
            31,
            94
        );

        $pdf->Cell(
            45,
            4,
            '$'
            . number_format(
                (float)str_replace(
                    [',', '$'],
                    '',
                    $serviceFeeValue
                ),
                0
            ),
            0,
            0
        );
    }


    /*
    |--------------------------------------------------------------------------
    | BUYER-BROKER AUTHORIZATION
    |--------------------------------------------------------------------------
    */

    $buyerAuthorized =
        ($draft['buyer_broker_authorized'] ?? 'yes')
        === 'yes';

    $pdf->SetFont(
        'Helvetica',
        'B',
        9
    );

    if ($buyerAuthorized) {

        $pdf->SetXY(
            45.8,
            155
        );

    } else {

        $pdf->SetXY(
            62,
            155
        );
    }

    $pdf->Cell(
        4,
        4,
        'X',
        0,
        0
    );


    /*
    |--------------------------------------------------------------------------
    | BUYER-BROKER COMPENSATION
    |--------------------------------------------------------------------------
    */

    if ($buyerAuthorized) {

        $buyerFeeValue =
            trim(
                (string)(
                    $draft['buyer_broker_fee_value']
                    ?? ''
                )
            );

        $pdf->SetFont(
            'Helvetica',
            '',
            8
        );

        if (
            ($draft['buyer_broker_fee_type'] ?? '')
            === 'percent'
        ) {

            $pdf->SetXY(
                136,
                166
            );

            $pdf->Cell(
                18,
                4,
                $buyerFeeValue,
                0,
                0
            );

        } elseif ($buyerFeeValue !== '') {

            $pdf->SetXY(
                34,
                172
            );

            $pdf->Cell(
                34,
                4,
                '$'
                . number_format(
                    (float)str_replace(
                        [',', '$'],
                        '',
                        $buyerFeeValue
                    ),
                    0
                ),
                0,
                0
            );
        }
    }
}



/*
|--------------------------------------------------------------------------
| PAGE 2
|--------------------------------------------------------------------------
*/

if ($pageNumber === 2) {

    /*
    |--------------------------------------------------------------------------
    | SPECIAL SHOWING INSTRUCTIONS
    |--------------------------------------------------------------------------
    */

    $specialShowingInstructions =
    trim(
        (string)(
            $draft['showing_instructions']
            ?? ''
        )
    );

    if ($specialShowingInstructions !== '') {

        $pdf->SetFont(
            'Helvetica',
            '',
            8
        );

        $pdf->SetTextColor(
            0,
            0,
            0
        );

        $pdf->SetXY(
            57,
            21
        );

        $pdf->Cell(
            102,
            4,
            $specialShowingInstructions,
            0,
            0
        );
    }
}

/*
|--------------------------------------------------------------------------
| PAGE 3
|--------------------------------------------------------------------------
*/

if ($pageNumber === 3) {

    $pdf->SetFont(
        'Helvetica',
        '',
        8
    );

    $pdf->SetTextColor(
        0,
        0,
        0
    );


    /*
    |--------------------------------------------------------------------------
    | OTHER TERMS / SPECIAL INSTRUCTIONS
    |--------------------------------------------------------------------------
    */

    $otherTerms =
        trim(
            (string)(
                $draft['other_terms']
                ?? ''
            )
        );

    if ($otherTerms !== '') {

        $pdf->SetXY(
            105,
            39
        );

        $pdf->Cell(
            98,
            4,
            $otherTerms,
            0,
            0
        );
    }


    /*
    |--------------------------------------------------------------------------
    | SELLER INFORMATION
    |--------------------------------------------------------------------------
    */

    $seller1 =
        trim(
            (string)(
                $draft['seller_1']
                ?? ''
            )
        );

    $seller2 =
        trim(
            (string)(
                $draft['seller_2']
                ?? ''
            )
        );


    /*
    |--------------------------------------------------------------------------
    | AGREEMENT-LEVEL SELLER CONTACT DETAILS
    |
    | Seller 1 first.
    | If Seller 1 does not have the information,
    | use Seller 2's information.
    |--------------------------------------------------------------------------
    */

    $sellerEmail =
        trim(
            (string)(
                $draft['seller_1_email']
                ?? ''
            )
        );

    if ($sellerEmail === '') {

        $sellerEmail =
            trim(
                (string)(
                    $draft['seller_2_email']
                    ?? ''
                )
            );
    }


    $sellerStreet =
        trim(
            (string)(
                $draft['seller_1_street']
                ?? ''
            )
        );

    if ($sellerStreet === '') {

        $sellerStreet =
            trim(
                (string)(
                    $draft['seller_2_street']
                    ?? ''
                )
            );
    }


    $sellerCity =
        trim(
            (string)(
                $draft['seller_1_city']
                ?? ''
            )
        );

    if ($sellerCity === '') {

        $sellerCity =
            trim(
                (string)(
                    $draft['seller_2_city']
                    ?? ''
                )
            );
    }


    $sellerState =
        trim(
            (string)(
                $draft['seller_1_state']
                ?? ''
            )
        );

    if ($sellerState === '') {

        $sellerState =
            trim(
                (string)(
                    $draft['seller_2_state']
                    ?? ''
                )
            );
    }


    $sellerZip =
        trim(
            (string)(
                $draft['seller_1_zip']
                ?? ''
            )
        );

    if ($sellerZip === '') {

        $sellerZip =
            trim(
                (string)(
                    $draft['seller_2_zip']
                    ?? ''
                )
            );
    }


    $sellerCityStateZip =
        trim(
            $sellerCity
            . (
                $sellerCity !== ''
                && $sellerState !== ''
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
    | SELLER 1
    |--------------------------------------------------------------------------
    */

    if ($seller1 !== '') {

        $pdf->SetXY(
            14,
            201
        );

        $pdf->Cell(
            67,
            4,
            $seller1,
            0,
            0
        );
    }


    /*
    |--------------------------------------------------------------------------
    | SELLER 2
    |--------------------------------------------------------------------------
    */

    if ($seller2 !== '') {

        $pdf->SetXY(
            14,
            211
        );

        $pdf->Cell(
            67,
            4,
            $seller2,
            0,
            0
        );
    }


    /*
    |--------------------------------------------------------------------------
    | SELLER MAILING ADDRESS
    |--------------------------------------------------------------------------
    */

    if ($sellerStreet !== '') {

        $pdf->SetXY(
            14,
            221
        );

        $pdf->Cell(
            67,
            4,
            $sellerStreet,
            0,
            0
        );
    }


    if ($sellerCityStateZip !== '') {

        $pdf->SetXY(
            14,
            231
        );

        $pdf->Cell(
            67,
            4,
            $sellerCityStateZip,
            0,
            0
        );
    }


    /*
    |--------------------------------------------------------------------------
    | SELLER EMAIL
    |--------------------------------------------------------------------------
    */

    if ($sellerEmail !== '') {

        $pdf->SetXY(
            14,
            241
        );

        $pdf->Cell(
            67,
            4,
            $sellerEmail,
            0,
            0
        );
    }


    /*
    |--------------------------------------------------------------------------
    | BROKERAGE
    |--------------------------------------------------------------------------
    */

    $brokerageName =
        trim(
            (string)(
                $draft['broker']
                ?? ''
            )
        );

    if ($brokerageName !== '') {

        $pdf->SetXY(
            102,
            201
        );

        $pdf->Cell(
            66,
            4,
            $brokerageName,
            0,
            0
        );
    }


    /*
    |--------------------------------------------------------------------------
    | BROKERAGE ADDRESS
    |--------------------------------------------------------------------------
    |
    | Temporary Fercodini values.
    | Later these come from the brokerage profile.
    |--------------------------------------------------------------------------
    */

    $brokerageStreet =
        '484 Wolcott Road';

    $brokerageCityStateZip =
        'Wolcott, CT 06716';


    $pdf->SetXY(
        102,
        211
    );

    $pdf->Cell(
        66,
        4,
        $brokerageStreet,
        0,
        0
    );


    $pdf->SetXY(
        102,
        221
    );

    $pdf->Cell(
        66,
        4,
        $brokerageCityStateZip,
        0,
        0
    );


    /*
    |--------------------------------------------------------------------------
    | AUTHORIZED AGENT
    |--------------------------------------------------------------------------
    */

    $agentName =
        trim(
            (string)(
                $_SESSION['name']
                ?? ''
            )
        );

    if ($agentName === '') {

        $agentName =
            trim(
                (string)(
                    $_SESSION['email']
                    ?? ''
                )
            );
    }


    $pdf->SetXY(
        102,
        231
    );

    $pdf->Cell(
        53,
        4,
        $agentName,
        0,
        0
    );


    /*
    |--------------------------------------------------------------------------
    | AGENT EMAIL
    |--------------------------------------------------------------------------
    */

    $agentEmail =
        trim(
            (string)(
                $_SESSION['email']
                ?? ''
            )
        );

    if ($agentEmail !== '') {

        $pdf->SetXY(
            102,
            241
        );

        $pdf->Cell(
            66,
            4,
            $agentEmail,
            0,
            0
        );
    }


    /*
    |--------------------------------------------------------------------------
    | AGENT DATE
    |--------------------------------------------------------------------------
    |
    | For now this is intentionally blank.
    | The actual signing date will be written when
    | the Realtor signs the agreement.
    |--------------------------------------------------------------------------
    */
}

}

$fileName =
    $version === 'signed'
        ? 'Exclusive-Right-to-Sell-Signed.pdf'
        : 'Exclusive-Right-to-Sell-Prepared.pdf';

$pdf->Output(
    'I',
    $fileName
);

exit;