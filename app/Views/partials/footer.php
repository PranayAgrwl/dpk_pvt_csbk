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

<!-- <footer class="border-top bg-white py-3 mt-auto">
    <div class="container text-center text-muted small">
        &copy; <?= $e(date('Y')) ?> <?= $e($appName) ?> &mdash; built with PHP, MySQL, Bootstrap &amp; jQuery.
    </div>
</footer> -->

<script src="<?= $e($asset('vendor/jquery/jquery-3.7.1.min.js')) ?>"></script>
<script src="<?= $e($asset('vendor/bootstrap/js/bootstrap.bundle.min.js')) ?>"></script>
<?php
// Optional per-page extras. A view (or its controller) can set
// $extraScripts = ['js/trx-combobox.js', 'vendor/sortablejs/Sortable.min.js']
// to inject additional <script> tags here — AFTER jQuery + Bootstrap have
// loaded, but BEFORE app.js + any inline DOMContentLoaded handlers run.
if (!empty($extraScripts) && is_array($extraScripts)):
    foreach ($extraScripts as $src): ?>
    <script src="<?= $e($asset($src)) ?>"></script>
<?php endforeach; endif; ?>
<script src="<?= $e($asset('js/app.js')) ?>"></script>
</body>
</html>
