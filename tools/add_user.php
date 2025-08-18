<?php
// /tools/add_user.php  — quick admin helper for JSON-based users
// Usage examples (replace KEY):
//   https://nemi.buyingct.com/tools/add_user.php?key=KEY&email=help@buyingct.com&id=u_admin
//   https://nemi.buyingct.com/tools/add_user.php?key=KEY&email=v.rocharealtor@gmail.com&phone=%2B12035550123
//
// TIP: put this behind a random KEY, or restrict by IP in Nginx, or delete after use.

declare(strict_types=1);

// ---------- simple protection ----------
$ADMIN_KEY = 'CHANGE_ME_TO_A_LONG_RANDOM_STRING';   // <-- change this!
if (!isset($_GET['key']) || $_GET['key'] !== $ADMIN_KEY) {
  http_response_code(403);
  echo "Forbidden. Add ?key=YOUR_SECRET\n";
  exit;
}

// ---------- paths ----------
$base = dirname(__DIR__);
$usrPath = $base . '/data/users.json';
$idxPath = $base . '/data/user_index.json';

// ---------- load stores ----------
$users = file_exists($usrPath) ? json_decode(file_get_contents($usrPath), true) : [];
$index = file_exists($idxPath) ? json_decode(file_get_contents($idxPath), true) : [];
if (!is_array($users)) $users = [];
if (!is_array($index)) $index = [];

// ---------- inputs ----------
$email = strtolower(trim($_GET['email'] ?? ''));
$phone = trim($_GET['phone'] ?? '');           // E.164 preferred, e.g. +12035550123
$id    = trim($_GET['id'] ?? '');

// ---------- validation ----------
if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
  http_response_code(400);
  echo "Provide a valid ?email=\n";
  exit;
}
if (!$id) $id = 'u_' . bin2hex(random_bytes(4));

// ---------- upsert ----------
$users[$id] = $users[$id] ?? [
  'email'  => $email,
  'phone'  => $phone,
  'devices'=> [],
  'otp'    => ['code'=>null,'expires_at'=>0,'for_device'=>null],
  'reset'  => ['token'=>null,'expires_at'=>0]
];

// keep latest email/phone if you run it again
$users[$id]['email'] = $email;
if ($phone !== '') $users[$id]['phone'] = $phone;

// index keys (lowercase!)
$index['email:' . $email] = $id;
if ($phone !== '') $index['phone:' . $phone] = $id;

// ---------- save ----------
file_put_contents($usrPath, json_encode($users, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
file_put_contents($idxPath, json_encode($index, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

// ---------- perms (best effort) ----------
@chown($usrPath, 'www-data'); @chgrp($usrPath, 'www-data'); @chmod($usrPath, 0660);
@chown($idxPath, 'www-data'); @chgrp($idxPath, 'www-data'); @chmod($idxPath, 0660);

header('Content-Type: text/plain; charset=utf-8');
echo "OK: added/updated user\n";
echo "  id:    $id\n";
echo "  email: $email\n";
if ($phone !== '') echo "  phone: $phone\n";
