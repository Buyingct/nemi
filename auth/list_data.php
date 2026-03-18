<?php
declare(strict_types=1);

$path = __DIR__ . '/../data';

echo '<pre>';
echo "PATH: $path\n";
echo "IS_DIR: " . (is_dir($path) ? 'YES' : 'NO') . "\n";
echo "IS_READABLE: " . (is_readable($path) ? 'YES' : 'NO') . "\n\n";

$items = @scandir($path);
if ($items === false) {
    echo "SCANDIR FAILED\n";
    exit;
}

echo "CONTENTS:\n";
print_r($items);

echo "\nUSERS EXISTS: " . (file_exists($path . '/users.json') ? 'YES' : 'NO') . "\n";
echo "USER_INDEX EXISTS: " . (file_exists($path . '/user_index.json') ? 'YES' : 'NO') . "\n";