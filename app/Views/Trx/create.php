<?php
/**
 * View: app/Views/Trx/create.php
 * ------------------------------------------------------------
 * Cashbook — New Transaction form (CREATE step).
 *
 * Locals provided by TrxController::create():
 *   $masters    array<int,{id,name,station}>  — full master list (alphabetical)
 *   $balances   array<int,float>              — { master_id => current balance }
 *   $nextTrxId  int                           — preview of the trx_id we'll assign
 *   $todayDate  string                        — YYYY-MM-DD (server "today")
 *   $errors     validation errors (flashed after a failed POST)
 *   $old        old form values (flashed after a failed POST)
 *
 * Field order (per the trx bible):
 *   1. trx_date    today by default; cursor skips it on tab (tabindex="-1")
 *   2. master      MS-Access-style combobox; autofocus; station shown beside
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
$oldDate    = (string) ($old['trx_date']  ?? $todayDate);
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

                        <!-- 1) Trx date — cursor skips this on TAB (tabindex="-1"),
                                but it's still clickable + editable. -->
                        <div class="row g-3 align-items-end mb-3">
                            <div class="col-md-4">
                                <label for="trxDate" class="form-label">Date</label>
                                <input type="date"
                                       class="form-control <?= $err('trx_date') ? 'is-invalid' : '' ?>"
                                       id="trxDate" name="trx_date"
                                       value="<?= $e($oldDate) ?>"
                                       tabindex="-1" required>
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
                                           autofocus required>
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
     Waits for jQuery + Bootstrap (loaded by footer.php, which is included
     AFTER this view). All wiring is deferred to DOMContentLoaded. -->
<script>
document.addEventListener('DOMContentLoaded', function () {
    'use strict';

    if (!window.jQuery) {
        console.error('Trx page: jQuery not loaded — form wiring skipped.');
        return;
    }
    var $ = window.jQuery;

    // ---- data from PHP ------------------------------------------------------
    // Full master list (already sorted by name ASC server-side).
    var MASTERS  = <?= json_encode($masters,  JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
    // { master_id => current balance } as plain numbers.
    var BALANCES = <?= json_encode((object) $balances, JSON_UNESCAPED_SLASHES) ?>;

    // ---- cached DOM refs ----------------------------------------------------
    var $masterInput = $('#masterInput');
    var $masterId    = $('#masterId');
    var $masterList  = $('#masterList');
    var $balCurrent  = $('#balanceCurrent');
    var $balAfter    = $('#balanceAfter');
    var $cr          = $('#crInput');
    var $dr          = $('#drInput');

    // Tracks the currently highlighted dropdown item index, -1 when none.
    var highlighted = -1;
    // Last filtered list (subset of MASTERS) rendered into the dropdown.
    var visible     = [];

    // ---- small helpers ------------------------------------------------------

    // Format a number as money: thousands grouping + 2 decimals. Negative
    // balances are shown in red so they're easy to spot.
    function fmtMoney(n) {
        if (n === null || isNaN(n)) return '';
        var s = (Math.round(n * 100) / 100).toFixed(2);
        // Insert commas in the integer portion.
        var parts = s.split('.');
        parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        return parts.join('.');
    }

    // Strip everything that isn't a digit or a dot, and prevent multiple dots.
    function sanitizeMoneyInput(raw) {
        var s = String(raw).replace(/[^0-9.]/g, '');
        var firstDot = s.indexOf('.');
        if (firstDot !== -1) {
            s = s.slice(0, firstDot + 1) + s.slice(firstDot + 1).replace(/\./g, '');
        }
        // Limit to 2 decimals.
        if (firstDot !== -1) {
            var int = s.slice(0, firstDot);
            var dec = s.slice(firstDot + 1, firstDot + 3);
            s = dec.length ? (int + '.' + dec) : (int + '.');
        }
        return s;
    }

    // Set the red/green tone of a "money" readonly box based on its number.
    function styleAmount($el, n) {
        $el.removeClass('text-danger text-success fw-semibold');
        if (n === null || isNaN(n)) return;
        if (n < 0)      $el.addClass('text-danger fw-semibold');
        else if (n > 0) $el.addClass('text-success fw-semibold');
    }

    // ---- combobox: filter + render ------------------------------------------

    // Filter MASTERS by a prefix on `name`. Bible rule:
    //   "tr shows truffle and not strawberries"
    // → startsWith (not includes), case-insensitive.
    function filterMasters(query) {
        var q = String(query || '').trim().toLowerCase();
        if (q === '') {
            return MASTERS.slice();       // empty query → everything
        }
        return MASTERS.filter(function (m) {
            return m.name.toLowerCase().indexOf(q) === 0;
        });
    }

    // Build the dropdown <li> items from a filtered array.
    function renderList(items) {
        $masterList.empty();
        visible = items;

        if (items.length === 0) {
            $masterList.append(
                $('<li>', {
                    'class': 'list-group-item text-muted small',
                    'aria-disabled': 'true',
                    text: 'No matches'
                })
            );
            return;
        }

        items.forEach(function (m, idx) {
            // Each <li> shows: name (left) + station (right, muted).
            // Station is purely visual — bible says "cannot search from station".
            var $li = $('<li>', {
                'class': 'list-group-item list-group-item-action d-flex justify-content-between align-items-center',
                role: 'option',
                'data-idx': idx,
                'data-id':  m.id
            });
            $li.append($('<span>', { 'class': 'me-3 text-truncate', text: m.name }));
            if (m.station) {
                $li.append($('<span>', {
                    'class': 'text-muted small ms-2 text-truncate',
                    text: m.station
                }));
            }
            $masterList.append($li);
        });
    }

    function openList(items, highlightIdx) {
        renderList(items);
        $masterList.prop('hidden', false);
        $masterInput.attr('aria-expanded', 'true');
        setHighlight(typeof highlightIdx === 'number' ? highlightIdx : 0);
    }

    function closeList() {
        $masterList.prop('hidden', true);
        $masterInput.attr('aria-expanded', 'false');
        highlighted = -1;
    }

    function setHighlight(idx) {
        if (!visible.length) { highlighted = -1; return; }
        if (idx < 0)                  idx = 0;
        if (idx >= visible.length)    idx = visible.length - 1;
        highlighted = idx;
        var $items = $masterList.children('[data-idx]');
        $items.removeClass('active');
        var $cur = $items.eq(idx).addClass('active');
        // Scroll into view if the highlighted row drifts out of the list box.
        if ($cur.length) {
            var li   = $cur[0];
            var box  = $masterList[0];
            var top  = li.offsetTop;
            var bot  = top + li.offsetHeight;
            if (top  < box.scrollTop)                       box.scrollTop = top;
            else if (bot > box.scrollTop + box.clientHeight) box.scrollTop = bot - box.clientHeight;
        }
    }

    // "Commit" a selection: copy the master into the visible input + hidden id,
    // refresh the balance display, then close the list.
    function selectMaster(m) {
        if (!m) return;
        $masterInput.val(m.name);
        $masterId.val(m.id);
        closeList();
        refreshBalance();
        recomputeAfter();
    }

    // ---- balance display ----------------------------------------------------

    function refreshBalance() {
        var id   = parseInt($masterId.val(), 10);
        if (!id) {
            $balCurrent.val('').removeClass('text-danger text-success fw-semibold');
            return;
        }
        var bal = BALANCES.hasOwnProperty(id) ? Number(BALANCES[id]) : 0;
        $balCurrent.val(fmtMoney(bal));
        styleAmount($balCurrent, bal);
    }

    function recomputeAfter() {
        var id  = parseInt($masterId.val(), 10);
        if (!id) {
            $balAfter.val('').removeClass('text-danger text-success fw-semibold');
            return;
        }
        var bal = BALANCES.hasOwnProperty(id) ? Number(BALANCES[id]) : 0;
        var cr  = parseFloat($cr.val()) || 0;
        var dr  = parseFloat($dr.val()) || 0;
        // Cashbook balance formula: SUM(cr) − SUM(dr) per master.
        var after = bal + cr - dr;
        $balAfter.val(fmtMoney(after));
        styleAmount($balAfter, after);
    }

    // ---- combobox: events ---------------------------------------------------

    $masterInput.on('input', function () {
        // Typing invalidates any prior selection until they commit a new one.
        $masterId.val('');
        $balCurrent.val('').removeClass('text-danger text-success fw-semibold');
        recomputeAfter();
        openList(filterMasters(this.value), 0);
    });

    $masterInput.on('focus', function () {
        // Re-open if there's text but the list is hidden.
        if ($masterList.prop('hidden')) {
            openList(filterMasters(this.value), 0);
        }
    });

    $masterInput.on('keydown', function (ev) {
        // Alt+ArrowDown → ALWAYS open the full list, regardless of typed text.
        if (ev.altKey && ev.key === 'ArrowDown') {
            ev.preventDefault();
            openList(MASTERS.slice(), 0);
            return;
        }

        switch (ev.key) {
            case 'ArrowDown':
                ev.preventDefault();
                if ($masterList.prop('hidden')) {
                    openList(filterMasters($masterInput.val()), 0);
                } else {
                    setHighlight(highlighted + 1);
                }
                break;

            case 'ArrowUp':
                ev.preventDefault();
                if (!$masterList.prop('hidden')) {
                    setHighlight(highlighted - 1);
                }
                break;

            case 'Enter':
                // If the dropdown is open and something is highlighted, select
                // it and stop the form from submitting.
                if (!$masterList.prop('hidden') && highlighted >= 0 && visible[highlighted]) {
                    ev.preventDefault();
                    selectMaster(visible[highlighted]);
                    $cr.trigger('focus');
                }
                break;

            case 'Escape':
                if (!$masterList.prop('hidden')) {
                    ev.preventDefault();
                    closeList();
                }
                break;

            case 'Tab':
                // If user tabs out with something highlighted, commit it
                // (mirrors MS-Access "auto-pick" feel) but let the tab through.
                if (!$masterList.prop('hidden') && highlighted >= 0 && visible[highlighted]) {
                    selectMaster(visible[highlighted]);
                }
                break;
        }
    });

    // Clicking a dropdown item picks it. mousedown (not click) so that the
    // input's `blur` doesn't fire first and tear down the list.
    $masterList.on('mousedown', '[data-idx]', function (ev) {
        ev.preventDefault();
        var idx = parseInt($(this).attr('data-idx'), 10);
        if (!isNaN(idx) && visible[idx]) {
            selectMaster(visible[idx]);
            $cr.trigger('focus');
        }
    });

    // Close the list if the user clicks outside the combobox.
    $(document).on('mousedown', function (ev) {
        if (!$(ev.target).closest('.trx-combo').length) {
            // If they leave without committing AND the typed text doesn't
            // exactly match an existing master, clear the hidden id so
            // server-side validation catches it.
            if ($masterId.val() === '') {
                var typed = String($masterInput.val() || '').trim().toLowerCase();
                if (typed !== '') {
                    var exact = MASTERS.find(function (m) {
                        return m.name.toLowerCase() === typed;
                    });
                    if (exact) {
                        selectMaster(exact);
                    }
                }
            }
            closeList();
        }
    });

    // ---- cr / dr mutual exclusion + sanitisation ---------------------------

    function syncCrDrLock() {
        var crFilled = String($cr.val()).trim() !== '';
        var drFilled = String($dr.val()).trim() !== '';
        // Whichever is filled disables the other (and clears any leftover).
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

    // Restore display after a validation failure (or on first load).
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
