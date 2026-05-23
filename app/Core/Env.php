<?php
/**
 * Env.php
 * ------------------------------------------------------------
 * Minimal .env file loader (no Composer / vendor required).
 *
 * - Reads "KEY=VALUE" lines from a .env file
 * - Ignores blank lines and lines beginning with "#"
 * - Supports values wrapped in single or double quotes
 * - Strips inline comments (after the value) when unquoted
 * - Caches parsed values into $_ENV and getenv()
 *
 * Usage:
 *     Env::load(__DIR__ . '/../../.env');
 *     $dbHost = Env::get('DB_HOST', '127.0.0.1');
 * ------------------------------------------------------------
 */

namespace App\Core;

class Env
{
    /** @var array<string,string> In-memory cache of all loaded variables. */
    private static array $vars = [];

    /** @var bool Tracks whether load() has already run. */
    private static bool $loaded = false;

    /**
     * Load and parse a .env file. Safe to call multiple times -
     * subsequent calls are no-ops unless $force = true.
     */
    public static function load(string $path, bool $force = false): void
    {
        if (self::$loaded && !$force) {
            return;
        }

        if (!is_file($path) || !is_readable($path)) {
            // Don't crash the app if .env is missing - defaults will be used.
            self::$loaded = true;
            return;
        }

        // Read line-by-line; ignore newline characters and skip empty lines.
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        foreach ($lines as $line) {
            $line = trim($line);

            // Skip comments and blank lines.
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            // Must contain a "=" to be a valid KEY=VALUE pair.
            if (!str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            $key   = trim($key);
            $value = trim($value);

            // Strip surrounding quotes ("..." or '...').
            if (
                (str_starts_with($value, '"') && str_ends_with($value, '"')) ||
                (str_starts_with($value, "'") && str_ends_with($value, "'"))
            ) {
                $value = substr($value, 1, -1);
            } else {
                // For unquoted values, strip inline "# comment" trailers.
                $hashPos = strpos($value, ' #');
                if ($hashPos !== false) {
                    $value = rtrim(substr($value, 0, $hashPos));
                }
            }

            self::$vars[$key] = $value;

            // Make available via getenv() and $_ENV too, for compatibility.
            if (!array_key_exists($key, $_ENV)) {
                $_ENV[$key] = $value;
            }
            if (getenv($key) === false) {
                putenv("$key=$value");
            }
        }

        self::$loaded = true;
    }

    /**
     * Get an env variable with an optional default fallback.
     * Casts common boolean-like strings to real booleans.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $value = self::$vars[$key] ?? $_ENV[$key] ?? getenv($key);

        if ($value === false || $value === null) {
            return $default;
        }

        // Normalize boolean-ish strings.
        return match (strtolower((string)$value)) {
            'true', '(true)'   => true,
            'false', '(false)' => false,
            'null', '(null)'   => null,
            'empty', '(empty)' => '',
            default            => $value,
        };
    }

    /**
     * Set / override an env value at runtime (mostly for tests).
     */
    public static function set(string $key, string $value): void
    {
        self::$vars[$key] = $value;
        $_ENV[$key]       = $value;
        putenv("$key=$value");
    }
}
