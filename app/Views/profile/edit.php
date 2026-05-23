<?php
/**
 * View: profile/edit.php
 * ------------------------------------------------------------
 * Form to edit current user's name/username and optionally change password.
 * The "current password" field is required to make any change.
 *
 * Locals provided by ProfileController::edit():
 *   $user    The fresh user row from DB
 *   $errors  Validation errors keyed by field
 * ------------------------------------------------------------
 */
include APP_BASE . '/app/Views/partials/header.php';

$old      = \App\Core\Session::getFlash('old', []);
$nameVal  = (string) ($old['name']     ?? $user['name']     ?? '');
$userVal  = (string) ($old['username'] ?? $user['username'] ?? '');
$err      = static fn(string $k) => $errors[$k] ?? [];
?>

<div class="row justify-content-center">
    <div class="col-lg-7">
        <h1 class="h3 mb-4">Edit Profile</h1>

        <div class="card shadow-sm border-0">
            <div class="card-body p-4">
                <form method="POST" action="<?= $e($url('/profile')) ?>" autocomplete="off" novalidate>
                    <?= $csrfField ?>

                    <h6 class="text-uppercase text-muted small mb-3">Account details</h6>

                    <div class="mb-3">
                        <label for="name" class="form-label">Display name</label>
                        <input type="text"
                               class="form-control <?= $err('name') ? 'is-invalid' : '' ?>"
                               id="name" name="name" value="<?= $e($nameVal) ?>"
                               maxlength="100" required>
                        <?php foreach ($err('name') as $e1): ?>
                            <div class="invalid-feedback"><?= $e($e1) ?></div>
                        <?php endforeach; ?>
                    </div>

                    <div class="mb-3">
                        <label for="username" class="form-label">Username</label>
                        <input type="text"
                               class="form-control <?= $err('username') ? 'is-invalid' : '' ?>"
                               id="username" name="username" value="<?= $e($userVal) ?>"
                               minlength="3" maxlength="50" pattern="[A-Za-z0-9_]+" required>
                        <div class="form-text">Letters, digits and underscores only.</div>
                        <?php foreach ($err('username') as $e1): ?>
                            <div class="invalid-feedback"><?= $e($e1) ?></div>
                        <?php endforeach; ?>
                    </div>

                    <hr class="my-4">

                    <h6 class="text-uppercase text-muted small mb-3">Security</h6>

                    <div class="mb-3">
                        <label for="current_password" class="form-label">Current password <span class="text-danger">*</span></label>
                        <input type="password"
                               class="form-control <?= $err('current_password') ? 'is-invalid' : '' ?>"
                               id="current_password" name="current_password"
                               autocomplete="current-password" required>
                        <div class="form-text">Required to confirm any change.</div>
                        <?php foreach ($err('current_password') as $e1): ?>
                            <div class="invalid-feedback"><?= $e($e1) ?></div>
                        <?php endforeach; ?>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="new_password" class="form-label">New password</label>
                            <input type="password"
                                   class="form-control <?= $err('new_password') ? 'is-invalid' : '' ?>"
                                   id="new_password" name="new_password"
                                   autocomplete="new-password" minlength="3">
                            <div class="form-text">Leave blank to keep current password.</div>
                            <?php foreach ($err('new_password') as $e1): ?>
                                <div class="invalid-feedback"><?= $e($e1) ?></div>
                            <?php endforeach; ?>
                        </div>
                        <div class="col-md-6">
                            <label for="new_password_confirmation" class="form-label">Confirm new password</label>
                            <input type="password" class="form-control"
                                   id="new_password_confirmation" name="new_password_confirmation"
                                   autocomplete="new-password" minlength="3">
                        </div>
                    </div>

                    <div class="d-flex justify-content-between align-items-center mt-4">
                        <a href="<?= $e($url('/')) ?>" class="text-decoration-none">← Back to dashboard</a>
                        <button type="submit" class="btn btn-primary px-4 fw-semibold">Save changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include APP_BASE . '/app/Views/partials/footer.php'; ?>
