<?php
/**
 * View: home/index.php
 * ------------------------------------------------------------
 * Authenticated landing page.
 * Per the outline bible: "hello world".
 * ------------------------------------------------------------
 */
include APP_BASE . '/app/Views/partials/header.php';
?>

<h1>Hello World</h1>

<!-- <div class="row justify-content-center">
    <div class="col-lg-9">
        <div class="card shadow-sm border-0">
            <div class="card-body p-4 p-md-5">
                <p class="text-uppercase text-primary fw-semibold small mb-2">Dashboard</p>
                <h1 class="display-6 fw-bold mb-3">Hello, World!</h1>
                <p class="lead mb-4">
                    Welcome, <span class="fw-semibold"><?= $e($auth['name']) ?></span>.
                    You're signed in as <code>@<?= $e($auth['username']) ?></code>.
                </p>

                <hr class="my-4">

                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="border rounded p-3 h-100">
                            <h6 class="text-muted text-uppercase small mb-2">Account</h6>
                            <p class="mb-1"><strong>Name:</strong> <?= $e($auth['name']) ?></p>
                            <p class="mb-1"><strong>Username:</strong> <?= $e($auth['username']) ?></p>
                            <p class="mb-0 small text-muted">
                                Member since <?= $e(substr((string) ($auth['created_at'] ?? ''), 0, 10)) ?>
                            </p>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="border rounded p-3 h-100">
                            <h6 class="text-muted text-uppercase small mb-2">Next steps</h6>
                            <ul class="mb-0 ps-3">
                                <li><a href="<?= $e($url('/profile')) ?>">Edit your profile / change password</a></li>
                                <li>Build out the cashbook (transactions, accounts, reports)</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div> -->

<?php include APP_BASE . '/app/Views/partials/footer.php'; ?>
