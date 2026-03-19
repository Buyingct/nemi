<?php
declare(strict_types=1);

session_start();

if (empty($_SESSION['uid'])) {
    header('Location: /');
    exit;
}

$caseId = trim((string)($_GET['case'] ?? ''));
if ($caseId === '') {
    echo 'Missing case.';
    exit;
}

$caseFile = __DIR__ . '/../../case_storage/' . basename($caseId) . '.json';
if (!is_file($caseFile) || !is_readable($caseFile)) {
    echo 'Case not found.';
    exit;
}

$case = json_decode((string)file_get_contents($caseFile), true) ?: [];
$home = $case['home_design'] ?? [];

$modelUrl = (string)($home['model'] ?? '');
$title    = (string)($home['title'] ?? 'Your Home Design');

if ($modelUrl === '') {
    echo 'No model assigned to this case yet.';
    exit;
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></title>
  <script type="module" src="https://ajax.googleapis.com/ajax/libs/model-viewer/3.3.0/model-viewer.min.js"></script>
  <style>
    body{margin:0;font-family:system-ui,-apple-system,Segoe UI,Roboto,sans-serif;background:#f8fafc;color:#0f172a}
    .wrap{max-width:1200px;margin:0 auto;padding:24px}
    .topbar{display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:20px}
    .btn{display:inline-flex;align-items:center;justify-content:center;padding:10px 14px;border-radius:10px;background:#1e293b;color:#fff;text-decoration:none;font-weight:600}
    .card{background:#fff;border:1px solid #cbd5e1;border-radius:18px;overflow:hidden;box-shadow:0 10px 30px rgba(15,23,42,.08)}
    model-viewer{width:100%;height:72vh;background:#eef2f7}
    .meta{padding:16px 18px;border-top:1px solid #e2e8f0}
    .meta h2{margin:0 0 8px;font-size:20px}
    .meta p{margin:0;color:#475569}
  </style>
</head>
<body>
  <div class="wrap">
    <div class="topbar">
      <h1 style="margin:0;font-size:28px;"><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></h1>
      <a class="btn" href="/app/timeline.php?case=<?= urlencode($caseId) ?>">Back to Timeline</a>
    </div>

    <div class="card">
      <model-viewer
  src="<?= htmlspecialchars($modelUrl) ?>"
  camera-controls
  auto-rotate
  camera-orbit="0deg 75deg 2.5m"
  field-of-view="30deg"
  exposure="1.2"
  shadow-intensity="1"
  shadow-softness="0.8"
  environment-image="neutral"
  skybox-image="https://modelviewer.dev/shared-assets/environments/aircraft_workshop_01_1k.hdr"
  style="width:100%; height:80vh;">
</model-viewer>

      <div class="meta">
        <h2>Home Preview</h2>
        <p>This is your assigned home model. Finish selections and customization tools will be added here next.</p>
      </div>
    </div>
  </div>
</body>
</html>