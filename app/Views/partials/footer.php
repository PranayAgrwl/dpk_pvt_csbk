<?php
/**
 * Partial: footer.php
 * ------------------------------------------------------------
 * Closes the page, loads jQuery + Bootstrap JS (bundle) + app JS.
 * Bootstrap bundle includes Popper, so no separate Popper file.
 * ------------------------------------------------------------
 */
?>
</main>

<footer class="border-top bg-white py-3 mt-auto">
    <div class="container text-center text-muted small">
        <!-- &copy; <?= $e(date('Y')) ?> <?= $e($appName) ?> &mdash; built with PHP, MySQL, Bootstrap &amp; jQuery. -->
    </div>
</footer>

<script src="<?= $e($asset('vendor/jquery/jquery-3.7.1.min.js')) ?>"></script>
<script src="<?= $e($asset('vendor/bootstrap/js/bootstrap.bundle.min.js')) ?>"></script>
<script src="<?= $e($asset('js/app.js')) ?>"></script>
</body>
</html>
