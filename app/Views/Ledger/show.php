<?php
/**
 * View: app/Views/Ledger/show.php
 * ------------------------------------------------------------
 * Per-master ledger page (/ledger/{id}).
 *
 * Per bible step 5:
 *   1. trx_id
 *   2. date
 *   3. dr
 *   4. cr
 *   5. edit button (live edit — modal + AJAX)
 *   6. delete button
 *   + (added by user request) per-master running balance
 *
 * Locals provided by LedgerController::show():
 *   $master    { id,name,station,balance }
 *   $rows      ledger rows (sorted ASC server-side; per-master running_balance set)
 *   $masters   full list (for edit-modal combobox)
 *   $balances  { master_id => balance } (for edit-modal baseline math)
 *
 * Edit + Delete reuse:
 *   - The shared trx_edit_modal + trx_delete_modal partials (same HTML as /trx)
 *   - The shared TrxRowActions JS class (same wiring as /trx)
 *   - The existing POST /trx/{id}/update + /trx/{id}/delete endpoints
 *
 * On every successful save/delete the server returns the FULL trx state
 * (all masters). This page filters the payload down to THIS master and
 * recomputes the per-master running balance + the header balance.
 * If the user changes the master during edit, the row simply disappears
 * from this ledger (it belongs to a different master now).
 * ------------------------------------------------------------
 */
include APP_BASE . '/app/Views/partials/header.php';

$trxBase = $url('/trx');   // mutation endpoints still live under /trx/...

$balanceClass = static function (float $n): string {
    if ($n < 0) return 'text-danger';
    if ($n > 0) return 'text-success';
    return 'text-muted';
};

$fmtMoney = static function (float $n): string {
    return number_format($n, 2, '.', ',');
};
?>

<!-- Header: back link (left) + "Print Ledger" button (right). -->
<div class="mb-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
    <a href="<?= $e($url('/ledger')) ?>" class="text-decoration-none small">
        &larr; All ledgers
    </a>
    <!-- Opens the party-wise FPDF in a new tab. Same minimalist Courier
         style as the /ledger/print/regular and /local printouts. -->
    <a href="<?= $e($url('/ledger/' . (int) $master['id'] . '/print')) ?>"
       target="_blank" rel="noopener"
       class="btn btn-outline-primary btn-sm">
        Print Ledger
    </a>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body d-flex justify-content-between align-items-center flex-wrap gap-3">
        <div>
            <h1 class="h4 fw-bold mb-1"><?= $e($master['name']) ?></h1>
            <?php if ($master['station'] !== ''): ?>
                <div class="text-muted small"><?= $e($master['station']) ?></div>
            <?php endif; ?>
        </div>
        <div class="text-end">
            <div class="text-muted small text-uppercase">Current balance</div>
            <div id="ledgerHeaderBalance"
                 class="fs-4 fw-bold <?= $e($balanceClass((float) $master['balance'])) ?>">
                <?= $e($fmtMoney((float) $master['balance'])) ?>
            </div>
        </div>
    </div>
</div>

<!-- Empty state (toggled by JS after deletes). -->
<div id="ledgerEmpty" class="card border-0 shadow-sm" <?= !empty($rows) ? 'hidden' : '' ?>>
    <div class="card-body text-center py-5">
        <p class="text-muted mb-3">No transactions for this master yet.</p>
        <a href="<?= $e($url('/trx/create')) ?>" class="btn btn-outline-primary btn-sm">
            + Add a transaction
        </a>
    </div>
</div>

<!-- Ledger table (sorted ASC by trx_id, oldest at top). -->
<div id="ledgerTableWrap" class="table-responsive shadow-sm rounded bg-white" <?= empty($rows) ? 'hidden' : '' ?>>
    <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
            <tr>
                <th scope="col" class="text-muted small" style="width:80px;">#</th>
                <th scope="col" style="width:120px;">Date</th>
                <th scope="col" class="text-end" style="width:130px;">Dr</th>
                <th scope="col" class="text-end" style="width:130px;">Cr</th>
                <th scope="col" class="text-end" style="width:150px;">Running balance</th>
                <th scope="col" class="text-end" style="width:160px;">Actions</th>
            </tr>
        </thead>
        <tbody id="ledgerTbody">
            <!-- Rendered entirely by JS so the post-AJAX redraw uses the same path. -->
        </tbody>
    </table>
</div>

<!-- Shared edit + delete modals — same partials as the /trx page. -->
<?php include APP_BASE . '/app/Views/partials/trx_edit_modal.php'; ?>
<?php include APP_BASE . '/app/Views/partials/trx_delete_modal.php'; ?>


<!-- ===== Page JS =====
     Script load order (set in LedgerController::show() via $extraScripts):
       jQuery → Bootstrap → trx-combobox.js → trx-row-actions.js → app.js.
     No SortableJS here — reordering is only on the cashbook page. -->
<script>
document.addEventListener('DOMContentLoaded', function () {
    'use strict';

    if (!window.jQuery || !window.bootstrap || !window.TrxRowActions) {
        console.error('Ledger show: missing dependency (jQuery / Bootstrap / TrxRowActions).');
        return;
    }
    var $ = window.jQuery;

    // ---- data from PHP ------------------------------------------------------
    var MASTER_ID        = <?= (int) $master['id'] ?>;
    var MASTERS          = <?= json_encode($masters,  JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
    var INITIAL_ROWS     = <?= json_encode($rows,     JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
    var INITIAL_BALANCES = <?= json_encode((object) $balances, JSON_UNESCAPED_SLASHES) ?>;
    var BASE             = <?= json_encode($trxBase, JSON_UNESCAPED_SLASHES) ?>;
    var CSRF_TOKEN       = $('meta[name="csrf-token"]').attr('content') || '';
    var CSRF_NAME        = '_csrf';

    // Mutable state. `rows` is the filtered set for THIS master (DESC view).
    // `balances` is the FULL per-master map so the edit-modal can compute the
    // baseline correctly when the user reassigns a row to another master.
    var state = {
        rows:     INITIAL_ROWS.slice(),
        balances: $.extend({}, INITIAL_BALANCES)
    };

    var $tbody     = $('#ledgerTbody');
    var $tableWrap = $('#ledgerTableWrap');
    var $empty     = $('#ledgerEmpty');
    var $hdrBal    = $('#ledgerHeaderBalance');

    var fmtMoney    = TrxRowActions.fmtMoney;
    var styleAmount = TrxRowActions.styleAmount;
    function moneyCell(n) { return n === null || n === undefined ? '' : fmtMoney(n); }

    // Render a YYYY-MM-DD string (the MySQL/JSON format) as DD/MM/YYYY for
    // table display. Edit modal still uses raw ISO via row data (the date
    // <input type="date"> needs that). Falls back to the raw string on
    // anything that doesn't look like an ISO date.
    function fmtDate(s) {
        if (!s) return '';
        var m = /^(\d{4})-(\d{2})-(\d{2})/.exec(String(s));
        return m ? (m[3] + '/' + m[2] + '/' + m[1]) : String(s);
    }

    // ---- header balance render ---------------------------------------------

    function updateHeader() {
        var bal = Number(state.balances[MASTER_ID] || 0);
        $hdrBal
            .removeClass('text-danger text-success text-muted')
            .addClass(bal < 0 ? 'text-danger' : (bal > 0 ? 'text-success' : 'text-muted'))
            .text(fmtMoney(bal));
    }

    // ---- table render -------------------------------------------------------

    function renderTable() {
        // Display ASC by trx_id (oldest at top). Note: this also matches the
        // natural order in which `running_balance` was computed server-side,
        // so the balance column reads top-to-bottom as a running total.
        var sorted = state.rows.slice().sort(function (a, b) {
            return a.trx_id - b.trx_id;
        });

        $tbody.empty();

        if (sorted.length === 0) {
            $tableWrap.prop('hidden', true);
            $empty.prop('hidden', false);
            return;
        }
        $empty.prop('hidden', true);
        $tableWrap.prop('hidden', false);

        sorted.forEach(function (r) {
            var $tr = $('<tr>', {
                'data-id':          r.id,
                'data-trx-id':      r.trx_id,
                'data-master-id':   r.master_id,
                'data-master-name': r.master_name
            });

            // 1) trx_id
            $tr.append($('<td>', { 'class': 'fw-semibold', text: r.trx_id }));

            // 2) date — displayed as DD/MM/YYYY (raw ISO stays on row data for the modal).
            $tr.append($('<td>', { 'class': 'small text-nowrap', text: fmtDate(r.trx_date) }));

            // 3) dr
            $tr.append($('<td>', { 'class': 'text-end text-nowrap', text: moneyCell(r.dr) }));

            // 4) cr
            $tr.append($('<td>', { 'class': 'text-end text-nowrap', text: moneyCell(r.cr) }));

            // 5) running balance (per-master cumulative)
            var $bal = $('<td>', {
                'class': 'text-end text-nowrap fw-semibold',
                text: fmtMoney(r.running_balance)
            });
            styleAmount($bal, r.running_balance);
            $tr.append($bal);

            // 6) actions
            var $actions = $('<td>', { 'class': 'text-end text-nowrap' });
            $actions.append($('<button>', {
                type: 'button',
                'class': 'btn btn-sm btn-outline-secondary me-1 trx-edit-btn',
                text: 'Edit'
            }));
            $actions.append($('<button>', {
                type: 'button',
                'class': 'btn btn-sm btn-outline-danger trx-delete-btn',
                text: 'Delete'
            }));
            $tr.append($actions);

            $tbody.append($tr);
        });
    }

    // ---- after-AJAX state sync ---------------------------------------------

    // Take the full payload, filter to THIS master, recompute per-master
    // running balance (server's running_balance is the cashbook one), then
    // redraw. Header balance comes from payload.balances.
    function applyPayload(payload) {
        if (!payload || !payload.ok || !Array.isArray(payload.rows)) return;

        state.balances = payload.balances || {};

        var mine = payload.rows.filter(function (r) { return r.master_id === MASTER_ID; });
        // Server orders rows ASC by trx_id, so walking in-order yields the
        // correct per-master running balance.
        var running = 0;
        mine.forEach(function (r) {
            running += Number(r.dr || 0) - Number(r.cr || 0);
            r.running_balance = running;
        });

        state.rows = mine;
        updateHeader();
        renderTable();
    }

    function flashError(msg) {
        var $alert = $('<div>', {
            'class': 'alert alert-danger alert-dismissible fade show',
            role: 'alert'
        })
            .text(msg || 'Something went wrong.')
            .append('<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>');
        $('main.container').prepend($alert);
    }

    // ---- edit + delete: shared library --------------------------------------

    new TrxRowActions({
        base:       BASE,
        masters:    MASTERS,
        getState:   function () { return state; },
        onSuccess:  applyPayload,
        flashError: flashError,
        csrfToken:  CSRF_TOKEN,
        csrfName:   CSRF_NAME
    });

    // ---- first paint --------------------------------------------------------
    renderTable();
});
</script>

<?php include APP_BASE . '/app/Views/partials/footer.php'; ?>
