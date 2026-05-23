<?php
/**
 * Csrf.php
 * ------------------------------------------------------------
 * CSRF token issuer / verifier.
 *
 * - One token per session (rotated on login).
 * - Stored under $_SESSION['_csrf_token'].
 * - Verified using hash_equals() (timing-safe).
 *
 * Usage:
 *     <input type="hidden" name="_csrf" value="<?= Csrf::token() ?>">
 *     if (!Csrf::check($_POST['_csrf'] ?? '')) abort(419);
 * ------------------------------------------------------------
 */

namespace App\Core;

class Csrf
{
    /**
     * Get the current CSRF token, generating one on first call.
     */
    public static function token(): string
    {
        if (empty($_SESSION['_csrf_token'])) {
            // 32 random bytes → 64 hex chars. random_bytes is cryptographically secure.
            $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['_csrf_token'];
    }

    /**
     * Validate a token submitted from a form.
     * Uses hash_equals to avoid timing attacks.
     */
    public static function check(?string $token): bool
    {
        if (!is_string($token) || $token === '' || empty($_SESSION['_csrf_token'])) {
            return false;
        }
        return hash_equals($_SESSION['_csrf_token'], $token);
    }

    /**
     * Rotate the token (call on login or privilege change).
     */
    public static function rotate(): void
    {
        unset($_SESSION['_csrf_token']);
        self::token();
    }

    /**
     * Convenience: the form field name expected on POST (configurable in .env).
     */
    public static function fieldName(): string
    {
        return (string) Env::get('CSRF_TOKEN_NAME', '_csrf');
    }
}
