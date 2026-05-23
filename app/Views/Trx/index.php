<?php
/**
 * View: app/Views/Trx/index.php
 * ------------------------------------------------------------
 * Cashbook — Transactions table (R + U + D + drag-reorder).
 *
 * Locals provided by TrxController::index():
 *   $rows       array<int,{id,master_id,master_name,master_station,
 *                          trx_date,trx_id,cr,dr,remark,running_balance}>
 *               — sorted by trx_id ASC; running_balance pre-computed.
 *   $masters    full master list (for the edit-modal combobox)
 *   $balances   { master_id => SUM(dr)-SUM(cr) per master }
 *
 * UI (per bible step 4):
 *   - Table shows entries in DESCENDING trx_id order ("newest on top").
 *   - trx_id column has a drag handle (SortableJS): drop a row at position
 *     N → that row's trx_id becomes N and everything in the displaced
 *     window shifts accordingly. All via AJAX.
 *   - Edit button → modal pre-filled with master combobox + cr/dr + remark
 *     + current and updated balance (same UX as add). Save via AJAX.
 *   - Delete button → confirmation modal (matches Master delete pattern).
 *     Delete via AJAX; sequence renumbers automatically (−1 below).
 *
 * Security:
 *   - All cell contents pass through $e() (htmlspecialchars).
 *   - CSRF token included on every AJAX request (read from <meta>).
 *   - Server re-validates every mutation; never trust the client.
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


<!-- ===== Edit modal (master combobox + cr/dr + remark + balances) ===== -->
<div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <form id="editForm" autocomplete="off" novalidate>
                <?= $csrfField ?>
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title mb-0" id="editModalTitle">Edit Transaction</h5>
                        <div class="text-muted small mt-1">
                            Trx&nbsp;# <span id="editTrxIdNum" class="fw-semibold text-body">—</span>
                        </div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">

                    <!-- Date — narrow column on its own row. -->
                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label for="editTrxDate" class="form-label">Date</label>
                            <input type="date"
                                   class="form-control"
                                   id="editTrxDate" name="trx_date"
                                   required>
                            <div class="invalid-feedback" id="editTrxDateErr"></div>
                        </div>
                    </div>

                    <!-- Master combobox — full width, deserves the room. -->
                    <div class="mb-3">
                        <label for="editMasterInput" class="form-label">
                            Master <span class="text-danger">*</span>
                        </label>
                        <div class="trx-combo position-relative">
                            <input type="text"
                                   class="form-control"
                                   id="editMasterInput"
                                   autocomplete="off"
                                   spellcheck="false"
                                   role="combobox"
                                   aria-autocomplete="list"
                                   aria-expanded="false"
                                   aria-controls="editMasterList"
                                   required>
                            <input type="hidden" id="editMasterId" name="master_id">
                            <ul id="editMasterList"
                                class="trx-combo-list list-group position-absolute w-100 shadow-sm"
                                role="listbox"
                                hidden></ul>
                            <div class="invalid-feedback d-block" id="editMasterErr"></div>
                        </div>
                        <div class="form-text">
                            Start typing to filter &middot; Alt+&darr; for full list &middot; Enter to select
                        </div>
                    </div>

                    <!-- Cr / Dr — side-by-side, equal columns (no balance crammed in). -->
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label for="editCr" class="form-label">Cr (Credit)</label>
                            <input type="text"
                                   class="form-control text-end"
                                   id="editCr" name="cr"
                                   inputmode="decimal" autocomplete="off"
                                   placeholder="0.00">
                            <div class="invalid-feedback" id="editCrErr"></div>
                        </div>
                        <div class="col-md-6">
                            <label for="editDr" class="form-label">Dr (Debit)</label>
                            <input type="text"
                                   class="form-control text-end"
                                   id="editDr" name="dr"
                                   inputmode="decimal" autocomplete="off"
                                   placeholder="0.00">
                            <div class="invalid-feedback" id="editDrErr"></div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="editRemark" class="form-label">Remark</label>
                        <input type="text"
                               class="form-control"
                               id="editRemark" name="remark"
                               maxlength="255">
                        <div class="invalid-feedback" id="editRemarkErr"></div>
                    </div>

                    <!-- Balance summary — visually separate from editable
                         inputs so it's clear at a glance these are read-only. -->
                    <div class="trx-balance-summary rounded p-3 d-flex justify-content-between align-items-center">
                        <div>
                            <div class="trx-balance-label">Current balance</div>
                            <div class="trx-balance-value" id="editBalanceCurrent">—</div>
                        </div>
                        <div class="text-muted px-2 fs-5" aria-hidden="true">&rarr;</div>
                        <div class="text-end">
                            <div class="trx-balance-label">After save</div>
                            <div class="trx-balance-value" id="editBalanceAfter">—</div>
                        </div>
                    </div>

                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="editSubmit">Save changes</button>
                </div>
            </form>
        </div>
    </div>
</div>


<!-- ===== Delete confirmation modal ===== -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <form id="deleteForm" novalidate>
                <?= $csrfField ?>
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteModalTitle">Delete transaction?</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-0">
                        Delete Trx&nbsp;# <strong id="deleteTrxId">—</strong>
                        (<span id="deleteMaster" class="text-muted">—</span>)?
                    </p>
                    <p class="text-muted small mb-0 mt-2">
                        Entries below will renumber automatically. This cannot be undone.
                    </p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger" id="deleteSubmit">Delete</button>
                </div>
            </form>
        </div>
    </div>
</div>


<!-- ===== Page JS =====
     Loaded order (see footer.php): jQuery → Bootstrap → trx-combobox.js
     → Sortable.min.js → app.js. By the time DOMContentLoaded fires, all
     dependencies are ready. -->
<script>
document.addEventListener('DOMContentLoaded', function () {
    'use strict';

    if (!window.jQuery || !window.bootstrap || !window.TrxCombobox || !window.Sortable) {
        console.error('Trx index: missing dependency (jQuery / Bootstrap / TrxCombobox / Sortable).');
        return;
    }
    var $ = window.jQuery;

    // ---- data from PHP ------------------------------------------------------
    var MASTERS  = <?= json_encode($masters,  JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
    var INITIAL_ROWS     = <?= json_encode($rows, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
    var INITIAL_BALANCES = <?= json_encode((object) $balances, JSON_UNESCAPED_SLASHES) ?>;
    var BASE     = <?= json_encode($base, JSON_UNESCAPED_SLASHES) ?>;
    var CSRF_TOKEN = $('meta[name="csrf-token"]').attr('content') || '';
    var CSRF_NAME  = '_csrf';      // matches Csrf::fieldName() default

    // Mutable application state — kept in sync after every AJAX round-trip.
    var state = {
        rows:     INITIAL_ROWS.slice(),       // master copy, ASC by trx_id from server
        balances: $.extend({}, INITIAL_BALANCES)
    };

    // ---- cached DOM refs ----------------------------------------------------
    var $tbody     = $('#trxTbody');
    var $tableWrap = $('#trxTableWrap');
    var $empty     = $('#trxEmpty');

    // ---- formatting helpers -------------------------------------------------

    function fmtMoney(n) {
        if (n === null || n === undefined || isNaN(n)) return '';
        var s = (Math.round(Number(n) * 100) / 100).toFixed(2);
        var parts = s.split('.');
        parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        return parts.join('.');
    }

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

    function styleAmount($el, n) {
        $el.removeClass('text-danger text-success fw-semibold');
        if (n === null || n === undefined || isNaN(n) || Number(n) === 0) return;
        if (Number(n) < 0)      $el.addClass('text-danger fw-semibold');
        else                    $el.addClass('text-success fw-semibold');
    }

    function moneyCell(n) {
        // Use HTML-escape via .text() inside a <td>, so just return raw text.
        return n === null || n === undefined ? '' : fmtMoney(n);
    }

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
                'data-id':         r.id,
                'data-trx-id':     r.trx_id,
                'data-master-id':  r.master_id,
                'data-master-name': r.master_name,
                'data-cr':         r.cr === null ? '' : r.cr,
                'data-dr':         r.dr === null ? '' : r.dr,
                'data-remark':     r.remark || ''
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

            // 7) running balance (cash-book cumulative).
            var $bal = $('<td>', {
                'class': 'text-end text-nowrap fw-semibold',
                text: fmtMoney(r.running_balance)
            });
            styleAmount($bal, r.running_balance);
            $tr.append($bal);

            // 8) actions.
            var $actions = $('<td>', { 'class': 'text-end text-nowrap' });
            $actions.append(
                $('<button>', {
                    type: 'button',
                    'class': 'btn btn-sm btn-outline-secondary me-1 trx-edit-btn',
                    text: 'Edit'
                })
            );
            $actions.append(
                $('<button>', {
                    type: 'button',
                    'class': 'btn btn-sm btn-outline-danger trx-delete-btn',
                    text: 'Delete'
                })
            );
            $tr.append($actions);

            $tbody.append($tr);
        });
    }

    // ---- AJAX wrapper -------------------------------------------------------

    // Centralised so every POST is consistent (CSRF, headers, error toast).
    function ajaxPost(path, data) {
        var payload = $.extend({}, data || {});
        payload[CSRF_NAME] = CSRF_TOKEN;

        return $.ajax({
            url:      BASE + path,
            method:   'POST',
            data:     payload,
            dataType: 'json',
            headers:  { 'X-Requested-With': 'XMLHttpRequest' }
        });
    }

    // Apply a successful server payload to local state + redraw.
    function applyPayload(payload) {
        if (!payload || !payload.ok || !Array.isArray(payload.rows)) {
            console.warn('Trx: malformed AJAX payload', payload);
            return;
        }
        state.rows     = payload.rows;
        state.balances = payload.balances || {};
        renderTable();
    }

    function flashError(msg) {
        // Re-use the global flash style (alert-dismissible) so styling is consistent.
        var $alert = $('<div>', {
            'class': 'alert alert-danger alert-dismissible fade show',
            role: 'alert'
        })
            .text(msg || 'Something went wrong.')
            .append('<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>');
        $('main.container').prepend($alert);
        // Auto-dismiss is suppressed for .alert-danger by app.js — fine here.
    }

    // ---- drag-and-drop reorder (SortableJS) --------------------------------

    Sortable.create($tbody[0], {
        handle:    '.drag-handle',
        animation: 150,
        ghostClass: 'trx-row-ghost',
        chosenClass: 'trx-row-chosen',
        dragClass:   'trx-row-drag',
        onEnd: function (evt) {
            if (evt.oldIndex === evt.newIndex) return;     // no-op

            var $row     = $(evt.item);
            var rowId    = parseInt($row.attr('data-id'), 10);
            var total    = state.rows.length;
            // Table is DESC: DOM index 0 = highest trx_id (= total).
            // So new_trx_id = total − newDomIndex.
            var newTrxId = total - evt.newIndex;

            ajaxPost('/' + rowId + '/reorder', { new_trx_id: newTrxId })
                .done(function (payload) {
                    applyPayload(payload);
                })
                .fail(function (xhr) {
                    flashError((xhr.responseJSON && xhr.responseJSON.message) || 'Reorder failed.');
                    // Revert: re-render from server-of-record state to undo the drag.
                    renderTable();
                });
        }
    });

    // ---- edit modal: setup + state -----------------------------------------

    var editModal = new bootstrap.Modal(document.getElementById('editModal'));
    // Per-open editing context (used to compute baseline balance correctly
    // when the master is changed mid-edit).
    var editCtx = { id: 0, oldMasterId: 0, oldCr: 0, oldDr: 0 };

    var editCombo = new TrxCombobox({
        inputEl:  document.getElementById('editMasterInput'),
        hiddenEl: document.getElementById('editMasterId'),
        listEl:   document.getElementById('editMasterList'),
        masters:  MASTERS,
        onSelect: function (m) {
            refreshEditBalances();
        }
    });

    var $editCr        = $('#editCr');
    var $editDr        = $('#editDr');
    var $editBalCur    = $('#editBalanceCurrent');
    var $editBalAfter  = $('#editBalanceAfter');

    function selectedEditMasterId() {
        return parseInt($('#editMasterId').val(), 10) || 0;
    }

    // Baseline = master balance EXCLUDING the row being edited.
    // (Matches the meaning of "current balance" on the add form.)
    function baselineForMaster(masterId) {
        if (!masterId) return null;
        var bal = state.balances.hasOwnProperty(masterId) ? Number(state.balances[masterId]) : 0;
        if (masterId === editCtx.oldMasterId) {
            // Take out THIS row's old contribution.
            bal -= (Number(editCtx.oldDr) - Number(editCtx.oldCr));
        }
        return bal;
    }

    function refreshEditBalances() {
        var mId = selectedEditMasterId();
        var base = baselineForMaster(mId);

        if (base === null) {
            $editBalCur.text('—').removeClass('text-danger text-success');
            $editBalAfter.text('—').removeClass('text-danger text-success');
            return;
        }
        $editBalCur.text(fmtMoney(base));
        styleAmount($editBalCur, base);

        var cr = parseFloat($editCr.val()) || 0;
        var dr = parseFloat($editDr.val()) || 0;
        var after = base + dr - cr;
        $editBalAfter.text(fmtMoney(after));
        styleAmount($editBalAfter, after);
    }

    // Mutual exclusion for cr / dr — no disable. Both fields stay editable
    // so the user can freely switch sides on an existing entry. The "inactive"
    // side displays "0" as a visual indicator that it's unused; the second
    // the user types a real (>0) value into the previously-inactive side,
    // the other one is auto-zeroed.
    function enforceCrDrExclusion(justChanged) {
        var crNum = parseFloat($editCr.val()) || 0;
        var drNum = parseFloat($editDr.val()) || 0;
        if (justChanged === 'cr' && crNum > 0 && drNum !== 0) {
            $editDr.val('0');
        } else if (justChanged === 'dr' && drNum > 0 && crNum !== 0) {
            $editCr.val('0');
        }
    }

    // Auto-select on focus so the user can just type to replace the "0"
    // (or any current value). Matches the "money field" UX in most apps.
    $editCr.add($editDr).on('focus', function () {
        var el = this;
        // Defer so the cursor lands first, then the selection sticks.
        setTimeout(function () { el.select(); }, 0);
    });

    $editCr.on('input', function () {
        this.value = sanitizeMoneyInput(this.value);
        enforceCrDrExclusion('cr');
        refreshEditBalances();
    });
    $editDr.on('input', function () {
        this.value = sanitizeMoneyInput(this.value);
        enforceCrDrExclusion('dr');
        refreshEditBalances();
    });

    // Reset all field-level error decorations / messages.
    function clearEditErrors() {
        $('#editForm .is-invalid').removeClass('is-invalid');
        $('#editTrxDateErr,#editMasterErr,#editCrErr,#editDrErr,#editRemarkErr').text('');
    }

    function applyEditErrors(errors) {
        clearEditErrors();
        Object.keys(errors || {}).forEach(function (field) {
            var msg = (errors[field] || []).join(' ');
            if (field === 'trx_date') {
                $('#editTrxDate').addClass('is-invalid');
                $('#editTrxDateErr').text(msg);
            } else if (field === 'master_id') {
                $('#editMasterInput').addClass('is-invalid');
                $('#editMasterErr').text(msg);
            } else if (field === 'cr') {
                $editCr.addClass('is-invalid');
                $('#editCrErr').text(msg);
            } else if (field === 'dr') {
                $editDr.addClass('is-invalid');
                $('#editDrErr').text(msg);
            } else if (field === 'remark') {
                $('#editRemark').addClass('is-invalid');
                $('#editRemarkErr').text(msg);
            }
        });
    }

    // ---- edit modal: open + save -------------------------------------------

    // Open the edit modal for a given row id.
    function openEditModal(rowId) {
        var row = state.rows.find(function (r) { return r.id === rowId; });
        if (!row) return;

        editCtx = {
            id:          row.id,
            oldMasterId: row.master_id,
            oldCr:       row.cr === null ? 0 : Number(row.cr),
            oldDr:       row.dr === null ? 0 : Number(row.dr)
        };

        $('#editTrxIdNum').text(row.trx_id);
        $('#editTrxDate').val(row.trx_date);
        editCombo.setValue(row.master_id);

        // Bible: when an entry has dr=xyz, show cr as "0" (and vice versa)
        // — explicit visual cue that the other side is empty.
        $editCr.val(row.cr !== null ? row.cr : '0');
        $editDr.val(row.dr !== null ? row.dr : '0');
        $('#editRemark').val(row.remark || '');

        clearEditErrors();
        refreshEditBalances();

        editModal.show();
    }

    // Delegated click handler — works against re-rendered rows.
    $tbody.on('click', '.trx-edit-btn', function () {
        var id = parseInt($(this).closest('tr').attr('data-id'), 10);
        if (id) openEditModal(id);
    });

    // Convert "0" / "0.00" / blank into an empty string so the server
    // (which keys on the empty string to detect "unused") still sees
    // exactly ONE side as active.
    function payloadAmount($field) {
        var v = String($field.val()).trim();
        var n = parseFloat(v);
        return (v === '' || isNaN(n) || n === 0) ? '' : v;
    }

    $('#editForm').on('submit', function (ev) {
        ev.preventDefault();
        clearEditErrors();

        var payload = {
            trx_date:  $('#editTrxDate').val(),
            master_id: $('#editMasterId').val(),
            cr:        payloadAmount($editCr),
            dr:        payloadAmount($editDr),
            remark:    $('#editRemark').val()
        };

        var $btn = $('#editSubmit').prop('disabled', true).text('Saving…');

        ajaxPost('/' + editCtx.id + '/update', payload)
            .done(function (resp) {
                applyPayload(resp);
                editModal.hide();
            })
            .fail(function (xhr) {
                var resp = xhr.responseJSON || {};
                if (resp.errors) {
                    applyEditErrors(resp.errors);
                } else {
                    flashError(resp.message || 'Update failed.');
                }
            })
            .always(function () {
                $btn.prop('disabled', false).text('Save changes');
            });
    });

    // ---- delete modal: open + confirm --------------------------------------

    var deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
    var deleteCtx = { id: 0 };

    $tbody.on('click', '.trx-delete-btn', function () {
        var $row = $(this).closest('tr');
        deleteCtx.id = parseInt($row.attr('data-id'), 10);
        $('#deleteTrxId').text($row.attr('data-trx-id'));
        $('#deleteMaster').text($row.attr('data-master-name'));
        deleteModal.show();
    });

    $('#deleteForm').on('submit', function (ev) {
        ev.preventDefault();
        var $btn = $('#deleteSubmit').prop('disabled', true).text('Deleting…');

        ajaxPost('/' + deleteCtx.id + '/delete', {})
            .done(function (resp) {
                applyPayload(resp);
                deleteModal.hide();
            })
            .fail(function (xhr) {
                var resp = xhr.responseJSON || {};
                flashError(resp.message || 'Delete failed.');
            })
            .always(function () {
                $btn.prop('disabled', false).text('Delete');
            });
    });

    // ---- first paint --------------------------------------------------------
    renderTable();
});
</script>

<?php include APP_BASE . '/app/Views/partials/footer.php'; ?>
