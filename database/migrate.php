<?php
/**
 * Migration runner — works from browser (XAMPP) or CLI.
 * Edit the DB_ constants below to match your local credentials.
 */

// ── Database credentials ──────────────────────────────────────────────────────
define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
define('DB_PORT', getenv('DB_PORT') ?: '3306');
define('DB_NAME', getenv('DB_NAME') ?: 'sis');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');
// ─────────────────────────────────────────────────────────────────────────────

$isCli = (PHP_SAPI === 'cli');

// HTML header for browser runs
if (!$isCli) {
    echo '<!DOCTYPE html><html><head><meta charset="utf-8">
<title>SIS Migrations</title>
<style>
  body { font-family: monospace; background:#1e1e1e; color:#d4d4d4; padding:2rem; }
  .ok   { color:#4ec94e; }
  .fail { color:#f44747; font-weight:bold; }
  .skip { color:#ce9178; }
  h2    { color:#569cd6; }
</style></head><body>';
    echo '<h2>SIS — Running Migrations</h2><pre>';
}

// Connect directly (no autoloader needed)
try {
    $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', DB_HOST, DB_PORT, DB_NAME);
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    fail('Cannot connect to database: ' . $e->getMessage());
    exit(1);
}

$dir   = __DIR__ . '/migrations';
$files = glob($dir . '/*.sql');
sort($files);

$ok = 0;
$failed = 0;

foreach ($files as $file) {
    $name = basename($file);
    $sql  = file_get_contents($file);
    line("Running {$name} ... ", false);
    try {
        $pdo->exec($sql);
        line('<span class="ok">ok</span>');
        $ok++;
    } catch (Throwable $e) {
        $msg = $e->getMessage();
        // Treat "already exists" as a skip, not a hard failure
        if (stripos($msg, 'already exists') !== false || stripos($msg, 'Duplicate') !== false) {
            line('<span class="skip">skipped (already exists)</span>');
            $ok++;
        } else {
            line('<span class="fail">FAILED: ' . htmlspecialchars($msg) . '</span>');
            $failed++;
        }
    }
}

line('');
if ($failed === 0) {
    line('<span class="ok">✓ All migrations complete (' . $ok . ' files).</span>');
} else {
    line('<span class="fail">✗ ' . $failed . ' migration(s) failed. Fix errors above and re-run.</span>');
}

if (!$isCli) {
    echo '</pre></body></html>';
}

// ── Helpers ───────────────────────────────────────────────────────────────────
function line(string $text, bool $newline = true): void
{
    if (PHP_SAPI === 'cli') {
        echo strip_tags($text) . ($newline ? "\n" : '');
    } else {
        echo $text . ($newline ? "\n" : '');
    }
}

function fail(string $msg): void
{
    if (PHP_SAPI === 'cli') {
        echo "ERROR: {$msg}\n";
    } else {
        echo '<span class="fail">ERROR: ' . htmlspecialchars($msg) . '</span>';
    }
}
