<?php
declare(strict_types=1);
session_start();

/* =========================
   NEMI Admin — Users Hub
   Path: /admin/users.php
   ========================= */

$ADMINS = [
  // ✅ Only these emails can access (put yours here)
  'viviana@buyingct.com',
  'you@example.com',
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
  th, td{ padding:10px; border-bottom:1px solid #e6ebf2; text-align:left; font-size:14px; vertical-align:middle; }
  th{ background:#f1f4fa; position:sticky; top:0; z-index:1; }
  .actions button{ margin-right:6px; }
  .pill{ display:inline-block; padding:2px 8px; border-radius:999px; border:2px solid var(--edge); font-size:12px; }
  .pill.on{ border-color:var(--ok); }
  .searchbar{ display:flex; gap:8px; margin-bottom:10px; }
  .casebox{ display:flex; gap:6px; flex-wrap:wrap; }
  .case{ padding:2px 8px; border:1px dashed #b7c3d0; border-radius:8px; font-size:12px; }
  .case a{ color:var(--bad); text-decoration:none; margin-left:6px; font-weight:800; }
  .toast{ position:fixed; right:16px; bottom:16px; background:#0d1330; color:#fff; padding:10px 12px; border-radius:10px; box-shadow:0 8px 24px rgba(0,0,0,.2); opacity:0; transform:translateY(8px); transition:.25s; }
  .toast.show{ opacity:1; transform:none; }
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
        <form id="csvForm" onsubmit="return false;">
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
        <p class="muted">Click role to change. Use actions to reset password, enable/disable, and attach/detach to a case.</p>
      </div>
    </section>

  </div>
</div>

<div id="toast" class="toast"></div>

<script>
const CSRF = <?=json_encode($CSRF)?>;
let rows = [];

function toast(msg){
  const t = document.getElementById('toast');
  t.textContent = msg; t.classList.add('show');
  setTimeout(()=>t.classList.remove('show'), 3200);
}

async function api(action, payload){
  const res = await fetch('/admin/users_api.php', {
    method: 'POST',
    headers: {'Content-Type':'application/json'},
    body: JSON.stringify(Object.assign({csrf:CSRF, action}, payload||{}))
  });
  if(!res.ok){ toast('API error '+res.status); throw new Error('HTTP '+res.status); }
  return res.json();
}

async function refresh(){
  const q = document.getElementById('q').value.trim();
  const data = await api('list', {q});
  rows = data.users || [];
  render();
}

function render(){
  const q = document.getElementById('q').value.toLowerCase();
  const tb = document.querySelector('#tbl tbody');
  tb.innerHTML = '';
  (rows||[]).filter(r=>{
    if(!q) return true;
    return (r.name||'').toLowerCase().includes(q) || (r.email||'').toLowerCase().includes(q);
  }).forEach(r=>{
    const tr = document.createElement('tr');

    const tdName = document.createElement('td'); tdName.textContent = r.name||'—';
    const tdEmail= document.createElement('td'); tdEmail.textContent= r.email||'—';

    const tdRole = document.createElement('td');
    const sel = document.createElement('select');
    ['buyer','seller','realtor','lender','attorney','admin'].forEach(v=>{
      const o = document.createElement('option'); o.value=v; o.textContent=v;
      if ((r.role||'').toLowerCase()===v) o.selected=true;
      sel.appendChild(o);
    });
    sel.onchange = async ()=>{
      await api('set_role', {uid:r.uid, role:sel.value});
      toast('Role updated'); refresh();
    };
    tdRole.appendChild(sel);

    const tdStatus = document.createElement('td');
    const pill = document.createElement('span');
    pill.className = 'pill '+(r.enabled?'on':'');
    pill.textContent = r.enabled ? 'Enabled' : 'Disabled';
    pill.style.cursor='pointer';
    pill.onclick = async()=>{
      await api('set_enabled', {uid:r.uid, enabled: r.enabled?0:1});
      toast('Status updated'); refresh();
    };
    tdStatus.appendChild(pill);

    const tdCases = document.createElement('td');
    const box = document.createElement('div'); box.className='casebox';
    (r.cases||[]).forEach(cid=>{
      const c = document.createElement('span'); c.className='case';
      c.textContent = cid;
      const a = document.createElement('a'); a.href='#'; a.textContent='✕';
      a.onclick = async (e)=>{ e.preventDefault(); await api('detach_case',{uid:r.uid, case_id:cid}); toast('Detached'); refresh(); };
      c.appendChild(a); box.appendChild(c);
    });
    // add attach input
    const addWrap = document.createElement('div');
    addWrap.style.marginTop='6px';
    addWrap.innerHTML = '<input type="text" placeholder="case_..." style="width:220px;padding:6px 8px;border:1px solid #b7c3d0;border-radius:8px"> <button class="btn" style="padding:6px 10px">Attach</button>';
    const btn = addWrap.querySelector('button');
    const inp = addWrap.querySelector('input');
    btn.onclick = async ()=>{
      if(!inp.value.trim()) return;
      await api('attach_case',{uid:r.uid, case_id:inp.value.trim()});
      toast('Attached'); refresh();
    };
    tdCases.appendChild(box); tdCases.appendChild(addWrap);

    const tdAct = document.createElement('td'); tdAct.className='actions';
    const b1 = document.createElement('button'); b1.className='btn'; b1.textContent='Reset PW';
    b1.onclick = async()=>{ const out=await api('reset_password',{uid:r.uid}); toast('Temp password: '+out.temp_password); refresh(); };
    const b2 = document.createElement('button'); b2.className='btn bad'; b2.textContent='Delete';
    b2.onclick = async()=>{ if(!confirm('Delete user '+(r.email||'')+'?')) return; await api('delete_user',{uid:r.uid}); toast('Deleted'); refresh(); };
    tdAct.appendChild(b1); tdAct.appendChild(b2);

    tr.appendChild(tdName); tr.appendChild(tdEmail); tr.appendChild(tdRole); tr.appendChild(tdStatus); tr.appendChild(tdCases); tr.appendChild(tdAct);
    tb.appendChild(tr);
  });
}

function resetForm(){
  document.getElementById('c_name').value='';
  document.getElementById('c_email').value='';
  document.getElementById('c_role').value='buyer';
  document.getElementById('c_enabled').value='1';
}

async function createUser(){
  const name = document.getElementById('c_name').value.trim();
  const email= document.getElementById('c_email').value.trim();
  const role = document.getElementById('c_role').value;
  const enabled = parseInt(document.getElementById('c_enabled').value,10);
  if(!email){ toast('Email required'); return; }
  const out = await api('create', {name,email,role,enabled});
  toast('Created. Temp password: '+ out.temp_password);
  resetForm();
  refresh();
}

async function importCSV(){
  const f = document.getElementById('csvFile').files[0];
  if(!f){ toast('Choose a CSV first'); return; }
  const text = await f.text();
  const out = await api('import_csv', {csv:text});
  toast('Imported '+(out.imported||0)+' users');
  refresh();
}

refresh(); // initial
</script>
</body>
</html>
