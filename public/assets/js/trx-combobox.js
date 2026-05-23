/* ============================================================
   trx-combobox.js
   ------------------------------------------------------------
   Reusable MS-Access-style master picker.

   Used by:
     - app/Views/Trx/create.php   (new-transaction form)
     - app/Views/Trx/index.php    (edit-transaction modal)

   Behaviour (per trx bible step 1):
     - Visible text input + hidden id input.
     - Filter is "starts-with" (case-insensitive) on master name only.
         "tr" matches "truffle" but NOT "strawberries".
     - Alphabetical (callers pass an already-sorted list).
     - Alt+ArrowDown  → open the full list (overrides typed filter).
     - ArrowUp/Down   → move highlight.
     - Enter          → commit highlighted item (and trigger onSelect).
     - Tab            → commit highlighted item, then let Tab through.
     - Escape         → close the list.
     - Click on item  → commit it.
     - Station shown muted on the right of each row (visual only,
       NOT part of the filter — bible explicit).

   Public API:
     var combo = new TrxCombobox({
         inputEl, hiddenEl, listEl,    // raw DOM nodes
         masters,                       // [{id, name, station}, ...] sorted by name ASC
         onSelect: function (master) {} // called when a master is committed
     });
     combo.setValue(masterId);   // pre-select by id (no onSelect fired)
     combo.clear();              // wipe input + hidden + close list
     combo.focus();              // focus the visible input
   ============================================================ */

(function ($) {
    'use strict';

    if (typeof $ === 'undefined') {
        // jQuery required — every page that loads this file already has it.
        return;
    }

    function TrxCombobox(opts) {
        this.masters  = opts.masters  || [];
        this.onSelect = opts.onSelect || function () {};

        this.$input  = $(opts.inputEl);
        this.$hidden = $(opts.hiddenEl);
        this.$list   = $(opts.listEl);

        // Internal state.
        this.highlighted = -1;     // index into `visible`, or -1 when none
        this.visible     = [];     // array of master objects currently rendered

        this._bind();
    }

    // ---- internal helpers ---------------------------------------------------

    // Filter masters by a "starts-with" match on name (case-insensitive).
    TrxCombobox.prototype._filter = function (query) {
        var q = String(query || '').trim().toLowerCase();
        if (q === '') {
            return this.masters.slice();          // empty query → everything
        }
        return this.masters.filter(function (m) {
            return m.name.toLowerCase().indexOf(q) === 0;
        });
    };

    // Build the dropdown <li> items from a filtered array.
    TrxCombobox.prototype._render = function (items) {
        this.$list.empty();
        this.visible = items;

        if (items.length === 0) {
            this.$list.append(
                $('<li>', {
                    'class': 'list-group-item text-muted small',
                    'aria-disabled': 'true',
                    text: 'No matches'
                })
            );
            return;
        }

        items.forEach(function (m, idx) {
            // Each <li>: name (left) + station (right, muted).
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
            this.$list.append($li);
        }.bind(this));
    };

    TrxCombobox.prototype._open = function (items, highlightIdx) {
        this._render(items);
        this.$list.prop('hidden', false);
        this.$input.attr('aria-expanded', 'true');
        this._setHighlight(typeof highlightIdx === 'number' ? highlightIdx : 0);
    };

    TrxCombobox.prototype._close = function () {
        this.$list.prop('hidden', true);
        this.$input.attr('aria-expanded', 'false');
        this.highlighted = -1;
    };

    TrxCombobox.prototype._setHighlight = function (idx) {
        if (!this.visible.length) { this.highlighted = -1; return; }
        if (idx < 0)                       idx = 0;
        if (idx >= this.visible.length)    idx = this.visible.length - 1;
        this.highlighted = idx;

        var $items = this.$list.children('[data-idx]');
        $items.removeClass('active');
        var $cur = $items.eq(idx).addClass('active');

        // Scroll the highlighted row into view if it drifts past the visible box.
        if ($cur.length) {
            var li  = $cur[0];
            var box = this.$list[0];
            var top = li.offsetTop;
            var bot = top + li.offsetHeight;
            if (top < box.scrollTop)                         box.scrollTop = top;
            else if (bot > box.scrollTop + box.clientHeight) box.scrollTop = bot - box.clientHeight;
        }
    };

    // Commit a master selection: fills inputs, fires onSelect, closes list.
    // The second arg lets internal helpers (setValue) suppress onSelect.
    TrxCombobox.prototype._commit = function (m, silent) {
        if (!m) return;
        this.$input.val(m.name);
        this.$hidden.val(m.id);
        this._close();
        if (!silent) this.onSelect(m);
    };

    // ---- event wiring -------------------------------------------------------

    TrxCombobox.prototype._bind = function () {
        var self = this;

        this.$input.on('input.trxcombo', function () {
            // Typing invalidates any prior selection until user commits a new one.
            self.$hidden.val('');
            self.onSelect(null);                   // tell host "no master right now"
            self._open(self._filter(this.value), 0);
        });

        this.$input.on('focus.trxcombo', function () {
            if (self.$list.prop('hidden')) {
                self._open(self._filter(this.value), 0);
            }
        });

        this.$input.on('keydown.trxcombo', function (ev) {
            // Alt+ArrowDown ALWAYS opens the full list, ignoring typed text.
            if (ev.altKey && ev.key === 'ArrowDown') {
                ev.preventDefault();
                self._open(self.masters.slice(), 0);
                return;
            }

            switch (ev.key) {
                case 'ArrowDown':
                    ev.preventDefault();
                    if (self.$list.prop('hidden')) {
                        self._open(self._filter(self.$input.val()), 0);
                    } else {
                        self._setHighlight(self.highlighted + 1);
                    }
                    break;

                case 'ArrowUp':
                    ev.preventDefault();
                    if (!self.$list.prop('hidden')) {
                        self._setHighlight(self.highlighted - 1);
                    }
                    break;

                case 'Enter':
                    // If list is open with a highlighted item, commit it
                    // and prevent accidental form submission.
                    if (!self.$list.prop('hidden') && self.highlighted >= 0 && self.visible[self.highlighted]) {
                        ev.preventDefault();
                        self._commit(self.visible[self.highlighted]);
                    }
                    break;

                case 'Escape':
                    if (!self.$list.prop('hidden')) {
                        ev.preventDefault();
                        self._close();
                    }
                    break;

                case 'Tab':
                    // Auto-commit highlighted on Tab (MS-Access feel) and let Tab through.
                    if (!self.$list.prop('hidden') && self.highlighted >= 0 && self.visible[self.highlighted]) {
                        self._commit(self.visible[self.highlighted]);
                    }
                    break;
            }
        });

        // mousedown (not click) so the input's `blur` doesn't fire first and
        // tear down the list before we can read which item was tapped.
        this.$list.on('mousedown.trxcombo', '[data-idx]', function (ev) {
            ev.preventDefault();
            var idx = parseInt($(this).attr('data-idx'), 10);
            if (!isNaN(idx) && self.visible[idx]) {
                self._commit(self.visible[idx]);
            }
        });

        // Outside click: auto-commit an exact match if typed, else just close.
        // Scoped to the combobox's own container — multiple comboboxes on a page
        // shouldn't interfere with each other.
        this._docHandler = function (ev) {
            // If the click is inside THIS combobox (its input or list), leave it alone.
            if ($.contains(self.$input.parent()[0] || document, ev.target) ||
                ev.target === self.$input[0] ||
                ev.target === self.$list[0]   ||
                $.contains(self.$list[0], ev.target)) {
                return;
            }
            if (self.$hidden.val() === '') {
                var typed = String(self.$input.val() || '').trim().toLowerCase();
                if (typed !== '') {
                    var exact = self.masters.find(function (m) {
                        return m.name.toLowerCase() === typed;
                    });
                    if (exact) self._commit(exact);
                }
            }
            self._close();
        };
        $(document).on('mousedown.trxcombo', this._docHandler);
    };

    // ---- public API ---------------------------------------------------------

    // Pre-select a master by id (silent — does NOT fire onSelect).
    TrxCombobox.prototype.setValue = function (masterId) {
        var m = this.masters.find(function (x) { return Number(x.id) === Number(masterId); });
        if (m) {
            this.$input.val(m.name);
            this.$hidden.val(m.id);
        } else {
            this.clear();
        }
    };

    TrxCombobox.prototype.clear = function () {
        this.$input.val('');
        this.$hidden.val('');
        this._close();
    };

    TrxCombobox.prototype.focus = function () {
        this.$input.trigger('focus');
    };

    // Refresh the underlying master list (e.g. after a fresh AJAX fetch).
    TrxCombobox.prototype.setMasters = function (masters) {
        this.masters = masters || [];
    };

    // Detach event handlers (rarely needed; useful for SPA teardown).
    TrxCombobox.prototype.destroy = function () {
        this.$input.off('.trxcombo');
        this.$list.off('.trxcombo');
        $(document).off('mousedown.trxcombo', this._docHandler);
    };

    window.TrxCombobox = TrxCombobox;
})(window.jQuery);
