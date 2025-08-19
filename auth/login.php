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

// ensure device cookie exists (for PIN quick-unlock)
if (empty($_COOKIE['nemi_device'])) {
  $did = 'd_' . bin2hex(random_bytes(6));
  setcookie('nemi_device', $uid . ':' . $did, time() + 60*60*24*365, '/', '', true, true);
  if (!isset($users[$uid]['devices'])) $users[$uid]['devices'] = [];
  if (!isset($users[$uid]['devices'][$did])) {
    $users[$uid]['devices'][$did] = [
      'name' => $_SERVER['HTTP_USER_AGENT'] ?? 'Device',
      'pin_hash' => null, 'fail_count'=>0, 'locked_until'=>0, 'created_at'=>time()
    ];
    file_put_contents($usrPath, json_encode($users, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
  }
}

// go to timeline
header('Location: /app/timeline.php');
exit;
