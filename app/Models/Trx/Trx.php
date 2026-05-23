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
     * Formula (cashbook): SUM(cr) - SUM(dr).
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
                    COALESCE(SUM(cr), 0) - COALESCE(SUM(dr), 0) AS balance
               FROM trx
              GROUP BY master_id"
        );

        $out = [];
        foreach ($rows as $r) {
            $out[(int) $r['master_id']] = (float) $r['balance'];
        }
        return $out;
    }
}
