<?php
/**
 * Model.php
 * ------------------------------------------------------------
 * Bare-bones base class for database-backed models.
 *
 * Subclasses declare:
 *     protected static string $table = 'users';
 *
 * And get free helpers:
 *     User::find($id)
 *     User::findBy('username', 'admin')
 *     User::all()
 *     User::create(['name' => ..., 'username' => ..., 'password' => ...])
 *     User::updateById($id, ['name' => 'p'])
 *
 * All queries use PDO prepared statements (sql-injection safe).
 * ------------------------------------------------------------
 */

namespace App\Core;

abstract class Model
{
    /** @var string Name of the underlying table (override in child). */
    protected static string $table = '';

    /**
     * Fetch a single row by primary key.
     * @return array<string,mixed>|null
     */
    public static function find(int|string $id): ?array
    {
        return Database::queryOne(
            "SELECT * FROM " . static::table() . " WHERE id = ? LIMIT 1",
            [$id]
        );
    }

    /**
     * Fetch a single row by a specific column.
     * @return array<string,mixed>|null
     */
    public static function findBy(string $column, mixed $value): ?array
    {
        // Whitelist the column name: only allow safe identifier characters
        // because column names can't be parameter-bound.
        if (!preg_match('/^[A-Za-z0-9_]+$/', $column)) {
            throw new \InvalidArgumentException("Unsafe column name: {$column}");
        }
        return Database::queryOne(
            "SELECT * FROM " . static::table() . " WHERE {$column} = ? LIMIT 1",
            [$value]
        );
    }

    /**
     * Fetch all rows.
     * @return array<int, array<string,mixed>>
     */
    public static function all(): array
    {
        return Database::queryAll("SELECT * FROM " . static::table() . " ORDER BY id DESC");
    }

    /**
     * INSERT a new row. Returns the inserted id.
     * @param array<string,mixed> $data
     */
    public static function create(array $data): int
    {
        $cols   = [];
        $marks  = [];
        $params = [];
        foreach ($data as $col => $val) {
            self::assertSafeColumn($col);
            $cols[]   = "`{$col}`";
            $marks[]  = '?';
            $params[] = $val;
        }
        $sql = sprintf(
            "INSERT INTO %s (%s) VALUES (%s)",
            static::table(),
            implode(', ', $cols),
            implode(', ', $marks)
        );
        Database::execute($sql, $params);
        return (int) Database::lastInsertId();
    }

    /**
     * UPDATE by primary key. Returns affected rows.
     * @param array<string,mixed> $data
     */
    public static function updateById(int|string $id, array $data): int
    {
        if (empty($data)) {
            return 0;
        }
        $sets   = [];
        $params = [];
        foreach ($data as $col => $val) {
            self::assertSafeColumn($col);
            $sets[]   = "`{$col}` = ?";
            $params[] = $val;
        }
        $params[] = $id;
        $sql = sprintf(
            "UPDATE %s SET %s WHERE id = ?",
            static::table(),
            implode(', ', $sets)
        );
        return Database::execute($sql, $params);
    }

    /**
     * Get the table name. Throws if a subclass forgot to set $table.
     */
    protected static function table(): string
    {
        if (static::$table === '') {
            throw new \LogicException(static::class . ' must set protected static $table.');
        }
        return static::$table;
    }

    /**
     * Reject column names containing anything other than [A-Za-z0-9_].
     * Column names can't be parameter-bound, so we whitelist them.
     */
    private static function assertSafeColumn(string $col): void
    {
        if (!preg_match('/^[A-Za-z0-9_]+$/', $col)) {
            throw new \InvalidArgumentException("Unsafe column name: {$col}");
        }
    }
}
