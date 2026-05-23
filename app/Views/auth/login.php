<?php
/**
 * View: auth/login.php
 * ------------------------------------------------------------
 * Login form. No navbar (since the user isn't logged in yet).
 *
 * Available locals:
 *   $errors = ['field' => ['error1', ...]] (from flash)
 *   $csrfField (hidden CSRF input)
 * ------------------------------------------------------------
 */
include APP_BASE . '/app/Views/partials/header.php';

// "Old" values flashed back from the previous request (so we can repopulate fields).
$old      = \App\Core\Session::getFlash('old', []);
$oldUser  = (string) ($old['username'] ?? '');
$userErrs = $errors['username'] ?? [];
$passErrs = $errors['password'] ?? [];
?>

<div class="row justify-content-center">
    <div class="col-sm-10 col-md-8 col-lg-5">
        <div class="text-center mb-4">
            <h1 class="h3 fw-bold mb-1"><?= $e($appName) ?></h1>
            <p class="text-muted mb-0">Sign in to continue</p>
        </div>

        <div class="card shadow-sm border-0">
            <div class="card-body p-4">
                <form method="POST" action="<?= $e($url('/login')) ?>" novalidate>
                    <?= $csrfField ?>

                    <div class="mb-3">
                        <label for="username" class="form-label">Username</label>
                        <input type="text"
                               class="form-control <?= $userErrs ? 'is-invalid' : '' ?>"
                               id="username" name="username"
                               value="<?= $e($oldUser) ?>"
                               autocomplete="username" required autofocus>
                        <?php foreach ($userErrs as $err): ?>
                            <div class="invalid-feedback"><?= $e($err) ?></div>
                        <?php endforeach; ?>
                    </div>

                    <div class="mb-3">
                        <label for="password" class="form-label">Password</label>
                        <input type="password"
                               class="form-control <?= $passErrs ? 'is-invalid' : '' ?>"
                               id="password" name="password"
                               autocomplete="current-password" required>
                        <?php foreach ($passErrs as $err): ?>
                            <div class="invalid-feedback"><?= $e($err) ?></div>
                        <?php endforeach; ?>
                    </div>

                    <button type="submit" class="btn btn-primary w-100 fw-semibold">
                        Sign in
                    </button>
                </form>
            </div>
        </div>

        <p class="text-center text-muted small mt-4 mb-0">
            Default admin &mdash; <code>admin</code> / <code>321</code>
        </p>
    </div>
</div>

<?php include APP_BASE . '/app/Views/partials/footer.php'; ?>
