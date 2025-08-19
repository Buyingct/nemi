<?php
session_start();
require __DIR__ . '/send_helpers.php';

$cfg   = require __DIR__ . '/../config/.env.php';
$idx   = __DIR__ . '/../data/user_index.json';
$usr   = __DIR__ . '/../data/users.json';
$email = strtolower(trim($_POST['email'] ?? ''));

if (!$email) { echo "If that email exists, we sent a link."; exit; }

$index = file_exists($idx)? json_decode(file_get_contents($idx), true): [];
$users = file_exists($usr)? json_decode(file_get_contents($usr), true): [];

$uid = $index['email:'.$email] ?? null;
if ($uid && isset($users[$uid])) {
  $token = bin2hex(random_bytes(16));
  $users[$uid]['reset'] = ['token'=>$token,'expires_at'=>time()+3600];
  file_put_contents($usr, json_encode($users, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));

  $base = rtrim($cfg['APP_URL'] ?? '', '/');
  $link = $base . "/auth/reset.html?token=$token&uid=$uid";

  $html = "<p>Click the link below to reset your Nemi password. This link expires in 60 minutes.</p>"
        . "<p><a href=\"$link\">Reset your password</a></p>"
        . "<p>If you didn't request this, you can ignore this email.</p>";

  send_email($email, 'Reset your Nemi password', $html);
}
echo "If that email exists, we sent a link.";
