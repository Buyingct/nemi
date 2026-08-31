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
    $_SESSION[$draftKey] ?? null;

if (!is_array($draft) || empty($draft)) {
    http_response_code(400);
    exit('No prepared agreement was found.');
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