<?php
session_start();

$idxPath = __DIR__.'/../data/user_index.json';
$usrPath = __DIR__.'/../data/users.json';

$email = strtolower(trim($_POST['email'] ?? ''));
$pass  = $_POST['password'] ?? '';

if (!$email || !$pass) { http_response_code(400); echo "Missing fields"; exit; }
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { http_response_code(400); echo "Invalid email"; exit; }
if (strlen($pass) < 8) { http_response_code(400); echo "Password too short"; exit; }

$index = file_exists($idxPath)? json_decode(file_get_contents($idxPath), true): [];
$users = file_exists($usrPath)? json_decode(file_get_contents($usrPath), true): [];

if (!empty($index['email:'.$email])) { http_response_code(409); echo "Email already registered"; exit; }

$uid = 'u_'.bin2hex(random_bytes(4));
$users[$uid] = [
  'email'=>$email,
  'phone'=>'',
  'password_hash'=>password_hash($pass, PASSWORD_DEFAULT),
  'devices'=>[],
  'otp'=>['code'=>null,'expires_at'=>0,'for_device'=>null],
  'reset'=>['token'=>null,'expires_at'=>0]
];
$index['email:'.$email] = $uid;

file_put_contents($usrPath, json_encode($users, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
file_put_contents($idxPath, json_encode($index, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));

$_SESSION['user_id'] = $uid;
header('Location: /app/timeline.php');
exit;
