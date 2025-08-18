<?php
session_start();
require_once __DIR__ . '/send_helpers.php';
if (empty($_SESSION['otp_user'])) { header('Location: start.html'); exit; }

$usrPath = __DIR__ . '/../data/users.json';
$users = json_decode(file_get_contents($usrPath), true);
$uid = $_SESSION['otp_user'];

$user = $users[$uid] ?? null;
if (!$user || empty($user['email'])) { header('Location: start.html'); exit; }

$code = str_pad((string)random_int(0,999999), 6, "0", STR_PAD_LEFT);
$user['otp'] = ['code'=>$code, 'expires_at'=>time()+600, 'for_device'=>$user['otp']['for_device'] ?? null];

if (send_email_otp($user['email'], $code)) {
  $users[$uid] = $user;
  file_put_contents($usrPath, json_encode($users, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
  header('Location: verify_otp.html?sent=1');
} else {
  header('Location: verify_otp.html?sent=0');
}
exit;
