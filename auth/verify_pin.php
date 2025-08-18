<?php
// /auth/verify_pin.php  (hashed + rate limit)
session_start();
header('Content-Type: application/json');

$usrPath = __DIR__ . '/../data/users.json';
$users = file_exists($usrPath) ? json_decode(file_get_contents($usrPath), true) : [];

$resp = ['ok'=>false];

if (empty($_COOKIE['nemi_device'])) { echo json_encode(['ok'=>false,'msg'=>'No device cookie']); exit; }
list($uid, $did) = explode(':', $_COOKIE['nemi_device'], 2);

if (!isset($users[$uid]) || empty($users[$uid]['devices'][$did])) {
  echo json_encode(['ok'=>false,'msg'=>'Device not registered']); exit;
}

$dev = $users[$uid]['devices'][$did];
$now = time();

if (!empty($dev['locked_until']) && $dev['locked_until'] > $now) {
  echo json_encode(['ok'=>false,'locked'=>true,'retry_at'=>$dev['locked_until']]); exit;
}

$pin = $_POST['pin'] ?? '';

$ok = !empty($dev['pin_hash']) && password_verify($pin, $dev['pin_hash']);

if ($ok) {
  $users[$uid]['devices'][$did]['fail_count'] = 0;
  $users[$uid]['devices'][$did]['locked_until'] = 0;
  file_put_contents($usrPath, json_encode($users, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
  $_SESSION['user_id'] = $uid; // unlocked for this session
  echo json_encode(['ok'=>true]); exit;
}

$fc = intval($dev['fail_count'] ?? 0) + 1;
$users[$uid]['devices'][$did]['fail_count'] = $fc;
if ($fc >= 5) {
  $users[$uid]['devices'][$did]['locked_until'] = $now + 600; // 10 minutes
}
file_put_contents($usrPath, json_encode($users, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));

echo json_encode(['ok'=>false,'fail_count'=>$fc]); // your overlay switches to OTP after 5
