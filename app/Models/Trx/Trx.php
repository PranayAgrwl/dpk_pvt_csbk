<?php
/**
 * Trx model
 * ------------------------------------------------------------
 * Represents one row in the `trx` (cashbook transactions) table.
 *
 * Columns: id, master_id, trx_date, trx_id, cr, dr, remark, created_at, updated_at
 *
 * Per the trx bible:
 *   - Exactly ONE of `cr` / `dr` is populated per row (the other stays NULL).
 *   - `trx_id` is a user-facing ordering integer; the next one is just
 *     MAX(trx_id) + 1 (COALESCEd to 0 when the table is empty).
 *   - Balance per master = SUM(cr) - SUM(dr).
 *
 * The base App\Core\Model already provides find(), create(), updateById().
 * We override all() to sort by `trx_id ASC` and add a few small helpers
 * used by the create form.
 * ------------------------------------------------------------
 */

namespace App\Models\Trx;

use App\Core\Database;
use App\Core\Model;

class Trx extends Model
{
    /** @var string Underlying table name (used by parent Model helpers). */
    protected static string $table = 'trx';

    /**
     * Return every transaction row sorted by trx_id ASC (per bible).
     * Secondary `id ASC` is a stable tiebreaker if two rows share trx_id.
     *
     * @return array<int, array<string,mixed>>
     */
    public static function all(): array
    {
        return Database::queryAll(
            "SELECT * FROM trx ORDER BY trx_id ASC, id ASC"
        );
    }

    /**
     * Compute the next `trx_id` to assign on INSERT.
     * Empty table → 1; otherwise MAX(trx_id) + 1.
     */
    public static function nextTrxId(): int
    {
        $row = Database::queryOne(
            "SELECT COALESCE(MAX(trx_id), 0) + 1 AS next_id FROM trx"
        );
        return (int) ($row['next_id'] ?? 1);
    }

    /**
     * Current balance for every master that has ever appeared in trx.
     * Formula (classic cashbook): SUM(dr) - SUM(cr)
     *   - dr (debit)  = receipts, money IN
     *   - cr (credit) = payments, money OUT
     * Positive balance ⇒ net receipts from that master so far.
     *
     * Returned as { master_id => balance_as_float }.
     * Masters with no transactions are simply absent from the map; callers
     * default missing entries to 0.0.
     *
     * @return array<int, float>
     */
    public static function balancesByMaster(): array
    {
        $rows = Database::queryAll(
            "SELECT master_id,
                    COALESCE(SUM(dr), 0) - COALESCE(SUM(cr), 0) AS balance
               FROM trx
              GROUP BY master_id"
        );

        $out = [];
        foreach ($rows as $r) {
            $out[(int) $r['master_id']] = (float) $r['balance'];
        }
        return $out;
    }

    /**
     * List every transaction joined with its master (name + station), already
     * sorted by trx_id ASC (which is the natural "running balance" order).
     *
     * Each row also carries a `running_balance` field — the cumulative
     * SUM(dr - cr) for all rows up to AND INCLUDING this one (cash on hand
     * after the row was applied). Computed in PHP after fetch.
     *
     * @return array<int, array<string,mixed>>
     */
    public static function listWithMaster(): array
    {
        $rows = Database::queryAll(
            "SELECT t.id, t.master_id, t.trx_date, t.trx_id,
                    t.cr, t.dr, t.remark, t.created_at, t.updated_at,
                    m.name    AS master_name,
                    m.station AS master_station
               FROM trx t
               INNER JOIN master m ON m.id = t.master_id
              ORDER BY t.trx_id ASC, t.id ASC"
        );

        // Walk in ascending trx_id order and accumulate the cashbook balance.
        $running = 0.0;
        foreach ($rows as &$r) {
            $running += (float) ($r['dr'] ?? 0) - (float) ($r['cr'] ?? 0);
            $r['running_balance'] = $running;
        }
        unset($r);

        return $rows;
    }

    /**
     * Update editable fields of an existing transaction.
     * Per bible (step 4): editable fields = trx_date, master_id, cr, dr, remark.
     * trx_id is NOT editable here — it moves via drag-reorder only.
     *
     * Exactly one of `cr` / `dr` must be non-null in the caller's data.
     *
     * @param int                  $id   Row primary key.
     * @param array<string,mixed>  $data ['trx_date'=>string,'master_id'=>int,'cr'=>?float,'dr'=>?float,'remark'=>?string]
     * @return int affected rows
     */
    public static function updateFields(int $id, array $data): int
    {
        return self::updateById($id, [
            'trx_date'   => (string) $data['trx_date'],
            'master_id'  => (int) $data['master_id'],
            'cr'         => $data['cr'],                // one of these
            'dr'         => $data['dr'],                // is NULL
            'remark'     => $data['remark'],            // already uppercased/null
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Delete a transaction and renumber everything below it so that trx_id
     * stays contiguous (1..N with no gaps).
     *
     * Per bible: "if entry from between deleted - all entries below it move trx_id -1".
     *
     * Wrapped in a DB transaction so a partial failure can never leave the
     * sequence half-shifted.
     *
     * @return bool true when a row was actually deleted, false when not found.
     */
    public static function deleteAndRenumber(int $id): bool
    {
        return Database::transaction(function () use ($id): bool {
            $row = Database::queryOne("SELECT trx_id FROM trx WHERE id = ? LIMIT 1", [$id]);
            if ($row === null) {
                return false;
            }
            $deletedTrxId = (int) $row['trx_id'];

            Database::execute("DELETE FROM trx WHERE id = ?", [$id]);

            // Shift everyone below by -1 to keep the sequence dense.
            Database::execute(
                "UPDATE trx
                    SET trx_id = trx_id - 1,
                        updated_at = NOW()
                  WHERE trx_id > ?",
                [$deletedTrxId]
            );

            return true;
        });
    }

    /**
     * Move a row to a new trx_id (drag-and-drop reorder) and shift the rows
     * that fall in the path to keep the sequence dense and consistent.
     *
     * Semantics (per bible):
     *   - Drag a row to position N → that row becomes trx_id = N.
     *   - When N < old (moving UP in the sequence): rows in [N, old-1] get +1.
     *   - When N > old (moving DOWN in the sequence): rows in [old+1, N] get -1.
     *
     * Returns the row's new trx_id, or null when the source id doesn't exist.
     *
     * Note: trx_id has no UNIQUE constraint so the intermediate shifts never
     * collide. Still wrapped in a transaction for atomicity.
     */
    public static function reorder(int $id, int $newTrxId): ?int
    {
        return Database::transaction(function () use ($id, $newTrxId): ?int {
            $row = Database::queryOne("SELECT trx_id FROM trx WHERE id = ? LIMIT 1", [$id]);
            if ($row === null) {
                return null;
            }
            $oldTrxId = (int) $row['trx_id'];

            // Clamp newTrxId into the valid range [1, totalRows].
            $totalRow  = Database::queryOne("SELECT COUNT(*) AS c FROM trx");
            $total     = (int) ($totalRow['c'] ?? 0);
            if ($total === 0) {
                return $oldTrxId;
            }
            if ($newTrxId < 1)       $newTrxId = 1;
            if ($newTrxId > $total)  $newTrxId = $total;

            if ($newTrxId === $oldTrxId) {
                return $oldTrxId; // no-op
            }

            if ($newTrxId < $oldTrxId) {
                // Moving UP (smaller trx_id) — shift the displaced block +1.
                Database::execute(
                    "UPDATE trx
                        SET trx_id = trx_id + 1,
                            updated_at = NOW()
                      WHERE trx_id >= ?
                        AND trx_id <  ?
                        AND id     <> ?",
                    [$newTrxId, $oldTrxId, $id]
                );
            } else {
                // Moving DOWN (larger trx_id) — shift the displaced block -1.
                Database::execute(
                    "UPDATE trx
                        SET trx_id = trx_id - 1,
                            updated_at = NOW()
                      WHERE trx_id >  ?
                        AND trx_id <= ?
                        AND id     <> ?",
                    [$oldTrxId, $newTrxId, $id]
                );
            }

            // Finally place the moved row at its new trx_id.
            Database::execute(
                "UPDATE trx
                    SET trx_id = ?,
                        updated_at = NOW()
                  WHERE id = ?",
                [$newTrxId, $id]
            );

            return $newTrxId;
        });
    }
}
