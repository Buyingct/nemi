<?php
// /auth/login.php — email + password login
declare(strict_types=1);
session_start();

$idxPath = __DIR__ . '/../data/user_index.json';
$usrPath = __DIR__ . '/../data/users.json';

$email = strtolower(trim($_POST['email'] ?? ''));
$pass  = $_POST['password'] ?? '';

if ($email === '' || $pass === '') {
  http_response_code(400);
  echo "Missing email or password";
  exit;
}

// load stores
$index = file_exists($idxPath) ? json_decode(file_get_contents($idxPath), true) : [];
$users = file_exists($usrPath) ? json_decode(file_get_contents($usrPath), true) : [];

// map email -> user id
$uid = $index['email:' . $email] ?? null;
if (!$uid || empty($users[$uid])) {
  http_response_code(403);
  echo "Invalid credentials";
  exit;
}

$user = $users[$uid];

// verify password
$hash = $user['password_hash'] ?? null;
if (!$hash || !password_verify($pass, $hash)) {
  http_response_code(403);
  echo "Invalid credentials";
  exit;
}

// success: set session
$_SESSION['user_id'] = $uid;

// ensure device PIN cookie is cleared on web
setcookie('nemi_device', '', time() - 3600, '/', '', true, true);



// decide landing by cases
$assign = @json_decode(@file_get_contents(__DIR__.'/../data/cases/user_cases.json'), true) ?: [];
$userCases = $assign[$uid] ?? [];

if ($userCases) {
  // If the user is a client on any case, send to that case’s timeline
  foreach ($userCases as $caseId) {
    // If you want to check role precisely, you can also read case_index.json here
    header('Location: /app/client/timeline.php?case=' . urlencode($caseId));
    exit;
  }
}

// fallback (no cases yet)
header('Location: /app/empty.php'); // create a simple "No cases yet" page if you like
exit;
