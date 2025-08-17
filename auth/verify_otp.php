<?php
session_start();
if (empty($_SESSION['otp_user'])) { header('Location: start.html'); exit; }
$code = trim($_POST['code'] ?? '');

$usrPath = __DIR__ . '/../data/users.json';
$users = json_decode(file_get_contents($usrPath), true);
$uid = $_SESSION['otp_user'];
$otp = $users[$uid]['otp'] ?? null;

if (!$otp || time() > ($otp['expires_at'] ?? 0) || !hash_equals($otp['code'], $code)) {
  header('Location: verify_otp.html?err=1'); exit;
}

// device handling
if (empty($_COOKIE['nemi_device'])) {
  $deviceId = 'd_'.bin2hex(random_bytes(6));
  $users[$uid]['devices'][$deviceId] = [
    'name' => $_SERVER['HTTP_USER_AGENT'] ?? 'Device',
    'pin_hash' => null,
    'created_at' => time(),
    'locked_until' => 0,
    'fail_count' => 0
  ];
  setcookie('nemi_device', $deviceId, time()+60*60*24*365, '/', '', true, true);
} else {
  $deviceId = $_COOKIE['nemi_device'];
  if (!isset($users[$uid]['devices'][$deviceId])) {
    // stale cookie; register anyway
    $users[$uid]['devices'][$deviceId] = [
      'name' => $_SERVER['HTTP_USER_AGENT'] ?? 'Device',
      'pin_hash' => null,
      'created_at' => time(),
      'locked_until' => 0,
      'fail_count' => 0
    ];
  }
}

// clear OTP
$users[$uid]['otp'] = ['code'=>null,'expires_at'=>0,'for_device'=>$deviceId];
file_put_contents($usrPath, json_encode($users, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));

$_SESSION['user_id'] = $uid;
$_SESSION['device_id'] = $deviceId;

// If device has no PIN → ask to create one. Else go to timeline with PIN overlay.
if (empty($users[$uid]['devices'][$deviceId]['pin_hash'])) {
  header('Location: create-pin.php');
} else {
  header('Location: ../app/timeline.php');
}
exit;
