/* ============================================================
   app.js — site-wide custom scripts.
   Runs AFTER jQuery and Bootstrap bundle have loaded.
   ============================================================ */

(function ($) {
    'use strict';

    // Auto-dismiss flash alerts after a few seconds (except errors).
    $(function () {
        $('.alert').each(function () {
            var $a = $(this);
            if ($a.hasClass('alert-danger')) { return; }   // keep errors on screen
            setTimeout(function () {
                var instance = bootstrap.Alert.getOrCreateInstance($a[0]);
                instance.close();
            }, 4000);
        });
    });

    // Attach CSRF token to AJAX requests automatically.
    var token = $('meta[name="csrf-token"]').attr('content');
    if (token) {
        $.ajaxSetup({
            headers: { 'X-CSRF-TOKEN': token }
        });
    }
})(jQuery);
