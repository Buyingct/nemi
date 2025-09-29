<?php
declare(strict_types=1);
session_start();
header('Content-Type: application/json');

// Must be signed in
if (!isset($_SESSION['uid'])) {
  http_response_code(401); echo json_encode(['ok'=>false,'err'=>'not_signed_in']); exit;
}

$cfg = include __DIR__ . '/../config/.env.php';
require_once __DIR__ . '/../auth/send_helpers.php';

$DATA       = __DIR__ . '/../data';
$USERS      = $DATA . '/users.json';
$INDEX      = $DATA . '/user_index.json';
$CASES_DIR  = $DATA . '/cases';
$USER_CASES = $CASES_DIR . '/user_cases.json';
$CASE_INDEX = $CASES_DIR . '/case_index.json';

function jread($p){ return file_exists($p)?(json_decode(file_get_contents($p),true)?:[]):[]; }
function jwrite($p,$a){ @mkdir(dirname($p),0770,true); $ok=file_put_contents($p,json_encode($a,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)); @chmod($p,0660); return (bool)$ok; }
function uid(){ return 'u_'.bin2hex(random_bytes(6)); }
function randpass($n=12){ $s='ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789!@#$%'; $o=''; for($i=0;$i<$n;$i++) $o.=$s[random_int(0,strlen($s)-1)]; return $o; }

$caseId      = trim($_POST['case']  ?? '');
$email       = strtolower(trim($_POST['email'] ?? ''));
$roleIn      = strtolower(trim($_POST['role']  ?? ''));
$sendInvites = isset($_POST['send_invites']) ? (bool)$_POST['send_invites'] : true;

if ($caseId==='' || $email==='' || !filter_var($email, FILTER_VALIDATE_EMAIL) || $roleIn==='') {
  http_response_code(400); echo json_encode(['ok'=>false,'err'=>'bad_input']); exit;
}

$users = jread($USERS);
$index = jread($INDEX);
$caseFile = $CASES_DIR . "/{$caseId}.json";
if (!is_file($caseFile)) { http_response_code(404); echo json_encode(['ok'=>false,'err'=>'case_not_found']); exit; }
$case = jread($caseFile);
$caseType = $case['type'] ?? 'buyer';

// Actor
$actorUid   = (string)$_SESSION['uid'];
$actorRole  = strtolower((string)($_SESSION['role'] ?? 'buyer'));
$actorEmail = strtolower((string)($_SESSION['email'] ?? ''));

// Admin allowlist
$isAdmin = ($actorRole==='admin') || in_array($actorEmail, (array)($cfg['ADMINS'] ?? []), true);

// Must be on case unless admin
$actorOnCase = $isAdmin ? true : in_array($actorUid, array_merge(
  $case['team']['client']   ?? [],
  $case['team']['seller']   ?? [],
  $case['team']['realtor']  ?? [],
  $case['team']['lender']   ?? [],
  $case['team']['attorney'] ?? []
), true);
if (!$actorOnCase) { http_response_code(403); echo json_encode(['ok'=>false,'err'=>'not_on_case']); exit; }

// Permissions: admin-only realtor; realtor required before others
$ALLOW = [
  'buyer' => [
    'admin'    => ['*'],
    'realtor'  => ['lender','attorney'],
    'buyer'    => ['lender','attorney'], // aka client
    'client'   => ['lender','attorney'],
    'lender'   => [],
    'attorney' => [],
  ],
  'seller' => [
    'admin'    => ['*'],
    'realtor'  => ['lender','attorney'],
    'seller'   => ['lender','attorney'],
    'lender'   => [],
    'attorney' => [],
  ],
];
$policy = $ALLOW[$caseType] ?? $ALLOW['buyer'];
$can = $isAdmin ? ['*'] : ($policy[$actorRole] ?? []);
if (!in_array('*', $can, true) && !in_array($roleIn, $can, true)) {
  http_response_code(403); echo json_encode(['ok'=>false,'err'=>'not_allowed_for_role','who'=>$actorRole,'role'=>$roleIn]); exit;
}

$hasRealtor = !empty($case['team']['realtor']);
if (in_array($roleIn, ['lender','attorney'], true) && !$isAdmin && !$hasRealtor) {
  http_response_code(403); echo json_encode(['ok'=>false,'err'=>'realtor_required_first']); exit;
}
if ($roleIn === 'realtor' && !$isAdmin) {
  http_response_code(403); echo json_encode(['ok'=>false,'err'=>'admin_only_realtor']); exit;
}

// Map role to team key
$roleMap = [
  'buyer'=>'client','client'=>'client',
  'seller'=>'seller',
  'realtor'=>'realtor','lender'=>'lender','attorney'=>'attorney'
];
$teamKey = $roleMap[$roleIn] ?? null;
if (!$teamKey) { http_response_code(400); echo json_encode(['ok'=>false,'err'=>'bad_role']); exit; }

// Resolve/create user
$key = 'email:'.$email;
$newPwd = null;
if (!isset($index[$key])) {
  $newUid = uid();
  $newPwd = randpass();
  $users[$newUid] = [
    'email' => $email,
    'password_hash' => password_hash($newPwd, PASSWORD_DEFAULT),
    'role' => $roleIn
  ];
  $index[$key] = $newUid;
  jwrite($USERS,$users); jwrite($INDEX,$index);
} else {
  $newUid = $index[$key];
}

// Update team
$case['team'][$teamKey] = array_values(array_unique(array_merge($case['team'][$teamKey] ?? [], [$newUid])));
jwrite($caseFile,$case);

// Reverse index
$userCases = jread($USER_CASES);
$userCases[$newUid] = array_values(array_unique(array_merge($userCases[$newUid] ?? [], [$caseId])));
jwrite($USER_CASES, $userCases);

// Invite (only if new)
if ($sendInvites && $newPwd) {
  $app = rtrim($cfg['APP_URL'] ?? '', '/');
  $html = "<p>Your Nemi account was set up.</p>"
        . "<p><b>Email:</b> {$email}<br><b>Temp password:</b> {$newPwd}</p>"
        . "<p>Sign in: <a href=\"{$app}/auth/login_form.php\">{$app}/auth/login_form.php</a></p>";
  @send_email($email, 'Welcome to Nemi', $html);
}

echo json_encode(['ok'=>true,'case'=>$caseId,'added'=>['email'=>$email,'uid'=>$newUid,'role'=>$roleIn]]);
