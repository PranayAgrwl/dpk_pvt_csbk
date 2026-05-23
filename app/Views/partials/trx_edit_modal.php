<?php
/**
 * Partial: partials/trx_edit_modal.php
 * ------------------------------------------------------------
 * Shared edit-transaction modal — included by /trx (index.php) and
 * /ledger/{id} (show.php).
 *
 * The HTML here is purely structural; behaviour is wired up by
 * public/assets/js/trx-row-actions.js, which expects these element IDs
 * to be present on the page.
 *
 * Requires the view's locals to include:
 *   $csrfField   — pre-rendered hidden CSRF input (from View::defaultData)
 * ------------------------------------------------------------
 */
?>
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

                    <!-- Read-only summary: current → after-save balance. -->
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
