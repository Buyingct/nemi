<?php
// /auth/create-pin.php
session_start();

$usrPath = __DIR__ . '/../data/users.json';
$users = file_exists($usrPath) ? json_decode(file_get_contents($usrPath), true) : [];

function bad($m){ http_response_code(400); echo $m; exit; }

// We expect a remembered device or an OTP-verified session that will set it now.
$uid = $_SESSION['user_id'] ?? null;
$did = null;

// If device cookie already exists, parse it.
if (!empty($_COOKIE['nemi_device'])) {
  $parts = explode(':', $_COOKIE['nemi_device'], 2);
  if (count($parts) === 2) { $uid = $parts[0]; $did = $parts[1]; }
}

// If no device id yet but user session exists (fresh from OTP), create a device id.
if (!$did && $uid) {
  $did = 'd_'.bin2hex(random_bytes(6));
  // keep cookie for one year; Secure+HttpOnly
  setcookie('nemi_device', $uid . ':' . $did, time()+60*60*24*365, '/', '', true, true);
}

// Validate user/device existence in store; create device bucket if missing.
if (!$uid || !isset($users[$uid])) bad('No user session/device.');
if (!isset($users[$uid]['devices'][$did])) {
  $users[$uid]['devices'][$did] = [
    'name' => $_SERVER['HTTP_USER_AGENT'] ?? 'Device',
    'pin_hash' => null,
    'fail_count' => 0,
    'locked_until' => 0,
    'created_at' => time()
  ];
  file_put_contents($usrPath, json_encode($users, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
}

$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $p1 = $_POST['pin1'] ?? ''; $p2 = $_POST['pin2'] ?? '';
  if (!preg_match('/^\d{4}$/', $p1))        { $err = 'PIN must be 4 digits.'; }
  elseif ($p1 !== $p2)                       { $err = 'PINs do not match.'; }
  else {
    $users[$uid]['devices'][$did]['pin_hash'] = password_hash($p1, PASSWORD_DEFAULT);
    $users[$uid]['devices'][$did]['fail_count'] = 0;
    $users[$uid]['devices'][$did]['locked_until'] = 0;
    file_put_contents($usrPath, json_encode($users, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
    // Consider them unlocked now
    $_SESSION['user_id'] = $uid;
    header('Location: /app/timeline.php');
    exit;
  }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Create your PIN • Nemi</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-50 min-h-screen flex items-center justify-center">
  <div class="w-full max-w-md bg-white rounded-2xl shadow p-8">
    <h1 class="text-2xl font-bold text-center">Create a 4-digit PIN</h1>
    <?php if ($err): ?><p class="mt-3 text-red-600 font-semibold"><?=htmlspecialchars($err)?></p><?php endif; ?>
    <form method="post" class="mt-6 space-y-4">
      <input name="pin1" inputmode="numeric" pattern="\d{4}" maxlength="4"
             class="w-full rounded-xl border-slate-300 shadow-sm px-3 py-2 text-center text-2xl tracking-widest"
             placeholder="••••" required>
      <input name="pin2" inputmode="numeric" pattern="\d{4}" maxlength="4"
             class="w-full rounded-xl border-slate-300 shadow-sm px-3 py-2 text-center text-2xl tracking-widest"
             placeholder="••••" required>
      <button class="w-full rounded-xl bg-slate-900 text-white font-semibold py-2">Save PIN</button>
    </form>
    <p class="text-xs text-slate-500 text-center mt-4">This PIN unlocks Nemi on this device.</p>
  </div>
</body>
</html>
