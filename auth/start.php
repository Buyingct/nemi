<?php
declare(strict_types=1);
session_start();

function j(string $p): array {
    return file_exists($p) ? (json_decode(file_get_contents($p), true) ?: []) : [];
}

if (empty($_SESSION['uid'])) {
    header('Location: /');
    exit;
}

$uid  = (string)($_SESSION['uid'] ?? '');
$role = strtolower((string)($_SESSION['role'] ?? 'buyer'));

$cases = j(__DIR__ . '/../data/cases/user_cases.json');
$mine  = $uid !== '' ? ($cases[$uid] ?? []) : [];

if (is_array($mine) && !empty($mine)) {
    header('Location: /app/client/timeline.php?case=' . urlencode((string)$mine[0]));
    exit;
}

switch ($role) {
    case 'admin':
        header('Location: /admin/users.php');
        break;

    case 'realtor':
        header('Location: /app/realtor_portal.php');
        break;

    case 'buyer':
    case 'seller':
        header('Location: /app/timeline.php');
        break;

    case 'lender':
        header('Location: /tools/dashboard/lender.html');
        break;

    case 'attorney':
        header('Location: /tools/dashboard/attorney.html');
        break;

    default:
        header('Location: /tools/dashboard/index.html');
        break;
}
exit;
