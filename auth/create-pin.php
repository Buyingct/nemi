<?php
// /auth/create-pin.php
session_start();
header('Content-Type: text/html; charset=utf-8');

$clientId = $_GET['client'] ?? $_POST['client'] ?? '';
$token    = $_GET['token']  ?? $_POST['token']  ?? '';

$idxPath = __DIR__ . '/../data/clients.json';
$index   = file_exists($idxPath) ? json_decode(file_get_contents($idxPath), true) : [];

$client = $index[$clientId] ?? null;
if (!$client || empty($token) || !hash_equals($client['pin_token'] ?? '', $token)) {
  http_response_code(400);
  echo "<h1>Invalid or expired link.</h1>";
  exit;
}

// Handle POST → save PIN
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $pin1 = $_POST['pin1'] ?? '';
  $pin2 = $_POST['pin2'] ?? '';
  if (!preg_match('/^\d{4}$/', $pin1)) { $err = "PIN must be 4 digits."; }
  elseif ($pin1 !== $pin2) { $err = "PINs do not match."; }
  else {
    // Hash + save; clear token so link can’t be reused
    $index[$clientId]['pin_hash']  = password_hash($pin1, PASSWORD_DEFAULT);
    $index[$clientId]['pin_token'] = null;
    file_put_contents($idxPath, json_encode($index, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
    header('Location: /auth/signin.html?set=1'); // success
    exit;
  }
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Create your Nemi PIN</title>
<link rel="stylesheet" href="../css/pin.css">
</head>
<body>
<main class="pin-wrap" style="max-width:420px;margin:8vh auto;padding:16px">
  <h1 class="brand">Create your 4-digit PIN</h1>
  <?php if (!empty($err)) echo "<p style='color:#b91c1c'><b>$err</b></p>"; ?>
  <form method="post">
    <input type="hidden" name="client" value="<?php echo htmlspecialchars($clientId); ?>">
    <input type="hidden" name="token"  value="<?php echo htmlspecialchars($token); ?>">

    <label class="hint">Choose a 4-digit PIN</label>
    <div class="pin-row">
      <input name="pin1" inputmode="numeric" pattern="\d{4}" maxlength="4" placeholder="1234" required>
    </div>
    <label class="hint">Confirm PIN</label>
    <div class="pin-row">
      <input name="pin2" inputmode="numeric" pattern="\d{4}" maxlength="4" placeholder="1234" required>
    </div>
    <button class="btn" type="submit">Save PIN</button>
  </form>
</main>
</body>
</html>
