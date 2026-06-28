<?php
/** @var string $content */
use App\Helpers\Auth;
use App\Helpers\Config;
use App\Helpers\Csrf;
use App\Helpers\View;

$appName = Config::get('app.name', 'Student Information System');
$title   = $data['title'] ?? $appName;

// Consume flash message once
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
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

<nav class="navbar navbar-expand-lg navbar-dark bg-primary">
    <div class="container">
        <a class="navbar-brand" href="/dashboard"><?= htmlspecialchars($appName, ENT_QUOTES) ?></a>

        <?php if (Auth::check()): ?>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav"
                aria-controls="mainNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="mainNav">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item">
                    <a class="nav-link" href="/dashboard">Dashboard</a>
                </li>

                <?php if (Auth::role() === 'student'): ?>
                <li class="nav-item">
                    <a class="nav-link" href="/student/form">My Form</a>
                </li>
                <?php endif; ?>

                <?php if (in_array(Auth::role(), ['staff', 'dept_admin', 'institution_admin'], true)): ?>
                <li class="nav-item">
                    <a class="nav-link" href="/onboarding">Students</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="/enrolment">Enrolment Numbers</a>
                </li>
                <?php endif; ?>

                <?php if (Auth::role() === 'institution_admin'): ?>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="summaryDropdown"
                       role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        Summaries
                    </a>
                    <ul class="dropdown-menu" aria-labelledby="summaryDropdown">
                        <li><a class="dropdown-item" href="/onboarding/summary">Onboarding Summary</a></li>
                        <li><a class="dropdown-item" href="/enrolment/summary">Enrolment Summary</a></li>
                    </ul>
                </li>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="masterDataDropdown"
                       role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        Master Data
                    </a>
                    <ul class="dropdown-menu" aria-labelledby="masterDataDropdown">
                        <li><a class="dropdown-item" href="/master-data/departments">Departments</a></li>
                        <li><a class="dropdown-item" href="/master-data/geography">Geography</a></li>
                        <li><a class="dropdown-item" href="/master-data/option-lists">Option Lists</a></li>
                    </ul>
                </li>
                <?php endif; ?>
            </ul>

            <div class="d-flex align-items-center">
                <form method="POST" action="/logout" class="m-0">
                    <?= Csrf::field() ?>
                    <button type="submit" class="btn btn-outline-light btn-sm">Logout</button>
                </form>
            </div>
        </div>
        <?php endif; ?>
    </div>
</nav>

<main class="container py-4">
    <?php if ($flash): ?>
    <div class="alert alert-<?= htmlspecialchars($flash['type'], ENT_QUOTES) ?> alert-dismissible fade show" role="alert">
        <?= htmlspecialchars($flash['message'], ENT_QUOTES) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php endif; ?>

    <?= $content ?>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
