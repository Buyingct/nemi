<?php
session_start();
$usrPath = __DIR__.'/../data/users.json';
$users = file_exists($usrPath)? json_decode(file_get_contents($usrPath), true): [];

$uid   = $_POST['uid'] ?? '';
$token = $_POST['token'] ?? '';
$pass  = $_POST['password'] ?? '';

if (empty($users[$uid]) || strlen($pass) < 8) { http_response_code(400); echo "Bad request"; exit; }

$reset = $users[$uid]['reset'] ?? ['token'=>null,'expires_at'=>0];
if (!$reset['token'] || $reset['token'] !== $token || $reset['expires_at'] < time()) {
  http_response_code(403); echo "Invalid or expired token"; exit;
}

$users[$uid]['password_hash'] = password_hash($pass, PASSWORD_DEFAULT);
$users[$uid]['reset'] = ['token'=>null,'expires_at'=>0];
file_put_contents($usrPath, json_encode($users, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));

$_SESSION['user_id'] = $uid;
header('Location: /app/timeline.php');
exit;
