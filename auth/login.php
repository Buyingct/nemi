<?php
session_start();
function back(){ header('Location: signin.html?err=1'); exit; }

$clientId = $_POST['client_id'] ?? '';            // include a hidden field or choose client by email/phone in future
$pin      = $_POST['pin'] ?? ($_POST['digit1'].$_POST['digit2'].$_POST['digit3'].$_POST['digit4'] ?? '');

if (!$clientId || !preg_match('/^\d{4}$/', $pin)) back();

$idxPath = __DIR__ . '/../data/clients.json';
$index   = json_decode(file_get_contents($idxPath), true);
$client  = $index[$clientId] ?? null;
if (!$client || empty($client['pin_hash'])) back();

if (!password_verify($pin, $client['pin_hash'])) back();

$_SESSION['client_id']   = $clientId;
$_SESSION['client_name'] = $client['name'] ?? 'Client';
header('Location: ../app/timeline.php');
exit;
