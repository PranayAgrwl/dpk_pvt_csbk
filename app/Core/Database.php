<?php
/**
 * Database.php
 * ------------------------------------------------------------
 * Singleton PDO wrapper.
 *
 * - Reads connection details from Env (.env)
 * - Uses prepared statements only (sql injection safe)
 * - Returns associative arrays by default
 * - Throws PDOException on errors (caught & logged by App)
 *
 * Usage:
 *     $pdo = Database::pdo();
 *     $row = Database::queryOne("SELECT * FROM users WHERE id = ?", [$id]);
 * ------------------------------------------------------------
 */

namespace App\Core;

use PDO;
use PDOException;
use PDOStatement;

class Database
{
    /** @var PDO|null Lazily-created PDO instance. */
    private static ?PDO $pdo = null;

    /**
     * Lazily build & return the shared PDO instance.
     * @throws PDOException if the connection fails.
     */
    public static function pdo(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        $host    = Env::get('DB_HOST', '127.0.0.1');
        $port    = (string) Env::get('DB_PORT', '3306');
        $name    = Env::get('DB_NAME', 'db_dpk_pvt_csbk');
        $user    = Env::get('DB_USER', 'root');
        $pass    = (string) Env::get('DB_PASS', '');
        $charset = Env::get('DB_CHARSET', 'utf8mb4');

        // DSN string for MySQL with explicit charset (prevents charset-based injection tricks).
        $dsn = "mysql:host={$host};port={$port};dbname={$name};charset={$charset}";

        $options = [
            // Throw exceptions on errors instead of silently failing.
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            // Return associative arrays by default.
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            // Use real prepared statements (server-side), not emulated ones.
            PDO::ATTR_EMULATE_PREPARES   => false,
            // Persistent connections off (simpler, predictable behavior under XAMPP).
            PDO::ATTR_PERSISTENT         => false,
        ];

        self::$pdo = new PDO($dsn, $user, $pass, $options);
        return self::$pdo;
    }

    /**
     * Run a prepared query and return the PDOStatement.
     * @param string  $sql      SQL with "?" or ":name" placeholders.
     * @param array   $params   Values to bind.
     */
    public static function query(string $sql, array $params = []): PDOStatement
    {
        $stmt = self::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    /**
     * Fetch a single row (or null if no match).
     * @return array<string,mixed>|null
     */
    public static function queryOne(string $sql, array $params = []): ?array
    {
        $row = self::query($sql, $params)->fetch();
        return $row === false ? null : $row;
    }

    /**
     * Fetch all matching rows as an array.
     * @return array<int, array<string,mixed>>
     */
    public static function queryAll(string $sql, array $params = []): array
    {
        return self::query($sql, $params)->fetchAll();
    }

    /**
     * INSERT / UPDATE / DELETE helper. Returns affected row count.
     */
    public static function execute(string $sql, array $params = []): int
    {
        return self::query($sql, $params)->rowCount();
    }

    /**
     * Last-inserted primary key (after an INSERT).
     */
    public static function lastInsertId(): string
    {
        return self::pdo()->lastInsertId();
    }

    /**
     * Run a callback inside a DB transaction.
     *
     *   Database::transaction(function () {
     *       // ... queries that should commit/rollback atomically ...
     *   });
     *
     * On any thrown exception the transaction is rolled back and the exception
     * is re-thrown (so the caller / global handler can decide how to surface it).
     * Nested calls (transaction-in-transaction) just run inline — MySQL doesn't
     * support real nesting; this keeps the helper safe to compose.
     *
     * @template T
     * @param callable():T $callback
     * @return T
     */
    public static function transaction(callable $callback): mixed
    {
        $pdo = self::pdo();

        // Already inside a transaction? Just run the callback (no nested BEGIN).
        if ($pdo->inTransaction()) {
            return $callback();
        }

        $pdo->beginTransaction();
        try {
            $result = $callback();
            $pdo->commit();
            return $result;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }
}
