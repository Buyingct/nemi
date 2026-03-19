<?php
declare(strict_types=1);

session_start();

ini_set('display_errors', '1');
error_reporting(E_ALL);

$usrPath = __DIR__ . '/../auth_storage/users.json';

function fail_and_exit(string $message, int $status = 400): void
{
    http_response_code($status);
    echo $message;
    exit;
}

function normalize_phone(string $value): string
{
    return preg_replace('/\D+/', '', $value) ?? '';
}

function load_json_array(string $path): array
{
    if (!is_file($path)) {
        return [];
    }

    $data = json_decode((string)file_get_contents($path), true);
    return is_array($data) ? $data : [];
}

function save_json_array(string $path, array $data): void
{
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        fail_and_exit('Could not encode JSON.', 500);
    }

    if (file_put_contents($path, $json, LOCK_EX) === false) {
        fail_and_exit('Could not save file.', 500);
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    fail_and_exit('Invalid request method.', 405);
}

/**
 * Expected fields:
 * - email
 * - phone (optional but recommended)
 * - role (buyer, seller, realtor, lender, attorney, admin)
 * - pin (4 digits)
 * - device_name (optional; defaults to Web Browser)
 */
$email      = strtolower(trim((string)($_POST['email'] ?? '')));
$phoneRaw   = trim((string)($_POST['phone'] ?? ''));
$phone      = normalize_phone($phoneRaw);
$role       = trim((string)($_POST['role'] ?? 'buyer'));
$pin        = trim((string)($_POST['pin'] ?? ''));
$deviceName = trim((string)($_POST['device_name'] ?? 'Web Browser'));

if ($email === '') {
    fail_and_exit('Email is required.');
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fail_and_exit('Invalid email.');
}

if ($pin === '') {
    fail_and_exit('PIN is required.');
}

if (!preg_match('/^\d{4}$/', $pin)) {
    fail_and_exit('PIN must be exactly 4 digits.');
}

$allowedRoles = ['admin', 'realtor', 'buyer', 'seller', 'lender', 'attorney'];
if (!in_array($role, $allowedRoles, true)) {
    $role = 'buyer';
}

$users = load_json_array($usrPath);

/**
 * Prevent duplicate email or duplicate phone.
 */
foreach ($users as $existingUid => $existingUser) {
    if (!is_array($existingUser)) {
        continue;
    }

    $existingEmail = strtolower((string)($existingUser['email'] ?? ''));
    $existingPhone = normalize_phone((string)($existingUser['phone'] ?? ''));

    if ($existingEmail !== '' && $existingEmail === $email) {
        fail_and_exit('Email already registered.', 409);
    }

    if ($phone !== '' && $existingPhone !== '' && $existingPhone === $phone) {
        fail_and_exit('Phone already registered.', 409);
    }
}

/**
 * Create new user and first device
 */
$uid = 'u_' . bin2hex(random_bytes(4));
$deviceId = 'd_' . bin2hex(random_bytes(4));
$now = time();

$users[$uid] = [
    'email' => $email,
    'phone' => $phoneRaw,
    'role'  => $role,
    'devices' => [
        $deviceId => [
            'name'         => $deviceName !== '' ? $deviceName : 'Web Browser',
            'pin_hash'     => password_hash($pin, PASSWORD_DEFAULT),
            'created_at'   => $now,
            'locked_until' => 0,
            'fail_count'   => 0
        ]
    ],
    'otp' => [
        'code'       => null,
        'expires_at' => 0,
        'for_device' => null
    ],
    'reset' => [
        'token'      => null,
        'expires_at' => 0
    ]
];

save_json_array($usrPath, $users);

/**
 * Log them in immediately
 */
session_regenerate_id(true);

$_SESSION['uid']       = $uid;
$_SESSION['email']     = $email;
$_SESSION['role']      = $role;
$_SESSION['device_id'] = $deviceId;
$_SESSION['name']      = preg_replace('/@.*$/', '', $email);

/**
 * Redirect by role
 */
switch ($role) {
    case 'admin':
        header('Location: /admin/users.php');
        break;

    case 'realtor':
        header('Location: /app/realtor_portal.php');
        break;

    case 'buyer':
    case 'seller':
        header('Location: /app/timeline.php');
        break;

    case 'lender':
        header('Location: /tools/dashboard/lender.html');
        break;

    case 'attorney':
        header('Location: /tools/dashboard/attorney.html');
        break;

    default:
        header('Location: /tools/dashboard/index.html');
        break;
}

exit;
