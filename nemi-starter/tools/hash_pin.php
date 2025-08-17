<?php
if ($argc < 2) { echo "Usage: php hash_pin.php <PIN>\n"; exit; }
echo password_hash($argv[1], PASSWORD_DEFAULT) . "\n";
?>