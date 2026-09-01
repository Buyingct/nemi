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
    $_SESSION[$draftKey] ?? [];

if (!is_array($draft)) {
    $draft = [];
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

        if (
            !empty($draft['seller_2'])
        ) {

            $sellerNames .=
                ' & '
                . trim(
                    (string)$draft['seller_2']
                );
        }


        $pdf->SetXY(
            53,
            35.1
        );

        $pdf->Cell(
            118,
            5,
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
            13,
            43.2
        );

        $pdf->Cell(
            102,
            5,
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
            17,
            51.2
        );

        $pdf->Cell(
            118,
            5,
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
                (string)(
                    $draft['list_price']
                    ?? ''
                )
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
            95,
            59.3
        );

        $pdf->Cell(
            43,
            5,
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
            (string)(
                $draft['start_date']
                ?? ''
            );

        if ($startDate !== '') {

            $timestamp =
                strtotime(
                    $startDate
                );

            if ($timestamp !== false) {

                $startDate =
                    date(
                        'm/d/Y',
                        $timestamp
                    );
            }
        }


        $pdf->SetXY(
            68,
            67.3
        );

        $pdf->Cell(
            38,
            5,
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
                strtotime(
                    $expirationDate
                );

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
            75.2
        );

        $pdf->Cell(
            42,
            5,
            $expirationDate,
            0,
            0
        );


        /*
        |--------------------------------------------------------------------------
        | SERVICE FEE
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
                109.4
            );

            $pdf->Cell(
                27,
                5,
                $serviceFeeValue,
                0,
                0
            );

        } else {

            $pdf->SetXY(
                57,
                117.1
            );

            $pdf->Cell(
                42,
                5,
                '$' . $serviceFeeValue,
                0,
                0
            );
        }


        /*
        |--------------------------------------------------------------------------
        | BUYER BROKER AUTHORIZATION
        |--------------------------------------------------------------------------
        */

        $buyerAuthorized =
            ($draft['buyer_broker_authorized'] ?? 'yes')
            === 'yes';


        if ($buyerAuthorized) {

            $pdf->SetXY(
                44.5,
                183.1
            );

            $pdf->SetFont(
                'Helvetica',
                'B',
                11
            );

            $pdf->Cell(
                5,
                5,
                'X',
                0,
                0
            );

        } else {

            $pdf->SetXY(
                57,
                183.1
            );

            $pdf->SetFont(
                'Helvetica',
                'B',
                11
            );

            $pdf->Cell(
                5,
                5,
                'X',
                0,
                0
            );
        }


        /*
        |--------------------------------------------------------------------------
        | BUYER BROKER COMPENSATION
        |--------------------------------------------------------------------------
        */

        if ($buyerAuthorized) {

            $pdf->SetFont(
                'Helvetica',
                '',
                9
            );


            $buyerFeeValue =
                trim(
                    (string)(
                        $draft['buyer_broker_fee_value']
                        ?? ''
                    )
                );


            if (
                ($draft['buyer_broker_fee_type'] ?? '')
                === 'percent'
            ) {

                $pdf->SetXY(
                    143,
                    197.0
                );

                $pdf->Cell(
                    18,
                    5,
                    $buyerFeeValue,
                    0,
                    0
                );

            } else {

                $pdf->SetXY(
                    30,
                    204.8
                );

                $pdf->Cell(
                    30,
                    5,
                    '$' . $buyerFeeValue,
                    0,
                    0
                );
            }
        }
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