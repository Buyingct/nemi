<?php
declare(strict_types=1);
session_start();
header('Content-Type: application/json');

if (empty($_SESSION['user_id'])) { http_response_code(401); echo json_encode(['ok'=>false,'error'=>'Unauthorized']); exit; }

$step = $_POST['step'] ?? '';
$done = ($_POST['done'] ?? '0') === '1';
if ($step === '') { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'Missing step']); exit; }

// For now, store in a single default client file (you can switch to per-case later)
$dataPath = __DIR__ . '/../data/clients/default.json';
$payload  = file_exists($dataPath) ? (json_decode(file_get_contents($dataPath), true) ?: []) : ['states'=>[], 'notes'=>[]];

$payload['states'] = $payload['states'] ?? [];
$payload['states'][$step] = $payload['states'][$step] ?? [];
$payload['states'][$step]['done'] = $done;
$payload['states'][$step]['ts']   = time();

@mkdir(dirname($dataPath), 0770, true);
$ok = (bool)file_put_contents($dataPath, json_encode($payload, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
if (!$ok) { http_response_code(500); echo json_encode(['ok'=>false,'error'=>'Write failed']); exit; }

@chown($dataPath,'www-data'); @chgrp($dataPath,'www-data'); @chmod($dataPath,0660);
echo json_encode(['ok'=>true]);
