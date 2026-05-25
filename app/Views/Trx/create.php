<?php
/**
 * View: app/Views/Trx/create.php
 * ------------------------------------------------------------
 * Cashbook — New Transaction form (CREATE step).
 *
 * Locals provided by TrxController::create():
 *   $masters      array<int,{id,name,station}>  — full master list (alphabetical)
 *   $balances     array<int,float>              — { master_id => current balance }
 *   $nextTrxId    int                           — preview of the trx_id we'll assign
 *   $defaultDate  string                        — YYYY-MM-DD; the LAST trx's date,
 *                                                 or today when the table is empty
 *   $todayDate    string                        — YYYY-MM-DD (server "today"), kept for reference
 *   $errors       validation errors (flashed after a failed POST)
 *   $old          old form values (flashed after a failed POST)
 *
 * Field order (per the trx bible + later UX tweaks):
 *   1. trx_date    autofocused; defaults to last-trx date (same most of the time)
 *   2. master      MS-Access-style combobox; station shown beside
 *   3. balance     read-only; populated when a master is picked
 *   4. cr          credit (one of cr/dr only)
 *   5. dr          debit
 *   6. new balance read-only preview "what the balance WOULD be after save"
 *   7. remark      free text
 *
 * Security:
 *   - CSRF token via $csrfField (the router enforces it on all POSTs).
 *   - All row data is htmlspecialchars'd via $e() before landing in HTML.
 *   - Inputs are server-validated AND sent through PDO prepared statements.
 * ------------------------------------------------------------
 */
include APP_BASE . '/app/Views/partials/header.php';

// Convenience aliases — keeps the template compact.
$base       = $url('/trx');
$err        = static fn(string $k): array => $errors[$k] ?? [];
$hasErrors  = !empty($errors);

// "Old" values: fall back to a sensible default per field.
// Date default = last trx's date (or today when the cashbook is empty).
$oldDate    = (string) ($old['trx_date']  ?? $defaultDate);
$oldMaster  = (string) ($old['master_id'] ?? '');
$oldCr      = (string) ($old['cr']        ?? '');
$oldDr      = (string) ($old['dr']        ?? '');
$oldRemark  = (string) ($old['remark']    ?? '');

// Resolve the "old" master's display name (for restoring the combobox text
// after a validation failure). Empty string when there's no match.
$oldMasterName = '';
if ($oldMaster !== '' && ctype_digit($oldMaster)) {
    foreach ($masters as $m) {
        if ((int) $m['id'] === (int) $oldMaster) {
            $oldMasterName = (string) $m['name'];
            break;
        }
    }
}
?>

<div class="row justify-content-center">
    <div class="col-lg-9">

        <div class="d-flex justify-content-between align-items-end mb-4 flex-wrap gap-2">
            <div>
                <h1 class="h3 fw-bold mb-1">New Transaction</h1>
                <p class="text-muted mb-0 small">
                    Trx&nbsp;# <span class="fw-semibold"><?= (int) $nextTrxId ?></span>
                    &middot; cashbook entry
                </p>
            </div>
            <!-- <a href="<?= $e($url('/master')) ?>" class="text-decoration-none small">Manage masters →</a> -->
        </div>

        <?php if (empty($masters)): ?>
            <!-- No masters → can't make a transaction. Nudge the user to add one. -->
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center py-5">
                    <p class="text-muted mb-3">No masters yet — add one first to start recording transactions.</p>
                    <a href="<?= $e($url('/master')) ?>" class="btn btn-outline-primary btn-sm">Go to Master</a>
                </div>
            </div>
        <?php else: ?>
            <div class="card shadow-sm border-0">
                <div class="card-body p-4">
                    <form id="trxForm" method="POST" action="<?= $e($base . '/store') ?>" autocomplete="off" novalidate>
                        <?= $csrfField ?>

                        <!-- 1) Trx date — first field on the page, autofocused,
                                and part of the normal Tab sequence. Defaults to
                                the date of the last voucher (most data entry has
                                several rows on the same business date). The
                                browser <input type="date"> also exposes the
                                native calendar picker for changing the date. -->
                        <div class="row g-3 align-items-end mb-3">
                            <div class="col-md-4">
                                <label for="trxDate" class="form-label">Date</label>
                                <input type="date"
                                       class="form-control <?= $err('trx_date') ? 'is-invalid' : '' ?>"
                                       id="trxDate" name="trx_date"
                                       value="<?= $e($oldDate) ?>"
                                       required>
                                <?php foreach ($err('trx_date') as $msg): ?>
                                    <div class="invalid-feedback"><?= $e($msg) ?></div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- 2) Master — MS-Access-style combobox.
                                Visible input is text-only; the real id is in the
                                hidden field below it. -->
                        <div class="row g-3 mb-3">
                            <div class="col-md-8">
                                <label for="masterInput" class="form-label">
                                    Master <span class="text-danger">*</span>
                                </label>
                                <div class="trx-combo position-relative">
                                    <input type="text"
                                           class="form-control <?= $err('master_id') ? 'is-invalid' : '' ?>"
                                           id="masterInput"
                                           value="<?= $e($oldMasterName) ?>"
                                           autocomplete="off"
                                           spellcheck="false"
                                           role="combobox"
                                           aria-autocomplete="list"
                                           aria-expanded="false"
                                           aria-controls="masterList"
                                           required autofocus>
                                    <input type="hidden"
                                           id="masterId" name="master_id"
                                           value="<?= $e($oldMaster) ?>">
                                    <ul id="masterList"
                                        class="trx-combo-list list-group position-absolute w-100 shadow-sm"
                                        role="listbox"
                                        hidden></ul>
                                    <?php foreach ($err('master_id') as $msg): ?>
                                        <div class="invalid-feedback d-block"><?= $e($msg) ?></div>
                                    <?php endforeach; ?>
                                </div>
                                <div class="form-text">
                                    <!-- Start typing to filter &middot; Alt+&darr; for full list &middot; Enter to select -->
                                </div>
                            </div>

                            <!-- 3) Current balance — read-only display. -->
                            <div class="col-md-4">
                                <label for="balanceCurrent" class="form-label">Balance</label>
                                <input type="text"
                                       class="form-control text-end"
                                       id="balanceCurrent"
                                       value=""
                                       readonly tabindex="-1"
                                       placeholder="—">
                            </div>
                        </div>

                        <!-- 4) Cr  5) Dr  — exactly one of these per row.
                                A small JS handler disables the other one as soon
                                as one of them gets a value, so the user can't
                                accidentally fill both. -->
                        <div class="row g-3 mb-3">
                            <div class="col-md-4">
                                <label for="crInput" class="form-label">Cr (Credit)</label>
                                <input type="text"
                                       class="form-control text-end <?= $err('cr') ? 'is-invalid' : '' ?>"
                                       id="crInput" name="cr"
                                       value="<?= $e($oldCr) ?>"
                                       inputmode="decimal"
                                       autocomplete="off"
                                       placeholder="0.00">
                                <?php foreach ($err('cr') as $msg): ?>
                                    <div class="invalid-feedback"><?= $e($msg) ?></div>
                                <?php endforeach; ?>
                            </div>
                            <div class="col-md-4">
                                <label for="drInput" class="form-label">Dr (Debit)</label>
                                <input type="text"
                                       class="form-control text-end <?= $err('dr') ? 'is-invalid' : '' ?>"
                                       id="drInput" name="dr"
                                       value="<?= $e($oldDr) ?>"
                                       inputmode="decimal"
                                       autocomplete="off"
                                       placeholder="0.00">
                                <?php foreach ($err('dr') as $msg): ?>
                                    <div class="invalid-feedback"><?= $e($msg) ?></div>
                                <?php endforeach; ?>
                            </div>

                            <!-- 6) Balance preview — what the balance WOULD be if saved. -->
                            <div class="col-md-4">
                                <label for="balanceAfter" class="form-label">Balance after save</label>
                                <input type="text"
                                       class="form-control text-end"
                                       id="balanceAfter"
                                       value=""
                                       readonly tabindex="-1"
                                       placeholder="—">
                            </div>
                        </div>

                        <!-- 7) Remark — free text, optional. -->
                        <div class="mb-4">
                            <label for="remarkInput" class="form-label">Remark</label>
                            <input type="text"
                                   class="form-control <?= $err('remark') ? 'is-invalid' : '' ?>"
                                   id="remarkInput" name="remark"
                                   value="<?= $e($oldRemark) ?>"
                                   maxlength="255">
                            <?php foreach ($err('remark') as $msg): ?>
                                <div class="invalid-feedback"><?= $e($msg) ?></div>
                            <?php endforeach; ?>
                        </div>

                        <div class="d-flex justify-content-between align-items-center">
                            <!-- <a href="<?= $e($url('/')) ?>" class="text-decoration-none small">← Back to dashboard</a> -->
                            <button type="submit" class="btn btn-primary px-4 fw-semibold">Save</button>
                        </div>
                    </form>
                </div>
            </div>
        <?php endif; ?>

    </div>
</div>


<!-- ===== Page-specific JS =====
     Waits for jQuery + Bootstrap + TrxCombobox (all loaded by footer.php,
     which is included AFTER this view). All wiring is deferred to
     DOMContentLoaded. -->
<script>
document.addEventListener('DOMContentLoaded', function () {
    'use strict';

    if (!window.jQuery || !window.TrxCombobox) {
        console.error('Trx create: jQuery or TrxCombobox not loaded — wiring skipped.');
        return;
    }
    var $ = window.jQuery;

    // ---- data from PHP ------------------------------------------------------
    // Full master list (already sorted by name ASC server-side).
    var MASTERS  = <?= json_encode($masters,  JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
    // { master_id => current balance } as plain numbers (dr - cr per master).
    var BALANCES = <?= json_encode((object) $balances, JSON_UNESCAPED_SLASHES) ?>;

    // ---- cached DOM refs ----------------------------------------------------
    var $balCurrent  = $('#balanceCurrent');
    var $balAfter    = $('#balanceAfter');
    var $cr          = $('#crInput');
    var $dr          = $('#drInput');

    // ---- formatting helpers (shared with index.php conceptually) -----------

    // Format a number as money: thousands grouping + 2 decimals.
    function fmtMoney(n) {
        if (n === null || isNaN(n)) return '';
        var s = (Math.round(n * 100) / 100).toFixed(2);
        var parts = s.split('.');
        parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        return parts.join('.');
    }

    // Strip everything that isn't a digit or a dot, prevent multiple dots,
    // limit to 2 decimal places.
    function sanitizeMoneyInput(raw) {
        var s = String(raw).replace(/[^0-9.]/g, '');
        var firstDot = s.indexOf('.');
        if (firstDot !== -1) {
            s = s.slice(0, firstDot + 1) + s.slice(firstDot + 1).replace(/\./g, '');
            var intPart = s.slice(0, firstDot);
            var decPart = s.slice(firstDot + 1, firstDot + 3);
            s = decPart.length ? (intPart + '.' + decPart) : (intPart + '.');
        }
        return s;
    }

    // Red for negative, green for positive, neutral when zero/blank.
    function styleAmount($el, n) {
        $el.removeClass('text-danger text-success fw-semibold');
        if (n === null || isNaN(n)) return;
        if (n < 0)      $el.addClass('text-danger fw-semibold');
        else if (n > 0) $el.addClass('text-success fw-semibold');
    }

    // ---- combobox -----------------------------------------------------------

    var combo = new TrxCombobox({
        inputEl:  document.getElementById('masterInput'),
        hiddenEl: document.getElementById('masterId'),
        listEl:   document.getElementById('masterList'),
        masters:  MASTERS,
        // onSelect fires with the picked master object, or null when the user
        // is mid-typing (so we wipe stale balances).
        onSelect: function (m) {
            refreshBalance();
            recomputeAfter();
            if (m) {
                // Defer the focus to the next tick so it runs AFTER the browser
                // has finished any in-flight default action for the key that
                // triggered the commit. Specifically: when the user commits
                // via Tab, the combobox's keydown handler does NOT preventDefault
                // (so Tab can still propagate). If we focused $cr synchronously
                // here, the browser's Tab default would then run and move focus
                // FROM $cr to the next tabbable ($dr) — effectively skipping Cr.
                // setTimeout(0) makes the focus call land last, parking the
                // cursor on Cr regardless of whether the commit came from
                // Enter, Tab, or a mouse click.
                setTimeout(function () { $cr.trigger('focus'); }, 0);
            }
        }
    });

    // ---- balance display ----------------------------------------------------

    function currentMasterId() {
        return parseInt($('#masterId').val(), 10) || 0;
    }

    function refreshBalance() {
        var id = currentMasterId();
        if (!id) {
            $balCurrent.val('').removeClass('text-danger text-success fw-semibold');
            return;
        }
        var bal = BALANCES.hasOwnProperty(id) ? Number(BALANCES[id]) : 0;
        $balCurrent.val(fmtMoney(bal));
        styleAmount($balCurrent, bal);
    }

    function recomputeAfter() {
        var id = currentMasterId();
        if (!id) {
            $balAfter.val('').removeClass('text-danger text-success fw-semibold');
            return;
        }
        var bal = BALANCES.hasOwnProperty(id) ? Number(BALANCES[id]) : 0;
        var cr  = parseFloat($cr.val()) || 0;
        var dr  = parseFloat($dr.val()) || 0;
        // Classic cashbook: balance = SUM(dr) − SUM(cr).
        // Adding dr (receipt) increases; cr (payment) decreases.
        var after = bal + dr - cr;
        $balAfter.val(fmtMoney(after));
        styleAmount($balAfter, after);
    }

    // ---- cr / dr mutual exclusion + sanitisation ---------------------------

    function syncCrDrLock() {
        var crFilled = String($cr.val()).trim() !== '';
        var drFilled = String($dr.val()).trim() !== '';
        $dr.prop('disabled', crFilled).toggleClass('bg-light', crFilled);
        $cr.prop('disabled', drFilled).toggleClass('bg-light', drFilled);
        if (crFilled && drFilled === false) $dr.val('');
        if (drFilled && crFilled === false) $cr.val('');
    }

    $cr.on('input', function () {
        this.value = sanitizeMoneyInput(this.value);
        syncCrDrLock();
        recomputeAfter();
    });
    $dr.on('input', function () {
        this.value = sanitizeMoneyInput(this.value);
        syncCrDrLock();
        recomputeAfter();
    });

    // ---- initial state ------------------------------------------------------

    refreshBalance();
    recomputeAfter();
    syncCrDrLock();

    <?php if ($hasErrors): ?>
    // A submit just failed — keep focus on the first invalid field.
    var $firstInvalid = $('.is-invalid').first();
    if ($firstInvalid.length) $firstInvalid.trigger('focus');
    <?php endif; ?>
});
</script>

<?php include APP_BASE . '/app/Views/partials/footer.php'; ?>
