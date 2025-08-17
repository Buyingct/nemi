<?php
session_start();
function respond($msg){ http_response_code(400); echo $msg; exit; }

$email = trim($_POST['email'] ?? '');
$phone = trim($_POST['phone'] ?? '');

$idxPath = __DIR__ . '/../data/user_index.json';
$usrPath = __DIR__ . '/../data/users.json';

$index = file_exists($idxPath) ? json_decode(file_get_contents($idxPath), true) : [];
$users = file_exists($usrPath) ? json_decode(file_get_contents($usrPath), true) : [];

$userId = '';
if ($email && isset($index['email:'.strtolower($email)])) $userId = $index['email:'.strtolower($email)];
if (!$userId && $phone && isset($index['phone:'.$phone])) $userId = $index['phone:'.$phone];
if (!$userId) respond("We couldn't find an account. Ask your agent to invite you.");

$code = str_pad((string)random_int(0,999999), 6, "0", STR_PAD_LEFT);
$users[$userId]['otp'] = ['code'=>$code, 'expires_at'=>time()+600, 'for_device'=>null];
file_put_contents($usrPath, json_encode($users, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));

// TODO: send $code by email/SMS (for now, log it)
error_log("Nemi OTP for $userId ($email $phone): $code");

$_SESSION['otp_user'] = $userId;
header('Location: verify_otp.html');
exit;
