<?php
session_start();
if (!isset($_SESSION['client_id'])) { header('Location: ../auth/signin.html'); exit; }

$clientId = $_SESSION['client_id'];
$idx = json_decode(file_get_contents(__DIR__ . '/../data/clients.json'), true);
$client = $idx[$clientId];

$svgPath  = __DIR__ . '/../' . $client['svg'];                // assets/svgs/timeline.svg
$dataPath = __DIR__ . '/../' . $client['timeline_data'];      // data/clients/12345.json

$timeline = json_decode(file_get_contents($dataPath), true);
$states = $timeline['states'] ?? [];
$notes  = $timeline['notes'] ?? [];
$svg    = file_get_contents($svgPath);
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

  <!-- bottom sheet -->
  <div id="nt_sheet" aria-hidden="true">
    <div class="grab"></div>
    <h3 id="nt_title">Step</h3>
    <div class="meta" id="nt_meta">—</div>
    <div id="nt_body">—</div>
    <div class="row" style="margin-top:12px;">
      <button class="btn" id="nt_close">Close</button>
      <button class="btn primary" id="nt_done">Mark Done</button>
    </div>
  </div>
<!-- PIN Overlay -->
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

    <!-- OTP step (hidden until needed) -->
    <form id="otpForm" class="otp-form hide" method="post" action="/auth/verify_otp.php">
      <p>We sent a 6-digit code to your email/phone.</p>
      <input class="field" name="code" pattern="\d{6}" maxlength="6" inputmode="numeric" placeholder="123456" required>
      <div class="row">
        <button class="btn" type="submit">Verify</button>
        <form method="post" action="/auth/resend_otp.php"><button class="btn ghost" type="submit">Resend</button></form>
      </div>
      <p id="otpErr" class="err hide">Invalid or expired code.</p>
    </form>
  </div>
</div>

</main>

<script>
  window.NEMI = {
    clientId: "<?php echo htmlspecialchars($clientId); ?>",
    states: <?php echo json_encode($states); ?>,
    notes:  <?php echo json_encode($notes); ?>
  };
</script>
<script src="../js/timeline.js" defer></script>
<script>
(function(){
  const gate = document.getElementById('pinGate');
  const pins = Array.from(document.querySelectorAll('#pinForm .pin'));
  const pinErr = document.getElementById('pinErr');
  const otpForm = document.getElementById('otpForm');
  const forgot = document.getElementById('forgotPin');

  // Always show gate for remembered devices (simple rule).
  gate.removeAttribute('aria-hidden');

  // Auto-advance
  pins.forEach((el,i)=>{
    el.addEventListener('input', ()=>{
      el.value = el.value.replace(/\D/g,'').slice(0,1);
      if (el.value && pins[i+1]) pins[i+1].focus();
    });
    el.addEventListener('keydown', e=>{
      if (e.key==='Backspace' && !el.value && pins[i-1]) pins[i-1].focus();
    });
  });

  // Submit PIN → /auth/verify_pin.php
  document.getElementById('pinForm').addEventListener('submit', async (e)=>{
    e.preventDefault();
    const pin = pins.map(i=>i.value).join('');
    if (pin.length!==4) return;
    pinErr.classList.add('hide');

    const res = await fetch('/auth/verify_pin.php', {
      method:'POST',
      headers:{'Content-Type':'application/x-www-form-urlencoded'},
      body: new URLSearchParams({pin})
    });
    const j = await res.json().catch(()=>({ok:false}));

    if (j.ok) {
      gate.style.display = 'none';  // unlock
      return;
    }
    // locked flow OR too many fails
    if (j.locked || (j.fail_count && j.fail_count>=5)) {
      // flip to OTP form
      document.getElementById('pinForm').classList.add('hide');
      otpForm.classList.remove('hide');
      return;
    }
    // normal wrong pin
    pinErr.classList.remove('hide');
    pins.forEach(p=>p.value=''); pins[0].focus();
  });

  // Forgot PIN → go straight to OTP mode (we’ll reuse verify_otp.php)
  forgot.addEventListener('click', async (e)=>{
    e.preventDefault();
    document.getElementById('pinForm').classList.add('hide');
    otpForm.classList.remove('hide');
    // Optionally trigger a fresh OTP here by POSTing to /auth/resend_otp.php
    try { await fetch('/auth/resend_otp.php', {method:'POST'}); } catch(e){}
  });

  // When OTP is verified, verify_otp.php will redirect:
  // - If device has no PIN → /auth/create-pin.php (set a new PIN)
  // - Else → /app/timeline.php (and we’ll show gate again to enter PIN)
})();
</script>

</body>
</html>
