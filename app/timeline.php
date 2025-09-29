<?php
session_start();
if (empty($_SESSION['user_id'])) { header('Location: /auth/login_form.php'); exit; }

$userId  = $_SESSION['user_id'];
$role    = strtolower($_SESSION['role']  ?? '');
$email   = strtolower($_SESSION['email'] ?? '');
$showPin = false; // ← disable PIN on web

$cfg = include __DIR__ . '/../config/.env.php';

// --- CASE LOADING (new path) ---------------------------------
$caseId   = trim($_GET['case'] ?? '');
$case     = [];
$hasCase  = false;
$hasRealtor = false;
$isAdmin  = ($role === 'admin') || in_array($email, (array)($cfg['ADMINS'] ?? []), true);

if ($caseId !== '') {
  $caseFile = __DIR__ . '/../data/cases/' . basename($caseId) . '.json';
  if (is_file($caseFile)) {
    $case = json_decode(file_get_contents($caseFile), true) ?: [];
    $hasCase = true;
    $hasRealtor = !empty($case['team']['realtor']);
  }
}

// --- LEGACY CLIENT-ASSETS (fallback) --------------------------
$clientsJson = __DIR__ . '/../data/clients.json';
$client = [
  'svg'           => 'assets/svgs/timeline.svg',
  'timeline_data' => 'data/clients/default.json'
];
if (!$hasCase && file_exists($clientsJson)) {
  $all = json_decode(file_get_contents($clientsJson), true) ?: [];
  if (!empty($all[$userId])) $client = array_merge($client, $all[$userId]);
}

// --- ASSET RESOLUTION ----------------------------------------
if ($hasCase) {
  // Case assets from the case file (with sensible defaults)
  $svgRel   = $case['assets']['svg'] ?? 'assets/svgs/timeline_buyer.svg';
  $mapRel   = $case['assets']['map'] ?? 'data/timeline_map_buyer.json';
  $svgPath  = __DIR__ . '/../' . ltrim($svgRel, '/');
  $mapPath  = __DIR__ . '/../' . ltrim($mapRel, '/');
  // states/notes from case
  $states   = $case['timeline']['states'] ?? [];
  $notes    = $case['timeline']['notes']  ?? [];
  $pageTitle = $case['title'] ?? 'Timeline';
} else {
  // Legacy fallback
  $svgPath   = __DIR__ . '/../' . $client['svg'];
  $dataPath  = __DIR__ . '/../' . $client['timeline_data'];
  $timeline  = file_exists($dataPath) ? (json_decode(file_get_contents($dataPath), true) ?: []) : [];
  $states    = $timeline['states'] ?? [];
  $notes     = $timeline['notes'] ?? [];
  $mapPath   = __DIR__ . '/../data/timeline_map_buyer.json';
  $pageTitle = 'Timeline';
}

$svg = file_exists($svgPath) ? file_get_contents($svgPath) : '<!-- missing SVG -->';
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="../css/site.css">
  <link rel="stylesheet" href="../css/nemi_timeline.css">
  <title>Nemi • <?= htmlspecialchars($pageTitle) ?></title>
</head>
<body>
<header class="site-header">
  <div class="head-inner">
    <img src="../assets/svgs/nemi-logo.svg" class="logo" alt="Nemi">
    <nav class="nav">
      <a href="#" id="menu-messages" class="btn ghost">Messages</a>
      <a href="#" id="menu-docs" class="btn ghost">Docs</a>
      <a href="../auth/signout.php" class="btn">Log out</a>
    </nav>
  </div>
</header>

<main>
  <!-- ===== CASE TITLE + TEAM ACTIONS (baked in) ===== -->
  <section style="max-width:980px;margin:16px auto 8px;padding:0 12px;">
    <h1 style="margin:0 0 8px;font-size:20px;font-weight:700;"><?= htmlspecialchars($pageTitle) ?></h1>

    <?php if ($hasCase): ?>
      <?php
        if (empty($_SESSION['csrf_case'])) { $_SESSION['csrf_case'] = bin2hex(random_bytes(16)); }
        $csrfCase = $_SESSION['csrf_case'];
      ?>
      <div style="padding:12px;border:1px solid #e2e8f0;border-radius:10px;background:#fff">
        <h2 style="margin:0 0 8px; font-size:16px;">Team actions</h2>

        <?php if (!$hasRealtor): ?>
          <!-- Users can request a Realtor (admin must approve/attach) -->
          <form method="post" action="/api/request_realtor.php"
                style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin-bottom:8px;">
            <input type="hidden" name="case" value="<?= htmlspecialchars($caseId) ?>">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrfCase) ?>">
            <input name="email" type="email" placeholder="Realtor email" required
                   style="padding:8px 10px;border:1px solid #cbd5e1;border-radius:8px;">
            <input name="note" placeholder="Note (optional)"
                   style="padding:8px 10px;border:1px solid #cbd5e1;border-radius:8px;min-width:220px;">
            <button type="submit" class="btn" style="padding:8px 12px;border-radius:8px;background:#4f46e5;color:#fff;border:0;">
              Request Realtor
            </button>
            <small style="color:#64748b;">Admin verifies license & approves.</small>
          </form>
        <?php endif; ?>

        <!-- Add teammate (admin can add Realtor; others can add Lender/Attorney once a Realtor exists) -->
        <form method="post" action="/api/add_to_case.php"
              style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
          <input type="hidden" name="case" value="<?= htmlspecialchars($caseId) ?>">
          <input type="hidden" name="send_invites" value="1">
          <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrfCase) ?>">

          <select name="role" required
                  style="padding:8px 10px;border:1px solid #cbd5e1;border-radius:8px;">
            <?php if ($isAdmin): ?>
              <option value="realtor">Realtor (admin only)</option>
            <?php endif; ?>
            <option value="lender"   <?= $hasRealtor ? '' : 'disabled' ?>>Lender</option>
            <option value="attorney" <?= $hasRealtor ? '' : 'disabled' ?>>Attorney</option>
          </select>

          <input name="email" type="email" placeholder="Teammate email" required
                 style="padding:8px 10px;border:1px solid #cbd5e1;border-radius:8px;min-width:240px;">

          <button type="submit" class="btn" style="padding:8px 12px;border-radius:8px;background:#0ea5e9;color:#fff;border:0;">
            Add to Case
          </button>

          <?php if (!$hasRealtor): ?>
            <small style="color:#b45309;">Lender/Attorney disabled until a Realtor is on the case.</small>
          <?php endif; ?>
        </form>
      </div>
    <?php endif; ?>
  </section>

  <!-- ===== TIMELINE SVG ===== -->
  <section id="timeline-wrap" style="position:relative">
    <?php echo $svg; ?>  <!-- inline SVG so we can add click handlers -->
  </section>

  <!-- bottom sheet (for step details) -->
  <div id="nt_sheet" aria-hidden="true">
    <div class="grab"></div>
    <h3 id="nt_title">Step</h3>
    <div class="meta" id="nt_meta">—</div>
    <div id="nt_body">—</div>
    <div class="row" style="margin-top:12px;">
      <button class="btn" id="nt_close" type="button">Close</button>
      <button class="btn primary" id="nt_done" type="button">Mark Done</button>
    </div>
  </div>

  <?php if ($showPin): ?>
  <!-- PIN / OTP overlay (rendered only if $showPin === true) -->
  <div id="pinGate" class="pin-gate-backdrop" aria-hidden="true">
    <div class="pin-gate-card">
      <h3 class="title">Unlock with your PIN</h3>

      <form id="pinForm" class="pin-form">
        <div class="pin-row">
          <input inputmode="numeric" maxlength="1" class="pin" />
          <input inputmode="numeric" maxlength="1" class="pin" />
          <input inputmode="numeric" maxlength="1" class="pin" />
          <input inputmode="numeric" maxlength="1" class="pin" />
        </div>
        <button class="btn primary" type="submit">Unlock</button>
        <p id="pinErr" class="err hide">Wrong PIN. Try again.</p>
        <p class="sub"><a href="#" id="forgotPin">Forgot PIN?</a></p>
      </form>


      <form id="otpForm" class="otp-form hide" method="post" action="/auth/verify_otp.php">
        <p>We sent a 6-digit code to your email/phone.</p>
        <input class="field" name="code" pattern="\d{6}" maxlength="6" inputmode="numeric" placeholder="123456" required>
        <div class="row">
          <button class="btn" type="submit">Verify</button>
           <button id="resendOtp" class="btn ghost" type="button">Resend</button>
        </div>
        <p id="otpErr" class="err hide">Invalid or expired code.</p>
      </form>
    </div>
  </div>
  <?php endif; ?>
</main>


<?php
// Choose the correct map file for the JS boot
$mapJson = (is_readable($mapPath) ? (json_decode(file_get_contents($mapPath), true) ?: new stdClass()) : new stdClass());
?>
 <script>
  window.NEMI = {
    userId: "<?= htmlspecialchars($userId) ?>",
    states: <?= json_encode($states, JSON_UNESCAPED_SLASHES) ?>,
    notes:  <?= json_encode($notes,  JSON_UNESCAPED_SLASHES) ?>,
    map:    <?= json_encode($mapJson, JSON_UNESCAPED_SLASHES) ?>
  };
</script>
 <script src="../js/timeline.js" defer></script>


<?php if ($showPin): ?>
<script>
(function(){
  const gate    = document.getElementById('pinGate');
  const pinErr  = document.getElementById('pinErr');
  const pins    = Array.from(document.querySelectorAll('#pinForm .pin'));
  const otpForm = document.getElementById('otpForm');
  const forgot  = document.getElementById('forgotPin');


  const isMobile = /Android|iPhone|iPad|iPod/i.test(navigator.userAgent);
  const hasDeviceCookie = document.cookie.includes('nemi_device=');
  if (isMobile && hasDeviceCookie) gate.removeAttribute('aria-hidden');

  pins.forEach((el,i)=>{
    el.addEventListener('input', ()=>{
      el.value = el.value.replace(/\D/g,'').slice(0,1);
      if (el.value && pins[i+1]) pins[i+1].focus();
    });
    el.addEventListener('keydown', e=>{
      if (e.key==='Backspace' && !el.value && pins[i-1]) pins[i-1].focus();
    });
  });

  
  document.getElementById('pinForm').addEventListener('submit', async (e)=>{
    e.preventDefault();
    const pin = pins.map(i=>i.value).join('');
    if (pin.length !== 4) return;
    pinErr.classList.add('hide');

    try {
      const res = await fetch('/auth/verify_pin.php', {
        method:'POST',
        headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body: new URLSearchParams({pin})
      });
      const j = await res.json().catch(()=>({ok:false}));
      if (j.ok) { gate.style.display = 'none'; return; }
      if (j.locked || (j.fail_count && j.fail_count >= 5)) {
        document.getElementById('pinForm').classList.add('hide');
        otpForm.classList.remove('hide');
        return;
      }
      pinErr.classList.remove('hide');
      pins.forEach(p=>p.value=''); pins[0].focus();
    } catch(e){ pinErr.classList.remove('hide'); }
  });

  document.getElementById('resendOtp')?.addEventListener('click', async ()=>{
    try { await fetch('/auth/resend_otp.php', {method:'POST'}); } catch(e){}
  });

  document.getElementById('forgotPin').addEventListener('click', async (e)=>{
    e.preventDefault();
    document.getElementById('pinForm').classList.add('hide');
    otpForm.classList.remove('hide');
     try { await fetch('/auth/resend_otp.php', {method:'POST'}); } catch(e){}
    });
})();
</script>
<?php endif; ?>
</body>
</html>
