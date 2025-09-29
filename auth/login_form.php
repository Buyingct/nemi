<?php
// /auth/login_form.php  (serves the login page)
// If you prefer the old name, make sure the handler is /auth/login.php and this stays login_form.php

declare(strict_types=1);
session_start();

// One-time CSRF for login
if (empty($_SESSION['csrf_login'])) {
  $_SESSION['csrf_login'] = bin2hex(random_bytes(16));
}
$csrf = $_SESSION['csrf_login'];

// Simple flags for messages (optional)
$bad  = isset($_GET['e']);   // failed login
$sent = isset($_GET['sent']); // reset email sent
$ok   = isset($_GET['ok']);   // password changed ok
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Nemi — Sign in</title>
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-slate-50 flex items-center justify-center">
  <div class="w-full max-w-sm bg-white rounded-2xl shadow p-6">
    <h1 class="text-xl font-bold">Sign in to Nemi</h1>

    <?php if ($bad): ?>
      <div class="mt-3 rounded border p-3 bg-amber-50 border-amber-300 text-amber-900 text-sm">
        We couldn’t sign you in. Please check your email or password.
      </div>
    <?php endif; ?>
    <?php if ($sent): ?>
      <div class="mt-3 rounded border p-3 bg-emerald-50 border-emerald-300 text-emerald-900 text-sm">
        If the email exists, a reset link has been sent.
      </div>
    <?php endif; ?>
    <?php if ($ok): ?>
      <div class="mt-3 rounded border p-3 bg-emerald-50 border-emerald-300 text-emerald-900 text-sm">
        Password updated. Please sign in.
      </div>
    <?php endif; ?>

    <form class="mt-4 space-y-3" method="post" action="/auth/login.php">
      <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES); ?>">
      <label class="block">
        <span class="text-sm font-medium">Email</span>
        <input class="mt-1 w-full rounded border-slate-300" name="email" type="email" required autofocus>
      </label>
      <label class="block">
        <span class="text-sm font-medium">Password</span>
        <input class="mt-1 w-full rounded border-slate-300" name="password" type="password" required>
      </label>
      <button class="w-full mt-2 rounded-lg bg-indigo-600 text-white font-semibold py-2">Sign in</button>
    </form>

    <div class="mt-3 text-xs text-slate-500">
      If you were invited, check your email for your temporary password.
    </div>

    <div class="mt-4 text-sm flex justify-between">
      <a class="underline" href="/auth/forgot.html">Forgot password?</a>
      <!-- If you don’t want self-registration, hide this link or point to an access request form -->
      <a class="underline" href="/auth/register.html">Request access</a>
    </div>
  </div>
</body>
</html>
