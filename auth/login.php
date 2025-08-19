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



// go to timeline
header('Location: /app/timeline.php');
exit;
