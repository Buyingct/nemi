<?php
declare(strict_types=1);
session_start();

// tiny JSON helpers
function j($p){ return file_exists($p) ? (json_decode(file_get_contents($p), true) ?: []) : []; }

// Not logged in? → go to login form
if (empty($_SESSION['email'])) {
  header('Location: /auth/login_form.php'); exit;
}

// Logged in: route by cases first, then role
$uid   = $_SESSION['uid']   ?? null;
$role  = strtolower((string)($_SESSION['role'] ?? 'buyer'));

$cases = j(__DIR__ . '/../data/cases/user_cases.json');
$mine  = $uid ? ($cases[$uid] ?? []) : [];

if (!empty($mine)) {
  header('Location: /app/client/timeline.php?case=' . urlencode($mine[0])); exit;
}

switch ($role) {
  case 'admin':   header('Location: /admin/users.php'); break;
  case 'realtor': header('Location: /app/realtor_portal.php'); break;
  case 'buyer':
  case 'seller':  header('Location: /app/timeline.php'); break;
  case 'lender':  header('Location: /tools/dashboard/lender.html'); break;
  case 'attorney':header('Location: /tools/dashboard/attorney.html'); break;
  default:        header('Location: /tools/dashboard/index.html'); break;
}
exit;
