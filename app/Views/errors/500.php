<?php
/**
 * View: errors/500.php
 * ------------------------------------------------------------
 * Generic server error page (used when APP_DEBUG=false).
 * ------------------------------------------------------------
 */
$title = 'Server error';
include APP_BASE . '/app/Views/partials/header.php';
?>

<div class="row justify-content-center text-center">
    <div class="col-md-8 col-lg-6 py-5">
        <p class="text-danger fw-bold display-1 mb-2">500</p>
        <h1 class="h3 fw-bold mb-3">Something went wrong</h1>
        <p class="text-muted mb-4">
            An unexpected error occurred. The issue has been logged and we'll look into it.
        </p>
        <a href="<?= $e($url('/')) ?>" class="btn btn-primary px-4">Back to safety</a>
    </div>
</div>

<?php include APP_BASE . '/app/Views/partials/footer.php'; ?>
