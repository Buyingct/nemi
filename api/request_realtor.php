<?php
declare(strict_types=1);
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['uid'])) { http_response_code(401); echo json_encode(['ok'=>false,'err'=>'not_signed_in']); exit; }

$DATA = __DIR__ . '/../../data';
$REQUESTS = $DATA . '/cases/realtor_requests.json';

function jread($p){ return file_exists($p)?(json_decode(file_get_contents($p),true)?:[]):[]; }
function jwrite($p,$a){ @mkdir(dirname($p),0770,true); $ok=file_put_contents($p,json_encode($a,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)); @chmod($p,0660); return (bool)$ok; }

$caseId = trim($_POST['case'] ?? '');
$email  = strtolower(trim($_POST['email'] ?? ''));
$note   = trim($_POST['note'] ?? '');

if ($caseId==='' || $email==='' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
  http_response_code(400); echo json_encode(['ok'=>false,'err'=>'bad_input']); exit;
}

$reqs = jread($REQUESTS);
$reqs[] = ['case'=>$caseId,'email'=>$email,'note'=>$note,'by'=>$_SESSION['uid'],'at'=>date('c'),'status'=>'pending'];
jwrite($REQUESTS,$reqs);

echo json_encode(['ok'=>true]);
