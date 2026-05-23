<?php
/**
 * Response.php
 * ------------------------------------------------------------
 * Tiny helper for emitting HTTP responses.
 *
 * - redirect()  Issue a 302 Location redirect and stop execution.
 * - json()      Send a JSON payload with the right Content-Type.
 * - status()    Set the HTTP status code (chainable).
 * - abort()     Emit an error page and exit.
 * ------------------------------------------------------------
 */

namespace App\Core;

class Response
{
    /**
     * Issue an absolute redirect (terminates the script).
     * Accepts either an absolute URL or an app-relative path like "/login".
     */
    public static function redirect(string $to, int $status = 302): never
    {
        if (!preg_match('#^https?://#i', $to)) {
            $to = self::baseUrl() . '/' . ltrim($to, '/');
        }
        header('Location: ' . $to, true, $status);
        exit;
    }

    /**
     * Compute the public base URL like "http://localhost/dpk_pvt_csbk".
     * Falls back to APP_URL from .env if available.
     */
    public static function baseUrl(): string
    {
        $envUrl = Env::get('APP_URL');
        if (is_string($envUrl) && $envUrl !== '') {
            return rtrim($envUrl, '/');
        }

        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
        // dirname twice: index.php -> public -> /dpk_pvt_csbk
        $base   = rtrim(dirname(dirname($script)), '/');
        return $scheme . '://' . $host . $base;
    }

    /**
     * Send a JSON response and exit.
     */
    public static function json(mixed $data, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * Send an error status and render the matching error view if available.
     *
     * NOTE: Apache rewrites non-standard codes (like 419) to "500 Internal
     * Server Error" if we only call http_response_code(). Sending an explicit
     * HTTP status line via header() bypasses that.
     */
    public static function abort(int $status, string $message = ''): never
    {
        $reason = self::statusText($status);
        $protocol = $_SERVER['SERVER_PROTOCOL'] ?? 'HTTP/1.1';
        header("{$protocol} {$status} {$reason}", true, $status);
        http_response_code($status);

        // Try to render a view named after the status (e.g. errors/404.php).
        $viewPath = APP_BASE . "/app/Views/errors/{$status}.php";
        if (is_file($viewPath)) {
            $data = ['status' => $status, 'message' => $message];
            View::render("errors/{$status}", $data);
            exit;
        }

        // Fallback plain output.
        header('Content-Type: text/plain; charset=utf-8');
        echo "Error {$status}" . ($message !== '' ? ": {$message}" : '');
        exit;
    }

    /**
     * Map common status codes to reason phrases. Includes 419 ("Page Expired",
     * a Laravel convention) so Apache passes it through unmolested.
     */
    private static function statusText(int $status): string
    {
        $table = [
            200 => 'OK',
            301 => 'Moved Permanently',
            302 => 'Found',
            400 => 'Bad Request',
            401 => 'Unauthorized',
            403 => 'Forbidden',
            404 => 'Not Found',
            405 => 'Method Not Allowed',
            419 => 'Page Expired',
            500 => 'Internal Server Error',
        ];
        return $table[$status] ?? 'Unknown';
    }
}
