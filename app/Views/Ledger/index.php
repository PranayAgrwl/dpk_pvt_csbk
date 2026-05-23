<?php
/**
 * View: app/Views/Ledger/index.php
 * ------------------------------------------------------------
 * Ledger module — landing page.
 *
 * Per bible step 5:
 *   1. #               (serial number, not id)
 *   2. Name            (alphabetical) with station in small underneath
 *   3. Current balance (per-master SUM(dr) − SUM(cr))
 *   4. Button → opens the complete per-master ledger (/ledger/{id})
 *
 * Locals provided by LedgerController::index():
 *   $rows  array<int,{id,name,station,balance}>  — sorted by name ASC
 *
 * Pure server-render — no JS. Buttons are plain anchor links to the
 * per-master ledger page (which is where the live editing happens).
 * ------------------------------------------------------------
 */
include APP_BASE . '/app/Views/partials/header.php';

// Local helper to colour a balance value (matches the convention used on /trx).
$balanceClass = static function (float $n): string {
    if ($n < 0) return 'text-danger';
    if ($n > 0) return 'text-success';
    return 'text-muted';
};

// Local money formatter (small enough to inline; mirrors fmtMoney in JS).
$fmtMoney = static function (float $n): string {
    return number_format($n, 2, '.', ',');
};
?>

<div class="d-flex justify-content-between align-items-end mb-4 flex-wrap gap-2">
    <div>
        <h1 class="h3 fw-bold mb-1">Ledger</h1>
        <p class="text-muted mb-0 small">
            All masters with their current balance &middot; click <span class="fw-semibold">View</span> for full history
        </p>
    </div>
</div>

<?php if (empty($rows)): ?>
    <div class="card border-0 shadow-sm">
        <div class="card-body text-center py-5">
            <p class="text-muted mb-3">No masters yet — add one to start building ledgers.</p>
            <a href="<?= $e($url('/master')) ?>" class="btn btn-outline-primary btn-sm">Go to Master</a>
        </div>
    </div>
<?php else: ?>
    <div class="table-responsive shadow-sm rounded bg-white">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th scope="col" class="text-muted small" style="width:70px;">#</th>
                    <th scope="col">Name</th>
                    <th scope="col" class="text-end" style="width:160px;">Current balance</th>
                    <th scope="col" class="text-end" style="width:110px;">&nbsp;</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $i => $row): ?>
                    <tr>
                        <td class="text-muted small"><?= (int) ($i + 1) ?></td>
                        <td>
                            <div class="fw-semibold"><?= $e($row['name']) ?></div>
                            <?php if ($row['station'] !== ''): ?>
                                <div class="text-muted small"><?= $e($row['station']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td class="text-end text-nowrap fw-semibold <?= $e($balanceClass((float) $row['balance'])) ?>">
                            <?= $e($fmtMoney((float) $row['balance'])) ?>
                        </td>
                        <td class="text-end">
                            <a href="<?= $e($url('/ledger/' . (int) $row['id'])) ?>"
                               class="btn btn-sm btn-outline-secondary">
                                View &rarr;
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<?php include APP_BASE . '/app/Views/partials/footer.php'; ?>
