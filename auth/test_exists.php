<?php
$path = __DIR__ . '/../data/users.json';
echo 'PATH: ' . $path . '<br>';
echo 'EXISTS: ' . (file_exists($path) ? 'YES' : 'NO');