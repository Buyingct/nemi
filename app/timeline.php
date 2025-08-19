<?php
session_start();
if (empty($_SESSION['user_id'])) { header('Location: /auth/login.html'); exit; }

$userId  = $_SESSION['user_id'];
$showPin = false; // ← disable PIN on web

// Resolve client assets
$clientsJson = __DIR__ . '/../data/clients.json';
$client = [
  'svg'           => 'assets/svgs/timeline.svg',
  'timeline_data' => 'data/clients/default.json'
];

if (file_exists($clientsJson)) {
  $all = json_decode(file_get_contents($clientsJson), true) ?: [];
  if (!empty($all[$userId])) $client = array_merge($client, $all[$userId]);
}

$svgPath  = __DIR__ . '/../' . $client['svg'];
$dataPath = __DIR__ . '/../' . $client['timeline_data'];

$timeline = file_exists($dataPath) ? (json_decode(file_get_contents($dataPath), true) ?: []) : [];
$states   = $timeline['states'] ?? [];
$notes    = $timeline['notes'] ?? [];
$svg      = file_exists($svgPath) ? file_get_contents($svgPath) : '<!-- missing SVG -->';
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="../css/site.css">
  <link rel="stylesheet" href="../css/nemi_timeline.css">
  <title>Nemi • Timeline</title>
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

<!-- Always load timeline data & script -->
<script>

window.NEMI = {
    userId: "<?= htmlspecialchars($userId) ?>",
    states: <?= json_encode($states) ?>,
    notes:  <?= json_encode($notes)  ?>
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

  // Only show on mobile AND when cookie exists
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
