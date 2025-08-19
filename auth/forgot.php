<?php
session_start();
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/send_helpers.php';

$idxPath = __DIR__.'/../data/user_index.json';
$usrPath = __DIR__.'/../data/users.json';
$cfg     = require __DIR__ . '/../config/.env.php';

$email = strtolower(trim($_POST['email'] ?? ''));
$index = file_exists($idxPath)? json_decode(file_get_contents($idxPath), true): [];
$users = file_exists($usrPath)? json_decode(file_get_contents($usrPath), true): [];

$uid = $index['email:'.$email] ?? null;
if ($uid && isset($users[$uid])) {
  $token = bin2hex(random_bytes(16));
  $users[$uid]['reset'] = ['token'=>$token,'expires_at'=>time()+3600];
  file_put_contents($usrPath, json_encode($users, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));

  $link = rtrim($cfg['APP_URL'],'/')."/auth/reset.html?token=$token&uid=$uid";
  $html = "<p>Reset your Nemi password:</p><p><a href=\"$link\">$link</a></p>";
  send_email_otp($email, "Reset link: $link"); // reuse simple mailer (subject/body will be the link)
}
echo "If that email exists, we sent a link.";
