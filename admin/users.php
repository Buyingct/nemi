<?php
declare(strict_types=1);
session_start();

/* =========================
   NEMI Admin — Users Hub
   Path: /admin/users.php
   ========================= */

$ADMINS = [
  // Only these emails can access (add yours)
  'you@example.com',
  'viviana@buyingct.com'
];

// Must be logged in + authorized
if (!isset($_SESSION['email'])) { header('Location: /auth/login_form.php'); exit; }
$me = strtolower($_SESSION['email']);
if (!in_array($me, array_map('strtolower', $ADMINS), true)) {
  http_response_code(403);
  echo "Forbidden";
  exit;
}

// CSRF for this page
if (empty($_SESSION['csrf_admin_users'])) {
  $_SESSION['csrf_admin_users'] = bin2hex(random_bytes(16));
}
$CSRF = $_SESSION['csrf_admin_users'];
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Nemi — Admin Users</title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;800&display=swap" rel="stylesheet">
<style>
  :root{ --ink:#0d1330; --edge:#2d3a50; --accent:#7c4dff; --muted:#5c6b7a; --ok:#18a957; --bad:#c62828; }
  *{ box-sizing:border-box; }
  body{ margin:0; font-family:Poppins,system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif; color:var(--ink); background:#f7f9fc; }
  header{ padding:18px 24px; background:#fff; border-bottom:2px solid var(--edge); display:flex; align-items:center; justify-content:space-between; }
  h1{ margin:0; font-size:22px; }
  .wrap{ max-width:1200px; margin:22px auto; padding:0 16px; }
  .grid{ display:grid; grid-template-columns: 420px 1fr; gap:18px; }
  .card{ background:#fff; border:2px solid var(--edge); border-radius:14px; overflow:hidden; }
  .card h2{ margin:0; padding:12px 14px; font-size:16px; border-bottom:2px solid var(--edge); }
  .card .body{ padding:14px; }
  label{ font-weight:600; display:block; margin:10px 0 6px; }
  input[type=text], input[type=email], input[type=password], select{
    width:100%; padding:10px 12px; border:2px solid var(--edge); border-radius:10px; outline:0;
  }
  .row{ display:grid; grid-template-columns:1fr 1fr; gap:10px; }
  .btn{ display:inline-flex; align-items:center; gap:8px; padding:10px 14px; border-radius:999px; border:2px solid var(--edge); background:#fff; cursor:pointer; font-weight:800; }
  .btn.primary{ border-color:var(--accent); }
  .btn.ok{ border-color:var(--ok); }
  .btn.bad{ border-color:var(--bad); }
  .muted{ color:var(--muted); font-size:12px; }
  table{ width:100%; border-collapse:collapse; }
  th, td{ padding:10px; border-bottom:1px solid #e6ebf2; text-align:left; font-size:14px; }
  th{ background:#f1f4fa; position:sticky; top:0; z-index:1; }
  .actions button{ margin-right:6px; }
  .pill{ display:inline-block; padding:2px 8px; border-radius:999px; border:2px solid var(--edge); font-size:12px; }
  .pill.on{ border-color:var(--ok); }
  .searchbar{ display:flex; gap:8px; margin-bottom:10px; }
</style>
</head>
<body>
<header>
  <h1>🔐 Nemi — Admin Users</h1>
  <div class="muted">Signed in as <b><?=htmlspecialchars($me)?></b></div>
</header>

<div class="wrap">
  <div class="grid">

    <!-- LEFT: Create / Import -->
    <section class="card">
      <h2>Create user</h2>
      <div class="body">
        <div class="row">
          <div>
            <label>Name</label>
            <input id="c_name" type="text" placeholder="Full name">
          </div>
          <div>
            <label>Email</label>
            <input id="c_email" type="email" placeholder="name@example.com">
          </div>
        </div>
        <div class="row">
          <div>
            <label>Role / Group</label>
            <select id="c_role">
              <option value="buyer">Buyer</option>
              <option value="seller">Seller</option>
              <option value="realtor">Realtor</option>
              <option value="lender">Lender</option>
              <option value="attorney">Attorney</option>
              <option value="admin">Admin</option>
            </select>
          </div>
          <div>
            <label>Status</label>
            <select id="c_enabled">
              <option value="1">Enabled</option>
              <option value="0">Disabled</option>
            </select>
          </div>
        </div>
        <div style="margin-top:10px; display:flex; gap:10px;">
          <button class="btn primary" onclick="createUser()">➕ Create (send temp password)</button>
          <button class="btn" onclick="resetForm()">Reset</button>
        </div>
        <p class="muted" style="margin-top:8px;">User will receive a temp password email (if mailer configured). Password is also shown in a toast.</p>
      </div>

      <h2>Bulk import (CSV)</h2>
      <div class="body">
        <form id="csvForm">
          <label>CSV file (headers: name,email,role,enabled)</label>
          <input id="csvFile" type="file" accept=".csv">
          <div style="margin-top:10px; display:flex; gap:10px;">
            <button type="button" class="btn primary" onclick="importCSV()">📥 Import CSV</button>
            <a class="btn" href="data:text/csv;charset=utf-8,name,email,role,enabled%0AJane Doe,jane@ex.com,buyer,1%0A" download="nemi_users_template.csv">⬇ Template</a>
          </div>
          <p class="muted">Unknown roles default to buyer. enabled: 1 or 0.</p>
        </form>
      </div>
    </section>

    <!-- RIGHT: List / Edit -->
    <section class="card">
      <h2>Users</h2>
      <div class="body">
        <div class="searchbar">
          <input id="q" type="text" placeholder="Search name or email…" oninput="render()">
          <button class="btn" onclick="refresh()">⟳ Refresh</button>
        </div>
        <div style="max-height:63vh; overflow:auto; border:1px solid #e1e7f0; border-radius:10px;">
          <table id="tbl">
            <thead>
              <tr>
                <th>Name</th><th>Email</th><th>Role</th><th>Status</th><th>Cases</th><th>Actions</th>
              </tr>
            </thead>
            <tbody></tbody>
          </table>
        </div>
        <p class="muted">Click “Role” to change. Use actions to reset password, enable/disable, attach/detach to case.</p>
      </div>
    </section>

  </div>
</div>

<script>
const CSRF = <?=json_encode($CSRF)?>;
let rows = [];

async function api(path, payload){
  const res = await fetch('/admin/users_api.php', {
    method: 'POST',
    headers: {'Content-Type':'application/json'},
    body: JSON.stringify(Object.assign({csrf:CSRF, action
