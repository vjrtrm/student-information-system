<?php
/** @var string $content */
use App\Helpers\Config;
$appName = Config::get('app.name', 'Student Information System');
$title = $data['title'] ?? $appName;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title, ENT_QUOTES) ?> · <?= htmlspecialchars($appName, ENT_QUOTES) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="/assets/css/app.css" rel="stylesheet">
</head>
<body class="bg-light">
<nav class="navbar navbar-dark bg-primary">
    <div class="container">
        <span class="navbar-brand mb-0 h1"><?= htmlspecialchars($appName, ENT_QUOTES) ?></span>
    </div>
</nav>
<main class="container py-4">
    <?= $content ?>
</main>
</body>
</html>
