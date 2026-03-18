<?php
$path = __DIR__ . '/../auth_storage/users.json';
echo 'PATH: ' . $path . '<br>';
echo 'EXISTS: ' . (file_exists($path) ? 'YES' : 'NO');