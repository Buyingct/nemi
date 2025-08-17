<?php
session_start();
if (!isset($_SESSION['client_id'])) { header('Location: ../auth/signin.html'); exit; }

$clientId = $_SESSION['client_id'];
$idx = json_decode(file_get_contents(__DIR__ . '/../data/clients.json'), true);
$client = $idx[$clientId];

$svgPath  = __DIR__ . '/../' . $client['svg'];                // assets/svgs/timeline.svg
$dataPath = __DIR__ . '/../' . $client['timeline_data'];      // data/clients/12345.json

$timeline = json_decode(file_get_contents($dataPath), true);
$states = $timeline['states'] ?? [];
$notes  = $timeline['notes'] ?? [];
$svg    = file_get_contents($svgPath);
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="../css/site.css">
  <link rel="stylesheet" href="../css/nemi_timeline.css">
  <title>Nemi • Timeline</title>
</head>
<body>
<header class="site-header">
  <div class="head-inner">
    <img src="../assets/svgs/nemi-logo.svg" class="logo" alt="Nemi">
    <nav class="nav">
      <a href="#" id="menu-messages" class="btn ghost">Messages</a>
      <a href="#" id="menu-docs" class="btn ghost">Docs</a>
      <a href="../auth/signout.php" class="btn">Log out</a>
    </nav>
  </div>
</header>

<main>
  <section id="timeline-wrap" style="position:relative">
    <?php echo $svg; ?>  <!-- inline SVG so we can add click handlers -->
  </section>

  <!-- bottom sheet -->
  <div id="nt_sheet" aria-hidden="true">
    <div class="grab"></div>
    <h3 id="nt_title">Step</h3>
    <div class="meta" id="nt_meta">—</div>
    <div id="nt_body">—</div>
    <div class="row" style="margin-top:12px;">
      <button class="btn" id="nt_close">Close</button>
      <button class="btn primary" id="nt_done">Mark Done</button>
    </div>
  </div>
</main>

<script>
  window.NEMI = {
    clientId: "<?php echo htmlspecialchars($clientId); ?>",
    states: <?php echo json_encode($states); ?>,
    notes:  <?php echo json_encode($notes); ?>
  };
</script>
<script src="../js/timeline.js" defer></script>
</body>
</html>
