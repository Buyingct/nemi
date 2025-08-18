<?php
// Clears session but keeps the device cookie so /app/timeline.php will ask for PIN
session_start();
session_unset();
session_destroy();
header('Location: /app/timeline.php');
exit;
