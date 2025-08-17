<?php
session_start();
if (!isset($_SESSION['client_id'])) {
  header('Location: ../auth/signin.html');
  exit;
}
$clientId = $_SESSION['client_id'];
$dataFile = __DIR__ . '/../data/clients/' . $clientId . '.json';
$data = json_decode(file_get_contents($dataFile), true);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Nemi - Timeline</title>
  <link rel="stylesheet" href="../css/site.css">
  <script src="../js/timeline.js" defer></script>
</head>
<body>
  <header class="site-header">
    <div class="head-inner">
      <img src="../assets/svgs/nemi-logo.svg" class="logo" alt="Nemi Logo">
      <nav class="nav">
        <a href="#" id="menu-messages">Messages</a>
        <a href="#" id="menu-docs">Docs</a>
        <a href="../auth/signout.php">Log out</a>
      </nav>
    </div>
  </header>
  <main class="timeline-wrap">
    <object type="image/svg+xml" data="../assets/svgs/timeline.svg" class="timeline-svg"></object>
  </main>
</body>
</html>