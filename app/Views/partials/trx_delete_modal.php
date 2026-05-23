<?php
/**
 * Partial: partials/trx_delete_modal.php
 * ------------------------------------------------------------
 * Shared delete-transaction confirmation modal — included by /trx and
 * /ledger/{id}. Behaviour wired up by trx-row-actions.js.
 *
 * Requires the view's locals to include:
 *   $csrfField   — pre-rendered hidden CSRF input
 * ------------------------------------------------------------
 */
?>
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
