<?php
/**
 * Partial: header.php
 * ------------------------------------------------------------
 * Opens the HTML document, loads Bootstrap CSS + app CSS, and
 * starts the <body>. Every page should include this near the top.
 *
 * Available variables (from View::defaultData):
 *   $title, $appName, $e(), $asset(), $url(), $auth, $flash, $csrfField
 * ------------------------------------------------------------
 */
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="csrf-token" content="<?= $e(\App\Core\Csrf::token()) ?>">
    <title><?= $e($title) ?> &middot; <?= $e($appName) ?></title>

    <link rel="stylesheet" href="<?= $e($asset('vendor/bootstrap/css/bootstrap.min.css')) ?>">
    <link rel="stylesheet" href="<?= $e($asset('css/app.css')) ?>">
</head>
<body class="bg-body-tertiary">
<?php
    // Navbar is rendered separately so guest pages (login) can choose to skip it.
    // Default: show it whenever the user is logged in.
    if (!empty($auth)) {
        include APP_BASE . '/app/Views/partials/navbar.php';
    }
?>
<main class="container py-4">
    <?php if (!empty($flash)): ?>
        <?php foreach ($flash as $f):
            $type = $f['type'];
            $cls  = match ($type) {
                'success' => 'success',
                'error'   => 'danger',
                'warning' => 'warning',
                default   => 'info',
            };
        ?>
            <div class="alert alert-<?= $e($cls) ?> alert-dismissible fade show" role="alert">
                <?= $e($f['message']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
