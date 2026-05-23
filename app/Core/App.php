<?php
/**
 * App.php
 * ------------------------------------------------------------
 * Application bootstrap & registry.
 *
 * Responsibilities:
 *   - Define error reporting based on APP_DEBUG
 *   - Set the timezone
 *   - Register a PSR-4-style class autoloader for "App\" namespace
 *   - Start the session
 *   - Build the Router and load routes from /routes/web.php
 *   - Dispatch the current request
 *
 * Entry point: public/index.php calls App::run().
 * ------------------------------------------------------------
 */

namespace App\Core;

class App
{
    /** @var Router The router instance shared across the request lifecycle. */
    private static Router $router;

    /**
     * Run the application. Called from public/index.php.
     */
    public static function run(): void
    {
        self::bootstrap();

        // Build router and register middleware aliases.
        self::$router = new Router();
        self::$router->alias('auth',  \App\Middleware\AuthMiddleware::class);
        self::$router->alias('guest', \App\Middleware\GuestMiddleware::class);

        // Load route definitions (the file gets the $router var via closure scope).
        $router = self::$router;
        require APP_BASE . '/routes/web.php';

        // Dispatch.
        try {
            $request = new Request();
            self::$router->dispatch($request);
        } catch (\Throwable $e) {
            self::handleException($e);
        }
    }

    /**
     * One-time bootstrap (autoloader, env, errors, timezone, session).
     */
    private static function bootstrap(): void
    {
        // APP_BASE points at the project root and is set in public/index.php.

        // 1) Register a tiny PSR-4-style autoloader for the "App\" namespace.
        //    This MUST come before anything that references App\Core\Env etc.
        spl_autoload_register(function (string $class): void {
            $prefix  = 'App\\';
            $baseDir = APP_BASE . '/app/';
            if (!str_starts_with($class, $prefix)) {
                return;
            }
            $relative = substr($class, strlen($prefix));
            $file     = $baseDir . str_replace('\\', '/', $relative) . '.php';
            if (is_file($file)) {
                require $file;
            }
        });

        // 2) Load environment variables.
        Env::load(APP_BASE . '/.env');

        // 3) Configure error reporting based on APP_DEBUG.
        $debug = (bool) Env::get('APP_DEBUG', false);
        error_reporting(E_ALL);
        ini_set('display_errors', $debug ? '1' : '0');
        ini_set('log_errors', '1');
        ini_set('error_log', APP_BASE . '/storage/logs/php-errors.log');

        // 4) Set the default timezone.
        date_default_timezone_set((string) Env::get('APP_TIMEZONE', 'UTC'));

        // 5) Start the session (cookie params come from .env via Session).
        Session::start();
    }

    /**
     * Centralised exception handler.
     */
    private static function handleException(\Throwable $e): void
    {
        // Always log the full stack to a file.
        error_log('[' . date('Y-m-d H:i:s') . '] ' . $e::class . ': ' . $e->getMessage()
            . ' in ' . $e->getFile() . ':' . $e->getLine() . PHP_EOL . $e->getTraceAsString());

        $debug = (bool) Env::get('APP_DEBUG', false);
        http_response_code(500);

        if ($debug) {
            // Detailed page for local development.
            header('Content-Type: text/html; charset=utf-8');
            echo '<!doctype html><meta charset="utf-8">';
            echo '<title>Application Error</title>';
            echo '<div style="font:14px/1.4 monospace;padding:20px;background:#fff5f5;color:#900;">';
            echo '<h1 style="margin:0 0 10px;">' . htmlspecialchars($e::class) . '</h1>';
            echo '<p><b>' . htmlspecialchars($e->getMessage()) . '</b></p>';
            echo '<p>' . htmlspecialchars($e->getFile()) . ':' . (int) $e->getLine() . '</p>';
            echo '<pre style="white-space:pre-wrap;background:#fff;border:1px solid #f5c2c2;padding:12px;">'
                . htmlspecialchars($e->getTraceAsString()) . '</pre>';
            echo '</div>';
        } else {
            // Friendly 500 in production.
            Response::abort(500, 'Internal Server Error');
        }
    }
}
