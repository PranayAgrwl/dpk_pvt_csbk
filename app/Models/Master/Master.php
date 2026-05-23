<?php
/**
 * Master model
 * ------------------------------------------------------------
 * Represents one row in the `master` table.
 *
 * Conceptually a "Client" — covers both customers and suppliers.
 * Kept deliberately minimal per the bible ("extremely simple/minimalistic").
 *
 * Columns: id, name, station, remark, created_at, updated_at
 *
 * The base App\Core\Model already provides:
 *   Master::find($id)
 *   Master::create([...])
 *   Master::updateById($id, [...])
 *
 * We override Master::all() to sort by name ASC (per the bible).
 * ------------------------------------------------------------
 */

namespace App\Models\Master;

use App\Core\Database;
use App\Core\Model;

class Master extends Model
{
    /** @var string Underlying table name (used by the parent Model helpers). */
    protected static string $table = 'master';

    /**
     * Return every master row, ordered by name ASC (per bible).
     * Secondary `id ASC` is a stable tiebreaker when two rows share a name.
     *
     * @return array<int, array<string,mixed>>
     */
    public static function all(): array
    {
        return Database::queryAll("SELECT * FROM master ORDER BY name ASC, id ASC");
    }

    /**
     * Delete a single row by primary key. Returns affected row count.
     * (The base Model intentionally has no delete helper, so we add the
     * one-liner here — same prepared-statement pattern.)
     */
    public static function deleteById(int $id): int
    {
        return Database::execute("DELETE FROM master WHERE id = ?", [$id]);
    }
}
