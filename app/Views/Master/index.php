<?php
/**
 * View: app/Views/Master/index.php
 * ------------------------------------------------------------
 * Master CRUD page.
 *
 * Locals provided by MasterController::index():
 *   $rows        array<int, array{id,name,station,remark,created_at,updated_at}>
 *   $errors      validation errors keyed by field (flashed after a failed POST)
 *   $old         old form values (flashed after a failed POST)
 *   $editing_id  if a previous UPDATE failed, the row id being edited (reopens modal)
 *
 * UX:
 *   - One "Add" button at the top opens a shared Add/Edit modal.
 *   - Each row has Edit and Delete buttons.
 *   - Edit opens the same modal pre-filled (data-* attributes carry the row data).
 *   - Delete opens a small confirmation modal whose <form> is wired with the row id.
 *   - On validation failure the modal auto-reopens with old values so users
 *     don't lose what they typed.
 *
 * Note on data-* attributes: $e() (htmlspecialchars) is used everywhere any
 * row value lands in HTML — covers XSS through "name", "station" or "remark".
 * ------------------------------------------------------------
 */
include APP_BASE . '/app/Views/partials/header.php';

// Convenience aliases — keeps the template compact.
$base       = $url('/master');
$nameErr    = $errors['name']    ?? [];
$stationErr = $errors['station'] ?? [];
$remarkErr  = $errors['remark']  ?? [];
$hasErrors  = !empty($errors) || !empty($old);
?>

<div class="d-flex justify-content-between align-items-end mb-4 flex-wrap gap-2">
    <div>
        <h1 class="h3 fw-bold mb-1">Master</h1>
        <!-- <p class="text-muted mb-0 small">Clients &mdash; customers &amp; suppliers</p> -->
    </div>
    <button type="button"
            class="btn btn-primary"
            data-bs-toggle="modal"
            data-bs-target="#masterModal"
            data-mode="add">
        <span aria-hidden="true">+</span> Add Entry
    </button>
</div>

<?php if (empty($rows)): ?>
    <div class="card border-0 shadow-sm">
        <div class="card-body text-center py-5">
            <p class="text-muted mb-3 mb-md-2">No entries yet.</p>
            <p class="text-muted small mb-4">
                Click <span class="fw-semibold">&ldquo;Add Entry&rdquo;</span> above to create your first one.
            </p>
            <button type="button"
                    class="btn btn-outline-primary btn-sm"
                    data-bs-toggle="modal"
                    data-bs-target="#masterModal"
                    data-mode="add">
                + Add Entry
            </button>
        </div>
    </div>
<?php else: ?>
    <div class="table-responsive shadow-sm rounded bg-white">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th scope="col" class="text-muted small" style="width:70px;">#</th>
                    <th scope="col">Name</th>
                    <th scope="col">Station</th>
                    <th scope="col">Remark</th>
                    <th scope="col" class="text-end" style="width:170px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $i => $row): ?>
                    <tr>
                        <td class="text-muted small"><?= (int) ($i + 1) ?></td>
                        <td class="fw-semibold"><?= $e($row['name']) ?></td>
                        <td><?= $e($row['station'] ?? '') ?: '<span class="text-muted">&mdash;</span>' ?></td>
                        <td><?= $e($row['remark']  ?? '') ?: '<span class="text-muted">&mdash;</span>' ?></td>
                        <td class="text-end">
                            <button type="button"
                                    class="btn btn-sm btn-outline-secondary"
                                    data-bs-toggle="modal"
                                    data-bs-target="#masterModal"
                                    data-mode="edit"
                                    data-id="<?= (int) $row['id'] ?>"
                                    data-name="<?= $e($row['name']) ?>"
                                    data-station="<?= $e($row['station'] ?? '') ?>"
                                    data-remark="<?= $e($row['remark'] ?? '') ?>">
                                Edit
                            </button>
                            <button type="button"
                                    class="btn btn-sm btn-outline-danger"
                                    data-bs-toggle="modal"
                                    data-bs-target="#deleteModal"
                                    data-id="<?= (int) $row['id'] ?>"
                                    data-name="<?= $e($row['name']) ?>">
                                Delete
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>


<!-- ===== Add / Edit modal (reused for both) ===== -->
<div class="modal fade" id="masterModal" tabindex="-1" aria-labelledby="masterModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="masterForm" method="POST" action="<?= $e($base . '/store') ?>" autocomplete="off" novalidate>
                <?= $csrfField ?>
                <div class="modal-header">
                    <h5 class="modal-title" id="masterModalTitle">Add Entry</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="masterName" class="form-label">
                            Name <span class="text-danger">*</span>
                        </label>
                        <input type="text"
                               class="form-control <?= $nameErr ? 'is-invalid' : '' ?>"
                               id="masterName" name="name"
                               maxlength="255" required autofocus>
                        <?php foreach ($nameErr as $msg): ?>
                            <div class="invalid-feedback"><?= $e($msg) ?></div>
                        <?php endforeach; ?>
                    </div>

                    <div class="mb-3">
                        <label for="masterStation" class="form-label">Station</label>
                        <input type="text"
                               class="form-control <?= $stationErr ? 'is-invalid' : '' ?>"
                               id="masterStation" name="station" maxlength="255">
                        <?php foreach ($stationErr as $msg): ?>
                            <div class="invalid-feedback"><?= $e($msg) ?></div>
                        <?php endforeach; ?>
                    </div>

                    <div class="mb-3">
                        <label for="masterRemark" class="form-label">Remark</label>
                        <input type="text"
                               class="form-control <?= $remarkErr ? 'is-invalid' : '' ?>"
                               id="masterRemark" name="remark" maxlength="255">
                        <?php foreach ($remarkErr as $msg): ?>
                            <div class="invalid-feedback"><?= $e($msg) ?></div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="masterSubmit">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>


<!-- ===== Delete-confirmation modal ===== -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <form id="deleteForm" method="POST" action="" novalidate>
                <?= $csrfField ?>
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteModalTitle">Delete entry?</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-0">
                        Are you sure you want to delete
                        <strong id="deleteName" class="d-inline-block text-truncate" style="max-width: 200px; vertical-align: bottom;">this entry</strong>?
                    </p>
                    <p class="text-muted small mb-0 mt-2">This action cannot be undone.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Delete</button>
                </div>
            </form>
        </div>
    </div>
</div>


<!-- ===== Page-specific JS (modal wiring) =====
     IMPORTANT: this script must wait for jQuery + Bootstrap to load.
     They are included at the bottom of footer.php (after this view), so we
     defer all setup until DOMContentLoaded — by that time the vendor
     scripts have been parsed and executed, and window.jQuery is defined. -->
<script>
document.addEventListener('DOMContentLoaded', function () {
    'use strict';

    // Safety net: if jQuery / Bootstrap somehow failed to load, bail loudly
    // rather than silently misbehaving (which was the cause of the delete-404 bug).
    if (!window.jQuery || !window.bootstrap) {
        console.error('Master page: jQuery or Bootstrap not loaded — modal wiring skipped.');
        return;
    }
    var $ = window.jQuery;

    var BASE = <?= json_encode($base, JSON_UNESCAPED_SLASHES) ?>;

    // Cached DOM refs.
    var $form     = $('#masterForm');
    var $title    = $('#masterModalTitle');
    var $submit   = $('#masterSubmit');
    var $name     = $('#masterName');
    var $station  = $('#masterStation');
    var $remark   = $('#masterRemark');

    // Wire the SHARED add/edit modal. Mode + row data come from the trigger
    // button via data-* attributes.
    $('#masterModal').on('show.bs.modal', function (event) {
        var btn  = $(event.relatedTarget);
        var mode = btn.data('mode') || 'add';

        if (mode === 'edit') {
            $title.text('Edit Entry');
            $submit.text('Save changes');
            $form.attr('action', BASE + '/' + btn.data('id') + '/update');
            $name.val(btn.data('name') || '');
            $station.val(btn.data('station') || '');
            $remark.val(btn.data('remark') || '');
        } else {
            $title.text('Add Entry');
            $submit.text('Save');
            $form.attr('action', BASE + '/store');
            $name.val('');
            $station.val('');
            $remark.val('');
        }
        // Drop stale validation styling from the previous open.
        $form.find('.is-invalid').removeClass('is-invalid');
    });

    // Wire the DELETE modal — point its form at /master/{id}/delete and show the name.
    $('#deleteModal').on('show.bs.modal', function (event) {
        var btn = $(event.relatedTarget);
        $('#deleteForm').attr('action', BASE + '/' + btn.data('id') + '/delete');
        $('#deleteName').text(btn.data('name') || 'this entry');
    });

    <?php if ($hasErrors): ?>
    // A POST just failed validation — re-open the modal with the user's input
    // restored so they don't have to retype everything.
    $title.text(<?= json_encode($editing_id ? 'Edit Entry' : 'Add Entry') ?>);
    $submit.text(<?= json_encode($editing_id ? 'Save changes' : 'Save') ?>);
    <?php if ($editing_id): ?>
    $form.attr('action', BASE + '/' + <?= (int) $editing_id ?> + '/update');
    <?php else: ?>
    $form.attr('action', BASE + '/store');
    <?php endif; ?>
    $name.val(<?= json_encode($old['name']    ?? '') ?>);
    $station.val(<?= json_encode($old['station'] ?? '') ?>);
    $remark.val(<?= json_encode($old['remark']  ?? '') ?>);
    var m = bootstrap.Modal.getOrCreateInstance(document.getElementById('masterModal'));
    m.show();
    <?php endif; ?>
});
</script>

<?php include APP_BASE . '/app/Views/partials/footer.php'; ?>
