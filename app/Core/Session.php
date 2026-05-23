<?php
/**
 * Session.php
 * ------------------------------------------------------------
 * Thin wrapper around PHP's $_SESSION with secure defaults:
 *   - Custom cookie name from env
 *   - HTTPOnly + SameSite cookies (XSS mitigation)
 *   - Secure flag toggled from env (for HTTPS)
 *   - Configurable lifetime
 *   - session_regenerate_id() helper to prevent session fixation
 *   - Flash messages (one-shot reads, e.g. for form feedback)
 *
 * Usage:
 *     Session::start();
 *     Session::set('user_id', 1);
 *     Session::flash('success', 'Saved!');
 *     $msg = Session::getFlash('success');
 * ------------------------------------------------------------
 */

namespace App\Core;

class Session
{
    /** @var bool Tracks whether the session has been started already. */
    private static bool $started = false;

    /**
     * Start the session with hardened cookie params. Idempotent.
     */
    public static function start(): void
    {
        if (self::$started || session_status() === PHP_SESSION_ACTIVE) {
            self::$started = true;
            return;
        }

        // Pull settings from .env with safe defaults.
        $name     = (string) Env::get('SESSION_NAME', 'DPK_PVT_CSBK_SESSID');
        $lifetime = (int)    Env::get('SESSION_LIFETIME', 120); // minutes
        $secure   = (bool)   Env::get('SESSION_SECURE', false);
        $httponly = (bool)   Env::get('SESSION_HTTPONLY', true);
        $samesite = (string) Env::get('SESSION_SAMESITE', 'Lax');

        // Compute cookie path. We use the install base (directory ABOVE public/)
        // so the cookie is sent for the rewritten URL e.g. /dpk_pvt_csbk/login
        // — not /dpk_pvt_csbk/public/login (which the browser never sees).
        $scriptName  = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/');
        $installBase = rtrim(dirname(dirname($scriptName)), '/');
        $cookiePath  = ($installBase === '' || $installBase === '/') ? '/' : $installBase . '/';

        session_name($name);
        session_set_cookie_params([
            'lifetime' => $lifetime * 60,
            'path'     => $cookiePath,
            'domain'   => '',
            'secure'   => $secure,
            'httponly' => $httponly,
            'samesite' => $samesite,
        ]);

        // Prevent the session ID from being passed in the URL.
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');

        session_start();
        self::$started = true;

        // Process pending flash data: rotate "next" -> "now", drop "now" on next request.
        if (!isset($_SESSION['_flash'])) {
            $_SESSION['_flash'] = ['now' => [], 'next' => []];
        }
        $_SESSION['_flash']['now']  = $_SESSION['_flash']['next'];
        $_SESSION['_flash']['next'] = [];
    }

    /**
     * Get a value from the session, or $default if not set.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public static function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public static function has(string $key): bool
    {
        return isset($_SESSION[$key]);
    }

    public static function forget(string $key): void
    {
        unset($_SESSION[$key]);
    }

    /**
     * Completely destroy the session (used on logout).
     */
    public static function destroy(): void
    {
        $_SESSION = [];

        // Expire the session cookie.
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                [
                    'expires'  => time() - 42000,
                    'path'     => $params['path'],
                    'domain'   => $params['domain'],
                    'secure'   => $params['secure'],
                    'httponly' => $params['httponly'],
                    'samesite' => $params['samesite'],
                ]
            );
        }

        session_destroy();
        self::$started = false;
    }

    /**
     * Regenerate the session ID. Call this on login / privilege change
     * to defeat session fixation attacks.
     */
    public static function regenerate(bool $deleteOld = true): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id($deleteOld);
        }
    }

    // -----------------------
    // Flash messages
    // -----------------------

    /**
     * Stash a message to be shown on the NEXT request only.
     */
    public static function flash(string $key, mixed $value): void
    {
        $_SESSION['_flash']['next'][$key] = $value;
    }

    /**
     * Retrieve a flash message saved on the previous request.
     */
    public static function getFlash(string $key, mixed $default = null): mixed
    {
        return $_SESSION['_flash']['now'][$key] ?? $default;
    }

    public static function hasFlash(string $key): bool
    {
        return isset($_SESSION['_flash']['now'][$key]);
    }
}
