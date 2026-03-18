<?php
declare(strict_types=1);

session_start();

// CSRF token for login form
if (empty($_SESSION['csrf_login'])) {
    $_SESSION['csrf_login'] = bin2hex(random_bytes(16));
}
$csrf = $_SESSION['csrf_login'];

// One-time login error from login.php
$loginError = $_SESSION['login_error'] ?? '';
unset($_SESSION['login_error']);

// Optional status flags
$sent = isset($_GET['sent']);
$ok   = isset($_GET['ok']);

// Optional prefill
$prefillIdentifier = (string)($_GET['identifier'] ?? '');
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Nemi — Sign in</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
  <script src="https://cdn.tailwindcss.com"></script>
  <style>
    body { font-family: Inter, system-ui, sans-serif; }
  </style>
</head>
<body class="min-h-screen bg-slate-100 flex items-center justify-center px-4">
  <div class="w-full max-w-sm bg-white rounded-3xl shadow-xl p-6 border border-slate-200">
    <div class="mb-5 text-center">
      <div class="inline-flex items-center justify-center w-14 h-14 rounded-2xl bg-indigo-600 text-white text-xl font-bold shadow">
        N
      </div>
      <h1 class="mt-4 text-2xl font-extrabold text-slate-900">Sign in to Nemi</h1>
      <p class="mt-1 text-sm text-slate-500">Unlock your account with email or phone and your 4-digit PIN.</p>
    </div>

    <?php if ($loginError !== ''): ?>
      <div class="mb-4 rounded-2xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900">
        <?php echo htmlspecialchars($loginError, ENT_QUOTES, 'UTF-8'); ?>
      </div>
    <?php endif; ?>

    <?php if ($sent): ?>
      <div class="mb-4 rounded-2xl border border-emerald-300 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">
        If the account exists, a reset link has been sent.
      </div>
    <?php endif; ?>

    <?php if ($ok): ?>
      <div class="mb-4 rounded-2xl border border-emerald-300 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">
        PIN updated. You can sign in now.
      </div>
    <?php endif; ?>

    <form method="post" action="/auth/login.php" class="space-y-4">
      <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>">

      <label class="block">
        <span class="text-sm font-semibold text-slate-700">Email or phone</span>
        <input
          class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-3 outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200"
          name="identifier"
          type="text"
          required
          autofocus
          autocomplete="username"
          placeholder="you@example.com or 2035551234"
          value="<?php echo htmlspecialchars($prefillIdentifier, ENT_QUOTES, 'UTF-8'); ?>"
        >
      </label>

      <label class="block">
        <span class="text-sm font-semibold text-slate-700">4-digit PIN</span>
        <input
          class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-3 tracking-[0.35em] text-center text-lg outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200"
          name="pin"
          type="password"
          required
          maxlength="4"
          inputmode="numeric"
          pattern="\d{4}"
          autocomplete="current-password"
          placeholder="••••"
        >
      </label>

      <input type="hidden" name="device_id" value="d_abc123">

      <button class="w-full rounded-xl bg-indigo-600 py-3 font-semibold text-white shadow hover:bg-indigo-700 transition">
        Unlock Nemi
      </button>
    </form>

    <div class="mt-4 text-xs text-slate-500 text-center">
      Use the email or phone attached to your account.
    </div>

    <div class="mt-5 flex items-center justify-between text-sm">
      <a class="text-slate-600 underline hover:text-slate-900" href="/auth/forgot.html">Forgot PIN?</a>
      <a class="text-slate-600 underline hover:text-slate-900" href="/auth/register.html">Request access</a>
    </div>
  </div>
</body>
</html>
