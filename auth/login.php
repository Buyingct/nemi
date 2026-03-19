<?php
declare(strict_types=1);

ini_set('display_errors', '1');
error_reporting(E_ALL);

$secureCookie = (
    (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (($_SERVER['SERVER_PORT'] ?? '') == '443')
);

session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'domain'   => '',
    'secure'   => $secureCookie,
    'httponly' => true,
    'samesite' => 'Lax',
]);

session_start();

const MAX_FAILS = 5;
const LOCK_SECONDS = 900;

function fail_and_exit(string $message, string $redirect = '/'): void
{
    $_SESSION['login_error'] = $message;
    header('Location: ' . $redirect);
    exit;
}

function normalize_phone(string $value): string
{
    return preg_replace('/\D+/', '', $value) ?? '';
}

function save_users(string $path, array $users): void
{
    $json = json_encode($users, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        fail_and_exit('Could not save user data.');
    }
    file_put_contents($path, $json, LOCK_EX);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    fail_and_exit('Invalid request method.');
}

$identifier = trim((string)($_POST['identifier'] ?? ''));
$pinRaw     = (string)($_POST['pin'] ?? '');
$deviceId   = trim((string)($_POST['device_id'] ?? ''));

// Clean the PIN aggressively
$pin = preg_replace('/\D+/', '', $pinRaw) ?? '';

if ($identifier === '') {
    fail_and_exit('Please enter your email or phone.');
}

if (strlen($pin) !== 4) {
    fail_and_exit('PIN must be exactly 4 digits.');
}

$usersPath = __DIR__ . '/../auth_storage/users.json';
if (!is_file($usersPath)) {
    fail_and_exit('User database not found.');
}

$users = json_decode((string)file_get_contents($usersPath), true);
if (!is_array($users)) {
    fail_and_exit('User database is invalid.');
}

$uid = null;
$user = null;

$identifierPhone = normalize_phone($identifier);
$identifierEmail = strtolower($identifier);

foreach ($users as $candidateUid => $candidateUser) {
    if (!is_array($candidateUser)) {
        continue;
    }

    $candidateEmail = strtolower((string)($candidateUser['email'] ?? ''));
    $candidatePhone = normalize_phone((string)($candidateUser['phone'] ?? ''));

    if ($candidateEmail !== '' && $candidateEmail === $identifierEmail) {
        $uid = (string)$candidateUid;
        $user = $candidateUser;
        break;
    }

    if ($identifierPhone !== '' && $candidatePhone !== '' && $candidatePhone === $identifierPhone) {
        $uid = (string)$candidateUid;
        $user = $candidateUser;
        break;
    }
}

if ($uid === null || !is_array($user)) {
    fail_and_exit('Account not found.');
}

$email = (string)($user['email'] ?? '');
$role  = strtolower((string)($user['role'] ?? ''));

if ($role === '') {
    fail_and_exit('This account does not have a role assigned.');
}

$devices = $user['devices'] ?? [];
if (!is_array($devices) || empty($devices)) {
    fail_and_exit('No registered device found for this account.');
}

if ($deviceId !== '' && isset($devices[$deviceId]) && is_array($devices[$deviceId])) {
    $device = $devices[$deviceId];
} else {
    $deviceId = (string)array_key_first($devices);
    $device = $devices[$deviceId] ?? null;
}

if (!is_array($device)) {
    fail_and_exit('Device not found.');
}

$pinHash     = (string)($device['pin_hash'] ?? '');
$lockedUntil = (int)($device['locked_until'] ?? 0);
$failCount   = (int)($device['fail_count'] ?? 0);

if ($pinHash === '') {
    fail_and_exit('This device does not have a PIN configured.');
}

$now = time();

if ($lockedUntil > $now) {
    $minutesLeft = (int)ceil(($lockedUntil - $now) / 60);
    fail_and_exit('Too many attempts. Try again in ' . $minutesLeft . ' minute(s).');
}

if (!password_verify($pin, $pinHash)) {
    $failCount++;
    $users[$uid]['devices'][$deviceId]['fail_count'] = $failCount;

    if ($failCount >= MAX_FAILS) {
        $users[$uid]['devices'][$deviceId]['locked_until'] = $now + LOCK_SECONDS;
        $users[$uid]['devices'][$deviceId]['fail_count'] = 0;
    }

    save_users($usersPath, $users);
    fail_and_exit('Invalid PIN.');
}


$users[$uid]['devices'][$deviceId]['fail_count'] = 0;
$users[$uid]['devices'][$deviceId]['locked_until'] = 0;
save_users($usersPath, $users);


session_regenerate_id(true);

$_SESSION['uid']       = $uid;
$_SESSION['email']     = $email;
$_SESSION['role']      = $role;
$_SESSION['device_id'] = $deviceId;
$_SESSION['name']      = preg_replace('/@.*$/', '', $email);

header('Location: /auth/start.php');
exit;