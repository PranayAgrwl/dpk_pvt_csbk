<?php
/**
 * Request.php
 * ------------------------------------------------------------
 * Read-only abstraction over the incoming HTTP request.
 *
 * - method()  HTTP verb (GET, POST, ...)
 * - uri()     path portion only, relative to the app base URL
 * - input()   merged $_GET + $_POST + decoded JSON body
 * - all()     full input array
 * - only()    whitelist specific keys
 * - isPost()  shortcut
 *
 * Trims whitespace on input by default, so empty fields stay empty.
 * ------------------------------------------------------------
 */

namespace App\Core;

class Request
{
    /** @var array<string,mixed> Cached merged input bag. */
    private array $input;

    /** @var string Cached request method (uppercase). */
    private string $method;

    /** @var string Cached request URI path (no query string). */
    private string $uri;

    /** @var array<string,string> Route parameters (e.g. {id} matches). */
    private array $routeParams = [];

    public function __construct()
    {
        $this->method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

        // Strip query string and base path from REQUEST_URI.
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $uri = strtok($uri, '?');                       // remove ?query
        $uri = $this->stripBasePath($uri);
        $this->uri = '/' . trim($uri, '/');

        // Merge GET, POST, and JSON body (in that priority order, POST overrides GET).
        $body = [];
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        if (stripos($contentType, 'application/json') !== false) {
            $raw = file_get_contents('php://input') ?: '';
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $body = $decoded;
            }
        }
        $this->input = array_merge($_GET, $_POST, $body);
    }

    /**
     * Normalise REQUEST_URI to a clean app-relative path.
     *
     * Apache (XAMPP) example:
     *   SCRIPT_NAME = "/dpk_pvt_csbk/public/index.php"
     *   REQUEST_URI = "/dpk_pvt_csbk/login"
     *   → return "/login"
     *
     * PHP built-in dev server example (`php -S 127.0.0.1:8080 -t public/`):
     *   SCRIPT_NAME = "/index.php"
     *   REQUEST_URI = "/login"
     *   → return "/login"
     */
    private function stripBasePath(string $uri): string
    {
        $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');

        // installBase = directory that contains /public, e.g. "/dpk_pvt_csbk" (or "" at root).
        $installBase = rtrim(dirname(dirname($scriptName)), '/');
        if ($installBase !== '' && $installBase !== '/' && str_starts_with($uri, $installBase)) {
            $uri = substr($uri, strlen($installBase));
        }

        // If anyone hits /public directly, strip that segment too.
        if ($uri === '/public' || str_starts_with($uri, '/public/')) {
            $uri = substr($uri, strlen('/public'));
        }

        if ($uri === '' || $uri === false) {
            $uri = '/';
        }
        return $uri;
    }

    public function method(): string  { return $this->method; }
    public function uri(): string     { return $this->uri; }
    public function isPost(): bool    { return $this->method === 'POST'; }
    public function isGet(): bool     { return $this->method === 'GET'; }

    /**
     * Read a single input value. Trims whitespace by default.
     */
    public function input(string $key, mixed $default = null, bool $trim = true): mixed
    {
        $value = $this->input[$key] ?? $default;
        return ($trim && is_string($value)) ? trim($value) : $value;
    }

    /** @return array<string,mixed> */
    public function all(): array
    {
        return $this->input;
    }

    /**
     * Whitelist specific input keys (returns an associative array).
     * @param string[] $keys
     * @return array<string,mixed>
     */
    public function only(array $keys): array
    {
        $out = [];
        foreach ($keys as $k) {
            $out[$k] = $this->input($k);
        }
        return $out;
    }

    /**
     * Route parameter accessor (e.g. for "/users/{id}", $req->param('id')).
     */
    public function setRouteParams(array $params): void
    {
        $this->routeParams = $params;
    }

    public function param(string $name, mixed $default = null): mixed
    {
        return $this->routeParams[$name] ?? $default;
    }

    public function clientIp(): string
    {
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
}
