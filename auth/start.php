<?php
// /auth/start.php  (email-only OTP)
session_start();

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/send_helpers.php';

function bad($m){ http_response_code(400); echo $m; exit; }

$email = strtolower(trim($_POST['email'] ?? ''));
if (!$email) bad("Please enter your email.");

$idxPath = __DIR__ . '/../data/user_index.json';
$usrPath = __DIR__ . '/../data/users.json';

$index = file_exists($idxPath) ? json_decode(file_get_contents($idxPath), true) : [];
$users = file_exists($usrPath) ? json_decode(file_get_contents($usrPath), true) : [];

$userId = $index['email:'.$email] ?? null;
if (!$userId) bad("We couldn't find an account. Ask your agent to invite you.");

$user = $users[$userId] ?? null;
if (!$user || empty($user['email'])) bad("Account data not found.");

// make code
$code = str_pad((string)random_int(0,999999), 6, "0", STR_PAD_LEFT);
$user['otp'] = ['code'=>$code, 'expires_at'=>time()+600, 'for_device'=>null];

// send email
if (!send_email_otp($user['email'], $code)) bad("We couldn't send your code. Try again.");

$users[$userId] = $user;
file_put_contents($usrPath, json_encode($users, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));



$_SESSION['otp_user'] = $userId;
header('Location: verify_otp.html');
exit;
