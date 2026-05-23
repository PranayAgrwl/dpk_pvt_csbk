<?php
/**
 * View: errors/404.php
 * ------------------------------------------------------------
 * 404 - Page Not Found. Rendered by Response::abort(404).
 *
 * NOTE: this view may be rendered for guest visitors too, so we
 * include the header/footer but they will skip the navbar
 * automatically when $auth is null.
 * ------------------------------------------------------------
 */
$title = 'Page not found';
include APP_BASE . '/app/Views/partials/header.php';
?>

<div class="row justify-content-center text-center">
    <div class="col-md-8 col-lg-6 py-5">
        <p class="text-primary fw-bold display-1 mb-2">404</p>
        <h1 class="h3 fw-bold mb-3">We couldn't find that page</h1>
        <p class="text-muted mb-4">
            The page you're looking for doesn't exist, was moved, or never made it to production.
        </p>
        <a href="<?= $e($url('/')) ?>" class="btn btn-primary px-4">Go to dashboard</a>
    </div>
</div>

<?php include APP_BASE . '/app/Views/partials/footer.php'; ?>
