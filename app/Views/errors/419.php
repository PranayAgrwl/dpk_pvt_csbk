<?php
/**
 * View: errors/419.php
 * ------------------------------------------------------------
 * CSRF token mismatch / expired session page.
 * Same status code Laravel uses for the same condition.
 * ------------------------------------------------------------
 */
$title = 'Page expired';
include APP_BASE . '/app/Views/partials/header.php';
?>

<div class="row justify-content-center text-center">
    <div class="col-md-8 col-lg-6 py-5">
        <p class="text-warning fw-bold display-1 mb-2">419</p>
        <h1 class="h3 fw-bold mb-3">Your session has expired</h1>
        <p class="text-muted mb-4">
            For security reasons we couldn't verify that this request came from you.
            Please reload the page and try again.
        </p>
        <a href="<?= $e($url('/')) ?>" class="btn btn-primary px-4">Go back</a>
    </div>
</div>

<?php include APP_BASE . '/app/Views/partials/footer.php'; ?>
