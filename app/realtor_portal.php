<?php
declare(strict_types=1);
session_start();

if (!isset($_SESSION['user'])) { header('Location: /auth/login_form.php'); exit; }
if (strtolower($_SESSION['user']['role'] ?? '') !== 'realtor') { header('Location: /app/timeline.php'); exit; }

$meEmail = $_SESSION['user']['email'] ?? '';
$meName  = $_SESSION['user']['name']  ?? '';

// ---------- helpers ----------
function jread(string $p) { return file_exists($p) ? (json_decode(file_get_contents($p), true) ?: []) : []; }
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// ---------- data sources (simple, local JSONs you can replace later with DB) ----------
$recentPath   = __DIR__ . '/../data/analytics/recent_views_' . preg_replace('/[^a-z0-9_.-]/i','_', $meEmail) . '.json';
// Format: [{ "client_name":"Elizabeth Salazar", "case_id":"case_buyer_172...", "when":"2025-09-28T20:01:02Z" }, ...]
$recentViews  = jread($recentPath);

$listingsAll  = jread(__DIR__ . '/../data/listings/listings.json');
// Format: [{ "address":"89 Kelsey St, Waterbury, CT", "mls":"1706...", "agent_email":"you@...", "status":"Active" }, ...]
$myListings   = array_values(array_filter($listingsAll, fn($L) => strtolower($L['agent_email'] ?? '') === strtolower($meEmail)));

$contactsPath = __DIR__ . '/../data/contacts/realtor_contacts_' . preg_replace('/[^a-z0-9_.-]/i','_', $meEmail) . '.json';
// Format: [{ "name":"Steve", "role":"lender", "email":"...", "phone":"..." }, ...]
$contacts     = jread($contactsPath);

// Small helpers for icons (swap to your SVGs later)
function starBadge($type='gold'){
  $fill = $type==='gold' ? '#ffcc33' : ($type==='silver' ? '#bcc6cc' : '#e1e1e1');
  return '<span style="display:inline-block;width:18px;height:18px;border-radius:50%;background:'.$fill.';box-shadow:inset 0 0 0 2px #243;vertical-align:middle;margin-right:8px"></span>';
}
function avatarDot($seed){
  $h = substr(md5($seed),0,6);
  return '<span style="display:inline-block;width:22px;height:22px;border-radius:50%;background:#'.$h.';margin-right:8px;vertical-align:middle"></span>';
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Realtor Portal — <?=h($meName)?></title>
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700;800;900&display=swap" rel="stylesheet">
<style>
  :root{
    --ink:#0d1330; --ink-2:#243; --muted:#5a6b7a;
    --edge:#345; --trim:#8ec3c7; --accent:#d4af37; /* gold */
    --pill:#e7f3f5; --pill-edge:#6ca1a7; --rail:#20264c;
    --bg:#f7fbfd; --card:#fff;
  }
  *{ box-sizing: border-box; }
  body{ margin:0; font-family:Poppins,system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif; color:var(--ink); background:var(--bg); }

  header{
    display:flex; align-items:center; justify-content:space-between;
    padding:20px 28px; background:#fff; border-bottom:2px solid var(--edge);
  }
  .brand{ display:flex; align-items:center; gap:16px; }
  .brand .logo{
    width:200px; height:40px; background:#001a3a; border-radius:8px;
    color:#fff; display:flex; align-items:center; justify-content:center; font-weight:800; letter-spacing:.5px;
  }
  .hello{ font-size:28px; font-weight:900; text-shadow:1px 1px 0 #00000010; }
  .bar-actions{ display:flex; gap:12px; }
  .btn{
    display:inline-flex; align-items:center; gap:8px; padding:10px 14px; border-radius:999px;
    border:2px solid var(--accent); color:var(--ink); background:#fff; font-weight:700; text-decoration:none;
  }

  .wrap{ max-width:1200px; margin:28px auto; padding:0 20px; }
  .grid{
    display:grid; grid-template-columns:1fr 1fr 1fr; gap:22px;
  }
  .card{
    background:var(--card); border:2px solid var(--edge); border-radius:16px; overflow:hidden;
  }
  .card h3{ margin:0; padding:14px 16px; border-bottom:2px solid var(--edge); font-size:20px; }
  .list{ padding:14px 12px 18px; }
  .pill{
    display:flex; align-items:center; justify-content:space-between; gap:10px;
    background:var(--pill); border:2px solid var(--pill-edge); border-radius:999px;
    padding:10px 12px; margin:10px 6px;
  }
  .pill .left{ display:flex; align-items:center; gap:8px; overflow:hidden; }
  .pill .label{ white-space:nowrap; overflow:hidden; text-overflow:ellipsis; font-weight:700; }
  .rail{ width:10px; background:var(--rail); border-radius:10px; }
  .note{ color:var(--muted); font-size:12px; text-align:center; padding:12px 0 4px; }

  /* basic responsive */
  @media (max-width: 980px){
    .grid{ grid-template-columns:1fr; }
  }

  /* quick message bar */
  .msgbar{
    display:flex; align-items:center; gap:8px; margin:12px 10px 18px;
    border:2px solid var(--accent); border-radius:999px; padding:8px 10px;
    background:#fffef8;
  }
  .msgbar input{
    flex:1; border:0; outline:0; font-size:14px; padding:6px 8px; background:transparent;
  }
  .msgbar button{
    border:0; border-left:2px solid var(--accent); padding:8px 14px; font-weight:800; background:#fff; cursor:pointer;
  }
</style>
</head>
<body>

<header>
  <div class="brand">
    <div class="logo">Fercodini</div>
    <div class="hello">Welcome Realtor, <?=h($meName)?>!</div>
  </div>
  <div class="bar-actions">
    <a class="btn" href="/admin/create_case.php">📄 Send / Request Forms</a>
    <a class="btn" href="/app/grant_access.php">✅ Grant Client Access</a>
  </div>
</header>

<div class="wrap">
  <div class="grid">

    <!-- Left: Recent Clients -->
    <section class="card">
      <h3>Most recently viewed client page</h3>
      <div class="list">
        <?php if (!$recentViews): ?>
          <div class="note">No recent client views yet.</div>
        <?php else: foreach ($recentViews as $row): ?>
          <a class="pill" href="/app/timeline.php?case=<?=h($row['case_id'] ?? '')?>">
            <div class="left">
              <?=starBadge('gold') . avatarDot(($row['client_name'] ?? '') . ($row['case_id'] ?? ''))?>
              <span class="label"><?=h($row['client_name'] ?? 'Client')?></span>
            </div>
            <div class="rail" aria-hidden="true"></div>
          </a>
        <?php endforeach; endif; ?>
      </div>
    </section>

    <!-- Middle: Listings -->
    <section class="card">
      <h3>Listed Properties</h3>
      <div class="list">
        <?php if (!$myListings): ?>
          <a class="pill" href="/admin/add_listing.php">
            <div class="left"><?=starBadge('silver')?><span class="label">Add Listing Property Here</span></div>
            <div class="rail" aria-hidden="true"></div>
          </a>
        <?php else: foreach ($myListings as $L): ?>
          <a class="pill" href="/app/listing.php?mls=<?=h($L['mls'] ?? '')?>">
            <div class="left">
              <?=starBadge('gold')?>
              <span class="label"><?=h($L['address'] ?? 'Listing')?></span>
            </div>
            <div class="rail" aria-hidden="true"></div>
          </a>
        <?php endforeach; endif; ?>
        <a class="pill" href="/admin/add_listing.php">
          <div class="left"><?=starBadge('silver')?><span class="label">Add Listing Property Here</span></div>
          <div class="rail" aria-hidden="true"></div>
        </a>
      </div>
    </section>

    <!-- Right: Quick Message + Contacts -->
    <section class="card">
      <h3>Send a quick message to:</h3>
      <form class="msgbar" method="post" action="/app/send_quick_message.php">
        <input type="email" name="to" placeholder="Type an email or pick from Contacts below…" required />
        <input type="hidden" name="from" value="<?=h($meEmail)?>">
        <button type="submit">✉︎ Send</button>
      </form>

      <div class="list">
        <div class="note">Contact List — click a star to request.</div>
        <?php if (!$contacts): ?>
          <div class="note">No contacts yet.</div>
        <?php else: foreach ($contacts as $C): ?>
          <div class="pill">
            <div class="left">
              <?=starBadge('gold')?><?=avatarDot($C['email'] ?? $C['name'] ?? '')?>
              <span class="label"><?=h($C['name'] ?? 'Contact')?><?= isset($C['role']) ? ' — '.h(ucfirst($C['role'])) : '' ?></span>
            </div>
            <a href="/app/request_from_contact.php?email=<?=h($C['email'] ?? '')?>" style="text-decoration:none;font-weight:800">Request</a>
          </div>
        <?php endforeach; endif; ?>
      </div>
    </section>

  </div>

  <div class="note">Color based on company colors if broker set of selected theme by realtor.</div>
</div>

</body>
</html>
