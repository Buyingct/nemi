<?php // TEMP TOOL — delete after use
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  header('Content-Type: text/plain; charset=utf-8');
  echo password_hash($_POST['plain'] ?? '', PASSWORD_DEFAULT);
  exit;
}
?>
<!doctype html><meta charset="utf-8">
<form method="post" style="font:14px system-ui">
  <label>New password<br><input name="plain" style="width:420px"></label><br><br>
  <button>Make hash</button>
</form>
