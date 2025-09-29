<?php
declare(strict_types=1);

// Harden PHP session cookies (MUST be set BEFORE session_start)
session_set_cookie_params([
  'lifetime' => 0,
  'path'     => '/',
  'domain'   => '',           // set if you need a specific domain
  'secure'   => true,         // require HTTPS in prod
  'httponly' => true,
  'samesite' => 'Lax',
]);

session_start();

// [NEMI:PATCH login.php] — email + password login with diagnostics

// Always regenerate on login to prevent session fixation
session_regenerate_id(true);

// ---------- Logging setup ----------
$logDir = __DIR__ . '/../logs';
if (!is_dir($logDir)) { @mkdir($logDir, 0770, true); }
ini_set('log_errors', '1');
ini_set('error_log', $logDir . '/auth.log');
error_reporting(E_ALL);

// Helper: fail with log but generic user message
function nemi_fail(int $code, string $why): void {
  error_log("[LOGIN] $why");
  http_response_code($code);
  echo 'Invalid credentials';
  exit;
}

// ---------- Paths ----------
$idxPath = __DIR__ . '/../data/user_index.json';
$usrPath = __DIR__ . '/../data/users.json';

// ---------- Require POST ----------
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
  nemi_fail(405, 'Non-POST to login');
}

// ---------- Read inputs ----------
$email = strtolower(trim($_POST['email'] ?? ''));
$pass  = $_POST['password'] ?? '';
$csrf  = $_POST['csrf'] ?? ''; // token created on the FORM page, not here

if ($email === '' || $pass === '') {
  nemi_fail(400, 'Missing email or password');
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
  nemi_fail(400, "Invalid email format: $email");
}

// ---------- Verify CSRF ----------
if (!is_string($csrf) || !hash_equals($_SESSION['csrf_login'] ?? '', $csrf)) {
  nemi_fail(400, 'Bad CSRF token');
}
unset($_SESSION['csrf_login']); // one-time use

// ---------- Load stores (robust against missing/empty files) ----------
$index = json_decode(@file_get_contents($idxPath) ?: '[]', true);
if (!is_array($index)) $index = [];
$users = json_decode(@file_get_contents($usrPath) ?: '[]', true);
if (!is_array($users)) $users = [];

// ---------- Resolve UID ----------
$uid = $index['email:' . $email] ?? null;

// If no UID, try to detect a casing mismatch in the index (helpful after restores)
if (!$uid) {
  foreach ($index as $k => $v) {
    if (strpos($k, 'email:') === 0 && strtolower(substr($k, 6)) === $email) {
      $uid = $v;
      error_log("[LOGIN] Found UID via case-mismatch key: '$k' -> $uid");
      break;
    }
  }
}
if (!$uid) { nemi_fail(401, "No UID for email:$email in user_index.json @ $idxPath"); }

$user = $users[$uid] ?? null;
if (!$user) { nemi_fail(401, "UID $uid not found in users.json @ $usrPath"); }

// ---------- Verify password ----------
$hash = $user['password_hash'] ?? '';
if (!is_string($hash) || $hash === '') {
  nemi_fail(401, "No password_hash for UID $uid");
}
if (!password_verify($pass, $hash)) {
  nemi_fail(401, "password_verify failed for UID $uid (email:$email)");
}

// Upgrade old/weak hashes transparently
if (password_needs_rehash($hash, PASSWORD_DEFAULT)) {
  $users[$uid]['password_hash'] = password_hash($pass, PASSWORD_DEFAULT);
  @file_put_contents($usrPath, json_encode($users, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
  @chmod($usrPath, 0660);
}

// ---------- Success: set session ----------
$roleRaw = strtolower((string)($user['role'] ?? 'buyer'));
$roleMap = ['client' => 'buyer']; // normalize legacy roles
$role    = $roleMap[$roleRaw] ?? $roleRaw;

$_SESSION['user_id'] = $uid;   // keep your existing key
$_SESSION['uid']     = $uid;
$_SESSION['email']   = $email;
$_SESSION['role']    = $role;

// Clear any device PIN cookie for web
setcookie('nemi_device', '', time() - 3600, '/', '', true, true);

// ---------- Optional: route by case assignment ----------
$assignPath = __DIR__ . '/../data/cases/user_cases.json';
$userCases = [];
if (is_file($assignPath)) {
  $txt = file_get_contents($assignPath);
  if ($txt !== false && $txt !== '') {
    $tmp = json_decode($txt, true);
    if (is_array($tmp)) { $userCases = $tmp[$uid] ?? []; }
  }
}

// ---------- Redirects ----------
if (!empty($userCases)) {
  // send to first case timeline for now
  $caseId = $userCases[0];
  header('Location: /app/client/timeline.php?case=' . urlencode($caseId));
  exit;
}

// NEMI ROLE ROUTING — Realtor goes to your new portal
switch ($role) {
  case 'buyer':
    header('Location: /tools/dashboard/buyer.html'); break;
  case 'seller':
    header('Location: /tools/dashboard/seller.html'); break;
  case 'realtor':
    header('Location: /app/realtor_portal.php'); break; // ✅ your portal
  case 'lender':
    header('Location: /tools/dashboard/lender.html'); break;
  case 'attorney':
    header('Location: /tools/dashboard/attorney.html'); break;
  default:
    header('Location: /tools/dashboard/index.html'); break;
}
exit;
