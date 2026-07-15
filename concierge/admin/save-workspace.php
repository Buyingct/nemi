<?php

declare(strict_types=1);

require_once __DIR__ . '/../database/connect.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: create-workspace.php');
    exit;
}

$name = trim((string) ($_POST['name'] ?? ''));
$address = trim((string) ($_POST['address'] ?? ''));
$city = trim((string) ($_POST['city'] ?? ''));
$state = strtoupper(trim((string) ($_POST['state'] ?? 'CT')));
$postalCode = trim((string) ($_POST['postal_code'] ?? ''));

if ($name === '') {
    header(
        'Location: create-workspace.php?error='
        . urlencode('Workspace name is required.')
    );
    exit;
}

if (mb_strlen($name) > 160) {
    header(
        'Location: create-workspace.php?error='
        . urlencode('Workspace name is too long.')
    );
    exit;
}

if ($state === '') {
    $state = 'CT';
}

if (!preg_match('/^[A-Z]{2}$/', $state)) {
    header(
        'Location: create-workspace.php?error='
        . urlencode('State must be a two-letter abbreviation.')
    );
    exit;
}

try {
    $database = conciergeDatabase();

    do {
        $publicId = bin2hex(random_bytes(8));

        $check = $database->prepare(
            'SELECT COUNT(*) FROM workspaces WHERE public_id = :public_id'
        );

        $check->execute([
            ':public_id' => $publicId,
        ]);

        $alreadyExists = (int) $check->fetchColumn() > 0;
    } while ($alreadyExists);

    $statement = $database->prepare(
        '
        INSERT INTO workspaces (
            public_id,
            name,
            address,
            city,
            state,
            postal_code,
            status
        ) VALUES (
            :public_id,
            :name,
            :address,
            :city,
            :state,
            :postal_code,
            :status
        )
        '
    );

    $statement->execute([
        ':public_id' => $publicId,
        ':name' => $name,
        ':address' => $address !== '' ? $address : null,
        ':city' => $city !== '' ? $city : null,
        ':state' => $state,
        ':postal_code' => $postalCode !== '' ? $postalCode : null,
        ':status' => 'active',
    ]);

    header(
        'Location: index.php?created=1&workspace='
        . urlencode($publicId)
    );
    exit;
} catch (Throwable $exception) {
    error_log(
        'Workspace creation failed: '
        . $exception->getMessage()
    );

    header(
        'Location: create-workspace.php?error='
        . urlencode(
            'The workspace could not be created. Please try again.'
        )
    );
    exit;
}
