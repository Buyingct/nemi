<?php
// submit.php — save client requests to data/submissions.csv

// 1) Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  header('Content-Type: application/json');
  echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
  exit;
}

// 2) Read & sanitize inputs (update names to match your form fields)
$name    = trim($_POST['name']    ?? '');
$email   = trim($_POST['email']   ?? '');
$message = trim($_POST['message'] ?? '');

// Minimal validation
if ($name === '' || $email === '') {
  http_response_code(400);
  header('Content-Type: application/json');
  echo json_encode(['ok' => false, 'error' => 'Name and email are required']);
  exit;
}

// 3) Path to the CSV (repo folder: data/submissions.csv)
// NOTE: .gitignore keeps this OUT of GitHub; we’ll also block web access below.
$dir  = __DIR__ . '/data';
$file = $dir . '/submissions.csv';

// 4) Ensure the folder exists
if (!is_dir($dir)) {
  if (!mkdir($dir, 0775, true)) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['ok' => false, 'error' => 'Unable to create data folder']);
    exit;
  }
}

// 5) Open CSV for appending and write the row
$fp = @fopen($file, 'a');
if (!$fp) {
  http_response_code(500);
  header('Content-Type: application/json');
  echo json_encode(['ok' => false, 'error' => 'Unable to open storage']);
  exit;
}

// Add CSV header once (if file newly created)
if (filesize($file) === 0) {
  fputcsv($fp, ['timestamp_utc', 'name', 'email', 'message', 'ip']);
}

$now = gmdate('Y-m-d\TH:i:s\Z');
$ip  = $_SERVER['REMOTE_ADDR'] ?? '';

fputcsv($fp, [$now, $name, $email, $message, $ip]);
fclose($fp);

// 6) Return a friendly JSON response
header('Content-Type: application/json');
echo json_encode(['ok' => true, 'message' => 'Thanks! We’ll be in touch.']);
