<?php
/**
 * View.php
 * ------------------------------------------------------------
 * Minimal PHP-template view renderer with layout/partials support.
 *
 * - render('home/index', ['foo' => 'bar'])  → echoes the page
 * - Each view receives:
 *      $title      Auto-generated page title (overridable in $data)
 *      $appName    From .env (APP_NAME)
 *      $auth       The currently logged-in user (or null)
 *      $csrfField  Pre-built <input type="hidden" name="_csrf" value="...">
 *      $asset()    Closure: $asset('css/app.css') → "/dpk_pvt_csbk/assets/css/app.css"
 *      $url()      Closure: $url('/login')         → "/dpk_pvt_csbk/login"
 *      $e()        Closure: htmlspecialchars wrapper (XSS-safe echo)
 *      $flash      Flash messages (array of ['type' => string, 'message' => string])
 *
 * - All views may "include" partials from app/Views/partials/*.php
 *
 * Usage in a view:
 *     <?php include APP_BASE . '/app/Views/partials/header.php'; ?>
 *     <h1><?= $e($title) ?></h1>
 *     <?php include APP_BASE . '/app/Views/partials/footer.php'; ?>
 * ------------------------------------------------------------
 */

namespace App\Core;

class View
{
    /**
     * Render a view file by dotted/slashed name (relative to app/Views/).
     *
     * @param string                $view  e.g. "home/index" → app/Views/home/index.php
     * @param array<string,mixed>   $data  Variables exposed to the view.
     */
    public static function render(string $view, array $data = []): void
    {
        $viewFile = APP_BASE . '/app/Views/' . str_replace('.', '/', $view) . '.php';

        if (!is_file($viewFile)) {
            Response::abort(500, "View not found: {$view}");
        }

        // Build the standard variable bag every view gets.
        $data = array_merge(self::defaultData(), $data);

        // Extract into local scope so views can use $title, $auth, etc.
        // EXTR_SKIP guarantees no overwrite of pre-existing locals here.
        extract($data, EXTR_SKIP);

        require $viewFile;
    }

    /**
     * Capture a view into a string instead of echoing it (handy for emails or JSON).
     */
    public static function capture(string $view, array $data = []): string
    {
        ob_start();
        self::render($view, $data);
        return (string) ob_get_clean();
    }

    /**
     * Build the always-available data bag.
     * @return array<string,mixed>
     */
    private static function defaultData(): array
    {
        $appName = (string) Env::get('APP_NAME', 'dpk_pvt_csbk');
        $base    = Response::baseUrl();

        // Pull the logged-in user (set by AuthMiddleware) without forcing a DB round-trip.
        $auth = $_SESSION['auth_user'] ?? null;

        // Closures keep these helpers ergonomic inside views.
        $e = static fn($v): string => htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $url   = static fn(string $path = '/') => $base . '/' . ltrim($path, '/');
        $asset = static fn(string $path)       => $base . '/assets/' . ltrim($path, '/');

        // Collect flash messages from the current request for easy display.
        $flash = [];
        foreach (['success', 'error', 'info', 'warning'] as $type) {
            if (Session::hasFlash($type)) {
                $flash[] = ['type' => $type, 'message' => (string) Session::getFlash($type)];
            }
        }

        $csrfField = sprintf(
            '<input type="hidden" name="%s" value="%s">',
            htmlspecialchars(Csrf::fieldName(), ENT_QUOTES),
            htmlspecialchars(Csrf::token(),     ENT_QUOTES)
        );

        return [
            'title'     => $appName,
            'appName'   => $appName,
            'auth'      => $auth,
            'csrfField' => $csrfField,
            'flash'     => $flash,
            'e'         => $e,
            'url'       => $url,
            'asset'     => $asset,
        ];
    }
}
