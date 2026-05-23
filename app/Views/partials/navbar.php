<?php
/**
 * Partial: navbar.php
 * ------------------------------------------------------------
 * Top navigation. Only included when a user is authenticated.
 *
 * Logout uses a POST form (CSRF-protected). No GET-based logouts.
 * ------------------------------------------------------------
 */
?>
<!-- <nav class="navbar navbar-expand-lg bg-primary" data-bs-theme="dark"> -->
<nav class="navbar navbar-expand-lg bg-primary">
    <div class="container">
        <!-- <a class="navbar-brand fw-semibold" href="<?= $e($url('/')) ?>">
            <?= $e($appName) ?>
        </a> -->

        <button class="navbar-toggler" type="button"
                data-bs-toggle="collapse" data-bs-target="#mainNav"
                aria-controls="mainNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="mainNav">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item">
                    <a class="nav-link text-white" href="<?= $e($url('/')) ?>">Home</a>
                </li>
            </ul>

            <ul class="navbar-nav ms-auto">
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle text-white" href="#" role="button"
                       data-bs-toggle="dropdown" aria-expanded="false">
                        <?= $e($auth['name'] ?? 'Account') ?>
                        <!-- <span class="badge bg-light text-primary ms-1">@<?= $e($auth['username'] ?? '') ?></span> -->
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                    <!-- <ul class="dropdown-menu dropdown-menu-end bg-white border shadow-sm"> -->
                        <li>
                            <a class="dropdown-item" href="<?= $e($url('/profile')) ?>">Edit Profile</a>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <form method="POST" action="<?= $e($url('/logout')) ?>" class="m-0">
                                <?= $csrfField ?>
                                <button type="submit" class="dropdown-item text-danger">
                                    Logout
                                </button>
                            </form>
                        </li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav>
