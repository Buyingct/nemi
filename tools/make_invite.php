<?php
// Usage in browser or CLI: /tools/make_invite.php?client=12345
$clientId = $_GET['client'] ?? $argv[1] ?? '';
if (!$clientId) { echo "Provide client id\n"; exit; }

$idxPath = __DIR__ . '/../data/clients.json';
$index   = json_decode(file_get_contents($idxPath), true);
if (!isset($index[$clientId])) { echo "Client not found\n"; exit; }

$token = bin2hex(random_bytes(16));
$index[$clientId]['pin_token'] = $token;
file_put_contents($idxPath, json_encode($index, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));

$url = "https://nemi.buyingct.com/auth/create-pin.php?client={$clientId}&token={$token}";
echo $url . PHP_EOL;   // share this link with the client (SMS/email)
