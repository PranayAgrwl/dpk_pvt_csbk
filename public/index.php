<?php
/**
 * public/index.php
 * ------------------------------------------------------------
 * Front controller — every HTTP request enters here.
 *
 *   1. Defines APP_BASE (project root, one directory up from public/)
 *   2. Bootstraps the application (App::run loads .env, autoloader, session, router)
 *   3. Dispatches the matching route
 * ------------------------------------------------------------
 */

declare(strict_types=1);

// APP_BASE is used everywhere to build absolute paths (e.g. app/Views/...).
define('APP_BASE', dirname(__DIR__));

// ---- PHP built-in dev server compatibility ----------------------------------
// `php -S host:port -t public/ public/index.php` routes EVERY request through
// this file - including requests for real static files. Returning `false` here
// tells the dev server to serve the static file directly.
// Apache + .htaccess already excludes existing files, so this branch is inert
// in production.
if (PHP_SAPI === 'cli-server') {
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    $file = __DIR__ . $path;
    if ($path !== '/' && is_file($file)) {
        return false;
    }
}

// Manually require the bootstrap (App.php) before the autoloader is registered.
require APP_BASE . '/app/Core/App.php';

\App\Core\App::run();
