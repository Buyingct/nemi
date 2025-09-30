<?php
declare(strict_types=1);
session_start();

header('Content-Type: application/json; charset=utf-8');

// ---- Admin gate (unchanged) ----
$ADMINS = ['viviana@buyingct.com','you@example.com'];
if (!isset($_SESSION['email'])) { http_response_code(401); echo json_encode(['error'=>'auth']); exit; }
$me = strtolower($_SESSION['email']);
if (!in_array($me, array_map('strtolower',$ADMINS), true)) { http_response_code(403); echo json_encode(['error'=>'forbidden']); exit; }

// ---- CSRF (unchanged) ----
$raw = file_get_contents('php://input');
$in  = json_decode($raw ?: '[]', true) ?: [];
if (!isset($in['csrf']) || !hash_equals($_SESSION['csrf_admin_users'] ?? '', (string)$in['csrf'])) {
  http_response_code(400); echo json_encode(['error'=>'bad_csrf']); exit;
}
$action = (string)($in['action'] ?? '');

// ---- Paths (unchanged) ----
$base = dirname(__DIR__);
$idxPath   = $base . '/data/user_index.json';
$usrPath   = $base . '/data/users.json';
$casesIx   = $base . '/data/cases/user_cases.json';
@mkdir($base . '/data', 0770, true);
@mkdir($base . '/data/cases', 0770, true);

// ---- Helpers (unchanged + add mail + templates) ----
function jread($p){ return file_exists($p) ? (json_decode(file_get_contents($p), true) ?: []) : []; }
function jwrite($p,$a){ @mkdir(dirname($p),0770,true); $ok=file_put_contents($p,json_encode($a,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)); @chmod($p,0660); return $ok!==false; }
function new_uid(){ return 'u_' . bin2hex(random_bytes(8)); }
function temp_password(){ return substr(str_shuffle('ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789'),0,10); }
function out($a){ echo json_encode($a); exit; }
function normrole($r){ $r=strtolower((string)$r); return in_array($r,['buyer','seller','realtor','lender','attorney','admin'],true)?$r:'buyer'; }

// ✅ Use your PHPMailer helper
require_once __DIR__ . '/../auth/send_helpers.php';

// Pretty HTML templates
function email_user_created_html(string $name, string $email, string $tmp): string {
  $safeName = htmlspecialchars($name ?: 'there', ENT_QUOTES, 'UTF-8');
  $safeEmail= htmlspecialchars($email, ENT_QUOTES, 'UTF-8');
  $safeTmp  = htmlspecialchars($tmp, ENT_QUOTES, 'UTF-8');
  return <<<HTML
  <div style="font:14px/1.5 -apple-system,Segoe UI,Roboto,Arial,sans-serif;color:#0d1330">
    <h2 style="margin:0 0 10px">Welcome to Nemi, {$safeName}</h2>
    <p>Your account was created. Use this temporary password to sign in:</p>
    <p style="font-size:18px;font-weight:800;letter-spacing:1px;background:#fff7d6;border:1px solid #f0d97a;border-radius:8px;padding:10px 12px;display:inline-block">{$safeTmp}</p>
    <p style="margin:16px 0 6px">Email: <b>{$safeEmail}</b></p>
    <p>For security, please change your password after you log in.</p>
    <hr style="border:0;border-top:1px solid #e5ecf4;margin:16px 0">
    <p style="color:#5c6b7a">If you didn’t expect this email, you can ignore it.</p>
  </div>
  HTML;
}

function email_temp_password_html(string $name, string $tmp): string {
  $safeName = htmlspecialchars($name ?: 'there', ENT_QUOTES, 'UTF-8');
  $safeTmp  = htmlspecialchars($tmp, ENT_QUOTES, 'UTF-8');
  return <<<HTML
  <div style="font:14px/1.5 -apple-system,Segoe UI,Roboto,Arial,sans-serif;color:#0d1330">
    <h2 style="margin:0 0 10px">Your Nemi temporary password</h2>
    <p>Hello {$safeName}, here is your new temporary password:</p>
    <p style="font-size:18px;font-weight:800;letter-spacing:1px;background:#fff7d6;border:1px solid #f0d97a;border-radius:8px;padding:10px 12px;display:inline-block">{$safeTmp}</p>
    <p>Please sign in and change it right away.</p>
  </div>
  HTML;
}

// ---- Load stores (unchanged) ----
$index = jread($idxPath);
$users = jread($usrPath);
$uCases= jread($casesIx);

// ---- Actions (same as before; only email parts changed) ----
switch ($action) {

  case 'list': {
    $q = strtolower(trim((string)($in['q'] ?? '')));
    $outRows = [];
    foreach ($users as $uid => $u) {
      $name  = (string)($u['name'] ?? '');
      $email = strtolower((string)($u['email'] ?? ''));
      if ($q && !str_contains(strtolower($name),$q) && !str_contains($email,$q)) continue;
      $outRows[] = [
        'uid'     => $uid,
        'name'    => $name ?: '—',
        'email'   => $email,
        'role'    => strtolower((string)($u['role'] ?? 'buyer')),
        'enabled' => (int)($u['enabled'] ?? 1) ? 1 : 0,
        'cases'   => array_values($uCases[$uid] ?? []),
      ];
    }
    out(['users'=>$outRows]);
  }

  case 'create': {
    $name  = trim((string)($in['name'] ?? ''));
    $email = strtolower(trim((string)($in['email'] ?? '')));
    $role  = normrole($in['role'] ?? 'buyer');
    $enabled = (int)($in['enabled'] ?? 1) ? 1 : 0;

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { http_response_code(400); out(['error'=>'bad_email']); }

    // duplicate?
    $existingUid = $index['email:'.$email] ?? null;
    if ($existingUid && isset($users[$existingUid])) { http_response_code(409); out(['error'=>'exists','uid'=>$existingUid]); }

    $uid = new_uid();
    $tmp = temp_password();
    $users[$uid] = [
      'uid' => $uid,
      'name'=> $name,
      'email'=>$email,
      'role'=> $role,
      'enabled'=>$enabled,
      'password_hash'=> password_hash($tmp, PASSWORD_DEFAULT),
      'created_at'=> date('c'),
    ];
    $index['email:'.$email] = $uid;

    jwrite($usrPath,$users); jwrite($idxPath,$index);

    // ✅ HTML email via your helper
    $html = email_user_created_html($name, $email, $tmp);
    @send_email($email, 'Your Nemi account', $html);

    out(['ok'=>1,'uid'=>$uid,'temp_password'=>$tmp]);
  }

  case 'set_role': {
    $uid = (string)($in['uid'] ?? '');
    $role = normrole($in['role'] ?? 'buyer');
    if (!isset($users[$uid])) { http_response_code(404); out(['error'=>'no_uid']); }
    $users[$uid]['role'] = $role;
    jwrite($usrPath,$users);
    out(['ok'=>1]);
  }

  case 'set_enabled': {
    $uid = (string)($in['uid'] ?? '');
    $enabled = (int)($in['enabled'] ?? 1) ? 1 : 0;
    if (!isset($users[$uid])) { http_response_code(404); out(['error'=>'no_uid']); }
    $users[$uid]['enabled'] = $enabled;
    jwrite($usrPath,$users);
    out(['ok'=>1]);
  }

  case 'reset_password': {
    $uid = (string)($in['uid'] ?? '');
    if (!isset($users[$uid])) { http_response_code(404); out(['error'=>'no_uid']); }
    $tmp = temp_password();
    $users[$uid]['password_hash'] = password_hash($tmp, PASSWORD_DEFAULT);
    jwrite($usrPath,$users);

    $email = (string)($users[$uid]['email'] ?? '');
    $name  = (string)($users[$uid]['name'] ?? '');
    if ($email) {
      $html = email_temp_password_html($name, $tmp);
      @send_email($email, 'Nemi temporary password', $html);
    }
    out(['ok'=>1,'temp_password'=>$tmp]);
  }

  case 'delete_user': {
    $uid = (string)($in['uid'] ?? '');
    if (!isset($users[$uid])) { http_response_code(404); out(['error'=>'no_uid']); }
    $email = strtolower((string)($users[$uid]['email'] ?? ''));
    unset($users[$uid]);
    if ($email) unset($index['email:'.$email]);
    unset($uCases[$uid]);
    jwrite($usrPath,$users); jwrite($idxPath,$index); jwrite($casesIx,$uCases);
    out(['ok'=>1]);
  }

  case 'attach_case': {
    $uid = (string)($in['uid'] ?? '');
    $cid = trim((string)($in['case_id'] ?? ''));
    if (!$cid || !isset($users[$uid])) { http_response_code(400); out(['error'=>'bad']); }
    $arr = $uCases[$uid] ?? [];
    if (!in_array($cid, $arr, true)) { $arr[] = $cid; }
    $uCases[$uid] = array_values($arr);
    jwrite($casesIx,$uCases);
    out(['ok'=>1]);
  }

  case 'detach_case': {
    $uid = (string)($in['uid'] ?? '');
    $cid = trim((string)($in['case_id'] ?? ''));
    if (!isset($users[$uid])) { http_response_code(404); out(['error'=>'no_uid']); }
    $arr = $uCases[$uid] ?? [];
    $uCases[$uid] = array_values(array_filter($arr, fn($x)=>$x!==$cid));
    jwrite($casesIx,$uCases);
    out(['ok'=>1]);
  }

  case 'import_csv': {
    $csv = (string)($in['csv'] ?? '');
    if ($csv===''){ http_response_code(400); out(['error'=>'no_csv']); }

    $lines = preg_split('/\r\n|\n|\r/', $csv);
    if (!$lines) out(['imported'=>0]);

    // parse header
    $header = str_getcsv(array_shift($lines) ?? '');
    $cols = array_map('strtolower', $header);
    $posName = array_search('name',$cols); $posEmail=array_search('email',$cols);
    $posRole = array_search('role',$cols); $posEnabled=array_search('enabled',$cols);

    $imported = 0;
    foreach ($lines as $ln) {
      if (trim($ln)==='') continue;
      $cells = str_getcsv($ln);
      $name  = $posName!==false ? trim((string)($cells[$posName] ?? '')) : '';
      $email = $posEmail!==false ? strtolower(trim((string)($cells[$posEmail] ?? ''))) : '';
      $role  = $posRole!==false ? ($cells[$posRole] ?? 'buyer') : 'buyer';
      $enabled = $posEnabled!==false ? (int)$cells[$posEnabled] : 1;
      if (!filter_var($email, FILTER_VALIDATE_EMAIL)) continue;

      $uid = $index['email:'.$email] ?? null;
      $newUser = false;
      if (!$uid) {
        $uid = new_uid();
        $index['email:'.$email] = $uid;
        $newUser = true;
      }
      if (!isset($users[$uid])) {
        $users[$uid] = [
          'uid'=>$uid,'created_at'=>date('c'),
          'password_hash'=>password_hash(temp_password(), PASSWORD_DEFAULT),
        ];
        $newUser = true;
      }
      $users[$uid]['name']    = $name;
      $users[$uid]['email']   = $email;
      $users[$uid]['role']    = normrole($role);
      $users[$uid]['enabled'] = (int)$enabled ? 1 : 0;
      $imported++;

      // ✅ Optional: email only new users with a fresh temp password
      if ($newUser) {
        $tmp = temp_password();
        $users[$uid]['password_hash'] = password_hash($tmp, PASSWORD_DEFAULT);
        $html = email_user_created_html($name, $email, $tmp);
        @send_email($email, 'Your Nemi account', $html);
      }
    }
    jwrite($usrPath,$users); jwrite($idxPath,$index);
    out(['ok'=>1,'imported'=>$imported]);
  }

  default:
    http_response_code(400); out(['error'=>'unknown_action']);
}
