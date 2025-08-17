<?php
session_start();
header('Content-Type: application/json');
if (empty($_SESSION['user_id']) || empty($_SESSION['device_id'])) { http_response_code(401); echo '{"ok":false}'; exit; }

$pin = $_POST['pin'] ?? '';
$usrPath = __DIR__ . '/../data/users.json';
$users = json_decode(file_get_contents($usrPath), true);
$uid = $_SESSION['user_id']; $did = $_SESSION['device_id'];
$dev = $users[$uid]['devices'][$did] ?? null;

if (!$dev) { echo '{"ok":false}'; exit; }

$now = time();
if (($dev['locked_until'] ?? 0) > $now) {
  echo json_encode(['ok'=>false,'locked'=>true,'retry_at'=>$dev['locked_until']]); exit;
}

$ok = ($dev['pin_hash'] ?? null) && password_verify($pin, $dev['pin_hash']);

if ($ok) {
  $users[$uid]['devices'][$did]['fail_count'] = 0;
  $users[$uid]['devices'][$did]['locked_until'] = 0;
  file_put_contents($usrPath, json_encode($users, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
  echo '{"ok":true}'; exit;
} else {
  $fc = ($dev['fail_count'] ?? 0) + 1;
  $users[$uid]['devices'][$did]['fail_count'] = $fc;
  if ($fc >= 5) {
    $users[$uid]['devices'][$did]['locked_until'] = $now + 600; // 10 minutes
    // also trigger OTP to email/phone for fallback unlock:
    $code = str_pad((string)random_int(0,999999), 6, "0", STR_PAD_LEFT);
    $users[$uid]['otp'] = ['code'=>$code, 'expires_at'=>$now+600, 'for_device'=>$did];
    // TODO send $code via email/SMS
    error_log("Nemi fallback OTP for $uid: $code");
  }
  file_put_contents($usrPath, json_encode($users, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
  echo json_encode(['ok'=>false, 'fail_count'=>$fc]); exit;
}
