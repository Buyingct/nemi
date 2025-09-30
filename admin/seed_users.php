<?php
declare(strict_types=1);
session_start();

/* ==========================================================
   NEMI — One-time User Seeder
   Creates fresh /data/users.json and /data/user_index.json
   with 6 sample users (one for each role). Backs up old files.
   Path: /admin/seed_users.php
   ========================================================== */

$ADMINS = [
  // Only these emails can run the seeder
  'help@buyingct.com',
  'v.rocharealtor@gmail.com',

];

if (!isset($_SESSION['email'])) { header('Location: /auth/login_form.php'); exit; }
$me = strtolower($_SESSION['email']);
if (!in_array($me, array_map('strtolower',$ADMINS), true)) {
  http_response_code(403); echo "Forbidden"; exit;
}

$base    = dirname(__DIR__);
$dataDir = $base . '/data';
$usersP  = $dataDir . '/users.json';
$indexP  = $dataDir . '/user_index.json';
$casesIx = $dataDir . '/cases/user_cases.json';

@mkdir($dataDir, 0770, true);
@mkdir($dataDir . '/cases', 0770, true);

// CSRF
if (empty($_SESSION['csrf_seed'])) $_SESSION['csrf_seed'] = bin2hex(random_bytes(16));
$CSRF = $_SESSION['csrf_seed'];

function jread($p){ return file_exists($p) ? (json_decode(file_get_contents($p), true) ?: []) : []; }
function jwrite($p,$a){ @mkdir(dirname($p),0770,true); $ok=file_put_contents($p,json_encode($a,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)); @chmod($p,0660); return $ok!==false; }
function new_uid(){ return 'u_' . bin2hex(random_bytes(8)); }
function temp_password(){ return substr(str_shuffle('CATSandMORECATS.'),0,10); }
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$do   = isset($_POST['do']) && $_POST['do'] === '1';
$csrf = $_POST['csrf'] ?? '';

$result = null;

if ($do && hash_equals($_SESSION['csrf_seed'] ?? '', $csrf)) {
  // Backup existing files (if present)
  $ts = date('Ymd_His');
  if (file_exists($usersP)) @rename($usersP, $usersP . ".bak_$ts");
  if (file_exists($indexP)) @rename($indexP, $indexP . ".bak_$ts");
  if (!file_exists($casesIx)) jwrite($casesIx, []);

  // Define your seed users (edit emails/names if you want)
  $seed = [
    ['name'=>'Viviana Rocha',       'email'=>'viviana@buyingct.com',      'role'=>'admin'],
    ['name'=>'Realtor Sample',      'email'=>'realtor@nemi.local',        'role'=>'realtor'],
    ['name'=>'Buyer Sample',        'email'=>'buyer@nemi.local',          'role'=>'buyer'],
    ['name'=>'Seller Sample',       'email'=>'seller@nemi.local',         'role'=>'seller'],
    ['name'=>'Lender Sample',       'email'=>'lender@nemi.local',         'role'=>'lender'],
    ['name'=>'Attorney Sample',     'email'=>'attorney@nemi.local',       'role'=>'attorney'],
  ];

  $users = [];
  $index = [];
  $report = [];

  foreach ($seed as $row) {
    $uid = new_uid();
    $tmp = temp_password();
    $users[$uid] = [
      'uid'           => $uid,
      'name'          => $row['name'],
      'email'         => strtolower($row['email']),
      'role'          => strtolower($row['role']),
      'enabled'       => 1,
      'password_hash' => password_hash($tmp, PASSWORD_DEFAULT),
      'created_at'    => date('c'),
    ];
    $index['email:' . strtolower($row['email'])] = $uid;

    $report[] = [
      'uid'    => $uid,
      'name'   => $row['name'],
      'email'  => strtolower($row['email']),
      'role'   => strtolower($row['role']),
      'temp'   => $tmp,
    ];
  }

  jwrite($usersP, $users);
  jwrite($indexP, $index);

  $result = [
    'ok'      => 1,
    'users'   => $report,
    'usersP'  => $usersP,
    'indexP'  => $indexP,
    'backups' => [
      'users_bak' => file_exists($usersP . ".bak_$ts") ? basename($usersP . ".bak_$ts") : null,
      'index_bak' => file_exists($indexP . ".bak_$ts") ? basename($indexP . ".bak_$ts") : null,
    ],
  ];
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Nemi — Seed Users</title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;800&display=swap" rel="stylesheet">
<style>
  body{ margin:0; font-family:Poppins,system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif; background:#f7f9fc; color:#0d1330; }
  header{ padding:18px 24px; background:#fff; border-bottom:2px solid #2d3a50; display:flex; align-items:center; justify-content:space-between; }
  .wrap{ max-width:900px; margin:24px auto; padding:0 16px; }
  .card{ background:#fff; border:2px solid #2d3a50; border-radius:14px; padding:16px; }
  .btn{ display:inline-block; padding:10px 14px; border-radius:999px; border:2px solid #7c4dff; background:#fff; font-weight:800; cursor:pointer; }
  table{ width:100%; border-collapse:collapse; margin-top:12px; }
  th,td{ padding:10px; border-bottom:1px solid #e6ebf2; text-align:left; font-size:14px; }
  th{ background:#f1f4fa; }
  code{ background:#fff7d6; border:1px solid #f0d97a; padding:2px 6px; border-radius:6px; }
</style>
</head>
<body>
<header>
  <h1>🌱 Nemi — Seed Users</h1>
  <div>Signed in as <b><?=h($me)?></b></div>
</header>
<div class="wrap">
  <div class="card">
    <p>This will <b>replace</b> <code>/data/users.json</code> and <code>/data/user_index.json</code> with fresh records for: admin, realtor, buyer, seller, lender, attorney. Backups are created automatically.</p>
    <form method="post">
      <input type="hidden" name="csrf" value="<?=h($CSRF)?>">
      <input type="hidden" name="do" value="1">
      <button class="btn" type="submit">🚀 Seed now</button>
    </form>
  </div>

  <?php if ($result): ?>
    <div class="card" style="margin-top:16px;">
      <h2>✅ Done</h2>
      <p><b>users.json</b>: <code><?=h($result['usersP'])?></code><br>
         <b>user_index.json</b>: <code><?=h($result['indexP'])?></code></p>
      <?php if ($result['backups']['users_bak'] || $result['backups']['index_bak']): ?>
        <p>Backups created:
          <?php if ($result['backups']['users_bak']): ?> <code><?=h($result['backups']['users_bak'])?></code> <?php endif; ?>
          <?php if ($result['backups']['index_bak']): ?> <code><?=h($result['backups']['index_bak'])?></code> <?php endif; ?>
        </p>
      <?php endif; ?>

      <table>
        <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Temp Password</th></tr></thead>
        <tbody>
          <?php foreach ($result['users'] as $u): ?>
          <tr>
            <td><?=h($u['name'])?></td>
            <td><?=h($u['email'])?></td>
            <td><?=h($u['role'])?></td>
            <td><code><?=h($u['temp'])?></code></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>

      <p style="margin-top:12px;">Next: <b>Sign out</b> and test sign-in with the desired role.  
      Admins go to <code>/admin/users.php</code>, Realtors to <code>/app/realtor_portal.php</code>, Buyers/Sellers to timeline.</p>
    </div>
  <?php endif; ?>
</div>
</body>
</html>
