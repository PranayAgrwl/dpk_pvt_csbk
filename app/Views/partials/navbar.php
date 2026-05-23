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

        <?php
            // Active-link highlighting: compare the current request path (with the
            // install base like "/dpk_pvt_csbk" stripped) against each menu item.
            $reqPath = '/' . trim(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/', '/');
            $installBase = rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
            if ($installBase !== '' && $installBase !== '/' && str_starts_with($reqPath, $installBase)) {
                $reqPath = '/' . ltrim(substr($reqPath, strlen($installBase)), '/');
            }
            $isActive = static fn(string $prefix): string =>
                ($prefix === '/' ? $reqPath === '/' : str_starts_with($reqPath, $prefix))
                    ? 'active fw-semibold' : '';
        ?>
        <div class="collapse navbar-collapse" id="mainNav">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item">
                    <a class="nav-link text-white <?= $isActive('/') ?>" href="<?= $e($url('/')) ?>">Home</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link text-white <?= $isActive('/master') ?>" href="<?= $e($url('/master')) ?>">Master</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link text-white <?= $isActive('/trx') ?>" href="<?= $e($url('/trx/create')) ?>">New Trx</a>
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
