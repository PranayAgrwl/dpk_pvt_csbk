/* ============================================================
   trx-row-actions.js
   ------------------------------------------------------------
   Shared edit + delete modal wiring for trx rows.

   Used by:
     - app/Views/Trx/index.php           (cashbook table  — all trx)
     - app/Views/Ledger/show.php         (per-master ledger view)

   What it handles (assumes the partials are included):
     - Click on `.trx-edit-btn`   → opens #editModal pre-filled
     - Click on `.trx-delete-btn` → opens #deleteModal pre-filled
     - #editForm   submit → AJAX POST to {base}/{id}/update
     - #deleteForm submit → AJAX POST to {base}/{id}/delete
     - Server payload is forwarded to the caller via onSuccess(payload),
       which then updates its own state + re-renders its table.

   What it does NOT handle:
     - The page's table render (highly page-specific)
     - SortableJS reorder (only the /trx page has it)
     - Initial state — the caller is the source of truth

   Public API:
     new TrxRowActions({
         base:       string,                  // e.g. '/dpk_pvt_csbk/trx'
         masters:    [{id,name,station}, ...],
         getState:   function () { return { rows: [...], balances: {...} }; },
         onSuccess:  function (payload) { ... },   // payload = AJAX response
         flashError: function (msg) { ... },       // optional; default = alert
         csrfToken:  string,
         csrfName:   string                   // form-field name, e.g. '_csrf'
     });
   ============================================================ */

(function ($) {
    'use strict';

    if (typeof $ === 'undefined') return;

    // ---- shared formatting helpers ------------------------------------------

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

    // Treat "0" / "0.00" / blank as "this side unused".
    function payloadAmount($field) {
        var v = String($field.val()).trim();
        var n = parseFloat(v);
        return (v === '' || isNaN(n) || n === 0) ? '' : v;
    }

    // ---- constructor --------------------------------------------------------

    function TrxRowActions(opts) {
        if (!window.bootstrap || !window.TrxCombobox) {
            console.error('TrxRowActions: bootstrap + TrxCombobox must be loaded first.');
            return;
        }

        this.opts = $.extend({
            base:       '',
            masters:    [],
            getState:   function () { return { rows: [], balances: {} }; },
            onSuccess:  function () {},
            flashError: function (msg) { window.alert(msg); },
            csrfToken:  '',
            csrfName:   '_csrf'
        }, opts || {});

        // Internal context for the currently-open edit modal.
        // Stored so baselineForMaster can correctly subtract this row's
        // contribution when computing "current balance".
        this.editCtx   = { id: 0, oldMasterId: 0, oldCr: 0, oldDr: 0 };
        this.deleteCtx = { id: 0 };

        this._initRefs();
        this._initCombobox();
        this._wireEditFields();
        this._wireDelegates();
        this._wireSubmits();
    }

    // ---- setup --------------------------------------------------------------

    TrxRowActions.prototype._initRefs = function () {
        this.$editForm    = $('#editForm');
        this.$editCr      = $('#editCr');
        this.$editDr      = $('#editDr');
        this.$editRemark  = $('#editRemark');
        this.$editDate    = $('#editTrxDate');
        this.$editBalCur  = $('#editBalanceCurrent');
        this.$editBalAft  = $('#editBalanceAfter');
        this.$editSubmit  = $('#editSubmit');

        this.$deleteForm   = $('#deleteForm');
        this.$deleteSubmit = $('#deleteSubmit');

        this.editModal   = new bootstrap.Modal(document.getElementById('editModal'));
        this.deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
    };

    TrxRowActions.prototype._initCombobox = function () {
        var self = this;
        this.combo = new TrxCombobox({
            inputEl:  document.getElementById('editMasterInput'),
            hiddenEl: document.getElementById('editMasterId'),
            listEl:   document.getElementById('editMasterList'),
            masters:  this.opts.masters,
            onSelect: function () { self._refreshBalances(); }
        });
    };

    TrxRowActions.prototype._wireEditFields = function () {
        var self = this;

        // Mutual exclusion (no disable) — type a real value in one side, the
        // other auto-zeros. The "0" is a visible "unused" indicator.
        function enforceExclusion(justChanged) {
            var crNum = parseFloat(self.$editCr.val()) || 0;
            var drNum = parseFloat(self.$editDr.val()) || 0;
            if (justChanged === 'cr' && crNum > 0 && drNum !== 0) self.$editDr.val('0');
            if (justChanged === 'dr' && drNum > 0 && crNum !== 0) self.$editCr.val('0');
        }

        this.$editCr.add(this.$editDr).on('focus', function () {
            var el = this;
            setTimeout(function () { el.select(); }, 0);
        });

        this.$editCr.on('input', function () {
            this.value = sanitizeMoneyInput(this.value);
            enforceExclusion('cr');
            self._refreshBalances();
        });
        this.$editDr.on('input', function () {
            this.value = sanitizeMoneyInput(this.value);
            enforceExclusion('dr');
            self._refreshBalances();
        });
    };

    TrxRowActions.prototype._wireDelegates = function () {
        var self = this;

        // Delegate from document.body so the handlers survive any number of
        // table re-renders on either page (rows are re-created from JSON).
        $(document.body).on('click', '.trx-edit-btn', function () {
            var id = parseInt($(this).closest('tr').attr('data-id'), 10);
            if (id) self.openEdit(id);
        });

        $(document.body).on('click', '.trx-delete-btn', function () {
            var $row = $(this).closest('tr');
            self.openDelete(
                parseInt($row.attr('data-id'), 10),
                $row.attr('data-trx-id'),
                $row.attr('data-master-name')
            );
        });
    };

    TrxRowActions.prototype._wireSubmits = function () {
        var self = this;

        this.$editForm.on('submit', function (ev) {
            ev.preventDefault();
            self._clearEditErrors();

            var payload = {
                trx_date:  self.$editDate.val(),
                master_id: $('#editMasterId').val(),
                cr:        payloadAmount(self.$editCr),
                dr:        payloadAmount(self.$editDr),
                remark:    self.$editRemark.val()
            };

            self.$editSubmit.prop('disabled', true).text('Saving…');
            self._ajaxPost('/' + self.editCtx.id + '/update', payload)
                .done(function (resp) {
                    self.opts.onSuccess(resp);
                    self.editModal.hide();
                })
                .fail(function (xhr) {
                    var resp = xhr.responseJSON || {};
                    if (resp.errors) self._applyEditErrors(resp.errors);
                    else             self.opts.flashError(resp.message || 'Update failed.');
                })
                .always(function () {
                    self.$editSubmit.prop('disabled', false).text('Save changes');
                });
        });

        this.$deleteForm.on('submit', function (ev) {
            ev.preventDefault();
            self.$deleteSubmit.prop('disabled', true).text('Deleting…');

            self._ajaxPost('/' + self.deleteCtx.id + '/delete', {})
                .done(function (resp) {
                    self.opts.onSuccess(resp);
                    self.deleteModal.hide();
                })
                .fail(function (xhr) {
                    var resp = xhr.responseJSON || {};
                    self.opts.flashError(resp.message || 'Delete failed.');
                })
                .always(function () {
                    self.$deleteSubmit.prop('disabled', false).text('Delete');
                });
        });
    };

    // ---- balance + error helpers -------------------------------------------

    // Baseline = master's current balance EXCLUDING the row being edited.
    // Matches the "current balance" meaning used on the add form.
    TrxRowActions.prototype._baselineForMaster = function (masterId) {
        if (!masterId) return null;
        var st  = this.opts.getState();
        var bal = (st.balances && st.balances.hasOwnProperty(masterId))
                    ? Number(st.balances[masterId]) : 0;
        if (masterId === this.editCtx.oldMasterId) {
            bal -= (Number(this.editCtx.oldDr) - Number(this.editCtx.oldCr));
        }
        return bal;
    };

    TrxRowActions.prototype._refreshBalances = function () {
        var mId  = parseInt($('#editMasterId').val(), 10) || 0;
        var base = this._baselineForMaster(mId);

        if (base === null) {
            this.$editBalCur.text('—').removeClass('text-danger text-success');
            this.$editBalAft.text('—').removeClass('text-danger text-success');
            return;
        }
        this.$editBalCur.text(fmtMoney(base));
        styleAmount(this.$editBalCur, base);

        var cr    = parseFloat(this.$editCr.val()) || 0;
        var dr    = parseFloat(this.$editDr.val()) || 0;
        var after = base + dr - cr;
        this.$editBalAft.text(fmtMoney(after));
        styleAmount(this.$editBalAft, after);
    };

    TrxRowActions.prototype._clearEditErrors = function () {
        this.$editForm.find('.is-invalid').removeClass('is-invalid');
        $('#editTrxDateErr,#editMasterErr,#editCrErr,#editDrErr,#editRemarkErr').text('');
    };

    TrxRowActions.prototype._applyEditErrors = function (errors) {
        this._clearEditErrors();
        Object.keys(errors || {}).forEach(function (field) {
            var msg = (errors[field] || []).join(' ');
            if (field === 'trx_date') {
                $('#editTrxDate').addClass('is-invalid');
                $('#editTrxDateErr').text(msg);
            } else if (field === 'master_id') {
                $('#editMasterInput').addClass('is-invalid');
                $('#editMasterErr').text(msg);
            } else if (field === 'cr') {
                $('#editCr').addClass('is-invalid');
                $('#editCrErr').text(msg);
            } else if (field === 'dr') {
                $('#editDr').addClass('is-invalid');
                $('#editDrErr').text(msg);
            } else if (field === 'remark') {
                $('#editRemark').addClass('is-invalid');
                $('#editRemarkErr').text(msg);
            }
        });
    };

    // ---- AJAX wrapper -------------------------------------------------------

    TrxRowActions.prototype._ajaxPost = function (path, data) {
        var payload = $.extend({}, data || {});
        payload[this.opts.csrfName] = this.opts.csrfToken;

        return $.ajax({
            url:      this.opts.base + path,
            method:   'POST',
            data:     payload,
            dataType: 'json',
            headers:  { 'X-Requested-With': 'XMLHttpRequest' }
        });
    };

    // ---- public open methods -----------------------------------------------

    TrxRowActions.prototype.openEdit = function (rowId) {
        var st  = this.opts.getState();
        var row = (st.rows || []).find(function (r) { return r.id === rowId; });
        if (!row) return;

        this.editCtx = {
            id:          row.id,
            oldMasterId: row.master_id,
            oldCr:       row.cr === null ? 0 : Number(row.cr),
            oldDr:       row.dr === null ? 0 : Number(row.dr)
        };

        $('#editTrxIdNum').text(row.trx_id);
        this.$editDate.val(row.trx_date);
        this.combo.setValue(row.master_id);

        // Bible: show "0" in the unused side so it's explicit which one is empty.
        this.$editCr.val(row.cr !== null ? row.cr : '0');
        this.$editDr.val(row.dr !== null ? row.dr : '0');
        this.$editRemark.val(row.remark || '');

        this._clearEditErrors();
        this._refreshBalances();
        this.editModal.show();
    };

    TrxRowActions.prototype.openDelete = function (rowId, trxIdDisplay, masterName) {
        this.deleteCtx.id = rowId;
        $('#deleteTrxId').text(trxIdDisplay || '—');
        $('#deleteMaster').text(masterName || '—');
        this.deleteModal.show();
    };

    // Refresh the underlying master list (e.g. when a new master was added
    // since the page loaded). Rarely needed in this app.
    TrxRowActions.prototype.setMasters = function (masters) {
        this.opts.masters = masters || [];
        if (this.combo && this.combo.setMasters) this.combo.setMasters(masters);
    };

    // Re-export the formatting helpers so pages can reuse them without
    // duplicating the implementation in their own inline script.
    TrxRowActions.fmtMoney    = fmtMoney;
    TrxRowActions.styleAmount = styleAmount;

    window.TrxRowActions = TrxRowActions;
})(window.jQuery);
