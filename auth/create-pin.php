<?php
session_start();
if (empty($_SESSION['user_id']) || empty($_SESSION['device_id'])) { header('Location: start.html'); exit; }

$usrPath = __DIR__ . '/../data/users.json';
$users = json_decode(file_get_contents($usrPath), true);
$uid = $_SESSION['user_id']; $did = $_SESSION['device_id'];
$err = '';

if ($_SERVER['REQUEST_METHOD']==='POST') {
  $p1 = $_POST['pin1'] ?? ''; $p2 = $_POST['pin2'] ?? '';
  if (!preg_match('/^\d{4}$/', $p1)) $err="PIN must be 4 digits.";
  elseif ($p1 !== $p2) $err="PINs do not match.";
  else {
    $users[$uid]['devices'][$did]['pin_hash'] = password_hash($p1, PASSWORD_DEFAULT);
    $users[$uid]['devices'][$did]['fail_count'] = 0;
    $users[$uid]['devices'][$did]['locked_until'] = 0;
    file_put_contents($usrPath, json_encode($users, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
    header('Location: ../app/timeline.php'); exit;
  }
}
?>
<!doctype html><meta charset="utf-8">
<title>Create device PIN</title>
<link rel="stylesheet" href="../css/pin.css">
<main class="pin-wrap" style="max-width:420px;margin:8vh auto;padding:16px">
  <h1 class="brand">Create a 4-digit PIN</h1>
  <?php if($err) echo "<p style='color:#b91c1c'><b>$err</b></p>"; ?>
  <form method="post" class="grid gap-3">
    <input name="pin1" class="field" inputmode="numeric" pattern="\d{4}" maxlength="4" placeholder="1234" required>
    <input name="pin2" class="field" inputmode="numeric" pattern="\d{4}" maxlength="4" placeholder="1234" required>
    <button class="btn" type="submit">Save PIN</button>
  </form>
</main>
<style>.field{padding:12px;border:1px solid #e5e7eb;border-radius:12px;width:100%}</style>
