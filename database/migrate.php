<?php
// Simple migration runner: executes every .sql file in migrations/ in order.
// Usage: php database/migrate.php
require __DIR__ . '/../vendor/autoload.php';

use App\Helpers\Config;
use App\Helpers\Db;

Config::setPath(__DIR__ . '/../config');

$dir = __DIR__ . '/migrations';
$files = glob($dir . '/*.sql');
sort($files);

$pdo = Db::conn();
foreach ($files as $file) {
    $sql = file_get_contents($file);
    echo "Running " . basename($file) . " ... ";
    try {
        $pdo->exec($sql);
        echo "ok\n";
    } catch (\Throwable $e) {
        echo "FAILED: " . $e->getMessage() . "\n";
        exit(1);
    }
}
echo "Migrations complete.\n";
