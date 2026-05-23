<?php
/**
 * View: app/Views/Trx/index.php
 * ------------------------------------------------------------
 * Cashbook — Transactions table (R + U + D + drag-reorder).
 *
 * Locals provided by TrxController::index():
 *   $rows       array<int,{id,master_id,master_name,master_station,
 *                          trx_date,trx_id,cr,dr,remark,running_balance}>
 *               — sorted by trx_id ASC; running_balance pre-computed
 *               (cashbook cumulative SUM(dr-cr)).
 *   $masters    full master list (for the edit-modal combobox)
 *   $balances   { master_id => SUM(dr)-SUM(cr) per master }
 *
 * UI (per bible step 4):
 *   - Table shows entries in DESCENDING trx_id order ("newest on top").
 *   - trx_id column has a drag handle (SortableJS): drop a row at position
 *     N → that row's trx_id becomes N and everything in the displaced
 *     window shifts accordingly. All via AJAX.
 *   - Edit button → modal (shared partial) pre-filled with master combobox
 *     + cr/dr + remark + current/updated balance. Save via AJAX.
 *   - Delete button → confirmation modal (shared partial). Delete via AJAX;
 *     trx_id sequence renumbers automatically (−1 below).
 *
 * Shared with /ledger/{id}:
 *   - The edit + delete modal HTML lives in app/Views/partials/trx_*.php.
 *   - The modal wiring (combobox, balance preview, AJAX) is shared in
 *     public/assets/js/trx-row-actions.js (window.TrxRowActions).
 *   - This page additionally wires SortableJS (drag-to-reorder), which
 *     /ledger does NOT use.
 *
 * Security: CSRF token on every POST, server re-validates everything.
 * ------------------------------------------------------------
 */
include APP_BASE . '/app/Views/partials/header.php';

$base = $url('/trx');
?>

<div class="d-flex justify-content-between align-items-end mb-4 flex-wrap gap-2">
    <div>
        <h1 class="h3 fw-bold mb-1">Transactions</h1>
        <p class="text-muted mb-0 small">
            Drag the <span class="fw-semibold">#</span> column to reorder &middot;
            sorted by <span class="fw-semibold">Trx&nbsp;#</span> descending
        </p>
    </div>
    <a href="<?= $e($url('/trx/create')) ?>" class="btn btn-primary">
        <span aria-hidden="true">+</span> New Transaction
    </a>
</div>

<!-- Empty state (shown when no rows). Re-toggled by JS after AJAX deletes. -->
<div id="trxEmpty" class="card border-0 shadow-sm" <?= !empty($rows) ? 'hidden' : '' ?>>
    <div class="card-body text-center py-5">
        <p class="text-muted mb-3">No transactions yet.</p>
        <a href="<?= $e($url('/trx/create')) ?>" class="btn btn-outline-primary btn-sm">
            + Add the first transaction
        </a>
    </div>
</div>

<!-- Table (shown when at least one row exists). -->
<div id="trxTableWrap" class="table-responsive shadow-sm rounded bg-white" <?= empty($rows) ? 'hidden' : '' ?>>
    <table class="table table-hover align-middle mb-0 trx-table">
        <thead class="table-light">
            <tr>
                <th scope="col" class="text-muted small" style="width:90px;">#</th>
                <th scope="col" style="width:120px;">Date</th>
                <th scope="col">Master</th>
                <th scope="col" class="text-end" style="width:120px;">Cr</th>
                <th scope="col" class="text-end" style="width:120px;">Dr</th>
                <th scope="col">Remark</th>
                <th scope="col" class="text-end" style="width:140px;">Balance</th>
                <th scope="col" class="text-end" style="width:160px;">Actions</th>
            </tr>
        </thead>
        <tbody id="trxTbody">
            <!-- Rows are rendered entirely by JS (renderTable). On first paint
                 this body is empty; the inline script at the bottom fills it
                 from PHP-injected JSON. This keeps the post-AJAX re-render
                 path identical to the first render. -->
        </tbody>
    </table>
</div>

<!-- Edit + delete modal HTML — shared with /ledger/{id}. -->
<?php include APP_BASE . '/app/Views/partials/trx_edit_modal.php'; ?>
<?php include APP_BASE . '/app/Views/partials/trx_delete_modal.php'; ?>


<!-- ===== Page JS =====
     Script load order (see footer.php): jQuery → Bootstrap → trx-combobox.js
     → trx-row-actions.js → Sortable.min.js → app.js. All deps are ready by
     DOMContentLoaded. The shared TrxRowActions class handles the edit + delete
     modals; this page just owns state management, the table render and the
     SortableJS drag-reorder (which is /trx-specific). -->
<script>
document.addEventListener('DOMContentLoaded', function () {
    'use strict';

    if (!window.jQuery || !window.bootstrap || !window.TrxRowActions || !window.Sortable) {
        console.error('Trx index: missing dependency (jQuery / Bootstrap / TrxRowActions / Sortable).');
        return;
    }
    var $ = window.jQuery;

    // ---- data from PHP ------------------------------------------------------
    var MASTERS          = <?= json_encode($masters,  JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
    var INITIAL_ROWS     = <?= json_encode($rows,     JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
    var INITIAL_BALANCES = <?= json_encode((object) $balances, JSON_UNESCAPED_SLASHES) ?>;
    var BASE             = <?= json_encode($base, JSON_UNESCAPED_SLASHES) ?>;
    var CSRF_TOKEN       = $('meta[name="csrf-token"]').attr('content') || '';
    var CSRF_NAME        = '_csrf';

    // Mutable state — kept in sync after every AJAX round-trip.
    var state = {
        rows:     INITIAL_ROWS.slice(),
        balances: $.extend({}, INITIAL_BALANCES)
    };

    var $tbody     = $('#trxTbody');
    var $tableWrap = $('#trxTableWrap');
    var $empty     = $('#trxEmpty');

    // Re-use the shared formatters so the page and the modal print money
    // exactly the same way.
    var fmtMoney    = TrxRowActions.fmtMoney;
    var styleAmount = TrxRowActions.styleAmount;
    function moneyCell(n) { return n === null || n === undefined ? '' : fmtMoney(n); }

    // ---- table render -------------------------------------------------------

    function renderTable() {
        // Bible: descending order of trx_id.
        var sorted = state.rows.slice().sort(function (a, b) {
            return b.trx_id - a.trx_id;
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

            // 1) trx_id with drag handle.
            $tr.append(
                $('<td>', { 'class': 'drag-handle text-muted small' })
                    .append($('<span>', { 'class': 'drag-grip me-1', text: '⋮⋮' }))
                    .append($('<span>', { 'class': 'fw-semibold text-body', text: r.trx_id }))
            );

            // 2) trx_date.
            $tr.append($('<td>', { 'class': 'small text-nowrap', text: r.trx_date }));

            // 3) master — name (bold) + station (muted, beside).
            var $masterCell = $('<td>');
            $masterCell.append($('<span>', { 'class': 'fw-semibold', text: r.master_name }));
            if (r.master_station) {
                $masterCell.append(
                    $('<span>', { 'class': 'text-muted small ms-2', text: r.master_station })
                );
            }
            $tr.append($masterCell);

            // 4) cr.
            $tr.append($('<td>', { 'class': 'text-end text-nowrap', text: moneyCell(r.cr) }));

            // 5) dr.
            $tr.append($('<td>', { 'class': 'text-end text-nowrap', text: moneyCell(r.dr) }));

            // 6) remark.
            if (r.remark) {
                $tr.append($('<td>', { 'class': 'small', text: r.remark }));
            } else {
                $tr.append($('<td>').append($('<span>', { 'class': 'text-muted', text: '—' })));
            }

            // 7) running balance (cashbook cumulative).
            var $bal = $('<td>', {
                'class': 'text-end text-nowrap fw-semibold',
                text: fmtMoney(r.running_balance)
            });
            styleAmount($bal, r.running_balance);
            $tr.append($bal);

            // 8) actions.
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

    // ---- shared error toast -------------------------------------------------

    function flashError(msg) {
        var $alert = $('<div>', {
            'class': 'alert alert-danger alert-dismissible fade show',
            role: 'alert'
        })
            .text(msg || 'Something went wrong.')
            .append('<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>');
        $('main.container').prepend($alert);
    }

    // ---- edit + delete: delegated to the shared library --------------------

    new TrxRowActions({
        base:       BASE,
        masters:    MASTERS,
        getState:   function () { return state; },
        onSuccess:  function (payload) {
            if (!payload || !payload.ok || !Array.isArray(payload.rows)) return;
            state.rows     = payload.rows;
            state.balances = payload.balances || {};
            renderTable();
        },
        flashError: flashError,
        csrfToken:  CSRF_TOKEN,
        csrfName:   CSRF_NAME
    });

    // ---- drag-and-drop reorder (SortableJS) — /trx page only ---------------

    function ajaxReorder(rowId, newTrxId) {
        var payload = { new_trx_id: newTrxId };
        payload[CSRF_NAME] = CSRF_TOKEN;
        return $.ajax({
            url:      BASE + '/' + rowId + '/reorder',
            method:   'POST',
            data:     payload,
            dataType: 'json',
            headers:  { 'X-Requested-With': 'XMLHttpRequest' }
        });
    }

    Sortable.create($tbody[0], {
        handle:      '.drag-handle',
        animation:   150,
        ghostClass:  'trx-row-ghost',
        chosenClass: 'trx-row-chosen',
        dragClass:   'trx-row-drag',
        onEnd: function (evt) {
            if (evt.oldIndex === evt.newIndex) return;

            var rowId = parseInt($(evt.item).attr('data-id'), 10);
            var total = state.rows.length;
            // Table is DESC, so DOM index 0 = highest trx_id (= total).
            var newTrxId = total - evt.newIndex;

            ajaxReorder(rowId, newTrxId)
                .done(function (resp) {
                    if (resp && resp.ok && Array.isArray(resp.rows)) {
                        state.rows     = resp.rows;
                        state.balances = resp.balances || {};
                        renderTable();
                    }
                })
                .fail(function (xhr) {
                    flashError((xhr.responseJSON && xhr.responseJSON.message) || 'Reorder failed.');
                    renderTable();   // revert to last known good state
                });
        }
    });

    // ---- first paint --------------------------------------------------------
    renderTable();
});
</script>

<?php include APP_BASE . '/app/Views/partials/footer.php'; ?>
