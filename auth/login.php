<?php
session_start();
$entered = $_POST['digit1'].$_POST['digit2'].$_POST['digit3'].$_POST['digit4'];
$hashes = json_decode(file_get_contents(__DIR__ . '/../data/clients.json'), true);
foreach ($hashes as $id => $info) {
  if (password_verify($entered, $info['pin_hash'])) {
    $_SESSION['client_id'] = $id;
    header('Location: ../app/timeline.php');
    exit;
  }
}
echo "Invalid PIN";
?>