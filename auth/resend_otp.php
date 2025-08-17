<?php
// /auth/resend_otp.php
session_start();
if (empty($_SESSION['otp_user'])) { header('Location: start.html'); exit; }

$usrPath = __DIR__ . '/../data/users.json';
$users = file_exists($usrPath) ? json_decode(file_get_contents($usrPath), true) : [];
$uid = $_SESSION['otp_user'];

// generate a new code
$code = str_pad((string)random_int(0,999999), 6, "0", STR_PAD_LEFT);
$users[$uid]['otp'] = [
  'code'       => $code,
  'expires_at' => time() + 600,  // 10 minutes
  'for_device' => $users[$uid]['otp']['for_device'] ?? null
];

// save
file_put_contents($usrPath, json_encode($users, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

// TODO: send $code via email/SMS provider; for now log it
error_log("Nemi RESEND OTP for $uid: $code");

header('Location: verify_otp.html?sent=1');
exit;
