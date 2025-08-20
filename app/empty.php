<?php
session_start();
if (empty($_SESSION['user_id'])) { header('Location: /auth/login.html'); exit; }
?>
<!doctype html><meta charset="utf-8">
<link rel="stylesheet" href="/css/site.css">
<div class="max-w-xl mx-auto p-8">
  <h1 class="text-2xl font-bold">No cases yet</h1>
  <p class="mt-2 text-slate-600">Ask your agent to add you to a case.</p>
</div>
