<?php
// /auth/login.php — email + password login
declare(strict_types=1);
session_start();

// Always regenerate on login to prevent session fixation
session_regenerate_id(true);

$idxPath = __DIR__ . '/../data/user_index.json';
$usrPath = __DIR__ . '/../data/users.json';

$email = strtolower(trim($_POST['email'] ?? ''));
$pass  = $_POST['password'] ?? '';

if ($email === '' || $pass === '') {
  http_response_code(400);
  echo 'Missing email or password';
  exit;
}

// Load stores (robust against missing/empty files)
$index = json_decode(@file_get_contents($idxPath) ?: '[]', true) ?: [];
$users = json_decode(@file_get_contents($usrPath) ?: '[]', true) ?: [];

// Email -> user id
$uid = $index['email:' . $email] ?? null;
if (!$uid || empty($users[$uid])) {
  http_response_code(403);
  echo 'Invalid credentials';
  exit;
}

$user = $users[$uid] ?? [];

// Verify password
$hash = $user['password_hash'] ?? null;
if (!$hash || !password_verify($pass, $hash)) {
  http_response_code(403);
  echo 'Invalid credentials';
  exit;
}

// Success: set session
$_SESSION['user_id'] = $uid;

// Clear any device PIN cookie for web (keep flags secure/httponly)
setcookie('nemi_device', '', time() - 3600, '/', '', true, true);

// Decide landing by cases (optional)
$assignPath = __DIR__ . '/../data/cases/user_cases.json';
$assign     = json_decode(@file_get_contents($assignPath) ?: '[]', true) ?: [];
$userCases  = $assign[$uid] ?? [];

// If user has at least one case, send to client timeline for that case
if (!empty($userCases)) {
  $caseId = $userCases[0]; // or choose by role/most-recent
  header('Location: /app/timeline.php?case=' . rawurlencode($caseId));
  exit;
}

// Fallback when no cases yet
header('Location: /app/timeline.php');
exit;
