<?php
echo '<pre>';
$path = __DIR__ . '/../data';
echo "PATH: $path\n\n";

if (!is_dir($path)) {
    echo "DATA FOLDER DOES NOT EXIST\n";
    exit;
}

print_r(scandir($path));