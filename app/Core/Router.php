<?php
/**
 * Router.php
 * ------------------------------------------------------------
 * Laravel-style router.
 *
 * - $router->get('/users/{id}', [UserController::class, 'show'])->middleware('auth');
 * - Supports GET, POST, PUT, PATCH, DELETE.
 * - {placeholders} become named parameters (alpha-num + _ - by default).
 * - Middleware aliases are registered in bootstrap and resolved here.
 * - Auto-verifies CSRF on every POST/PUT/PATCH/DELETE.
 *
 * dispatch() runs the matching route and returns when done.
 * On no match: renders a 404 view.
 * ------------------------------------------------------------
 */

namespace App\Core;

class Router
{
    /** @var array<int, array{method:string, pattern:string, regex:string, params:string[], handler:mixed, middleware:string[]}> */
    private array $routes = [];

    /** @var array<string,string>  Alias => fully-qualified middleware class. */
    private array $aliases = [];

    /**
     * Register a middleware alias (e.g. 'auth' => AuthMiddleware::class).
     */
    public function alias(string $name, string $class): void
    {
        $this->aliases[$name] = $class;
    }

    public function get(string $uri, array|callable $handler): RouteRegistration
    {
        return $this->addRoute('GET',    $uri, $handler);
    }
    public function post(string $uri, array|callable $handler): RouteRegistration
    {
        return $this->addRoute('POST',   $uri, $handler);
    }
    public function put(string $uri, array|callable $handler): RouteRegistration
    {
        return $this->addRoute('PUT',    $uri, $handler);
    }
    public function patch(string $uri, array|callable $handler): RouteRegistration
    {
        return $this->addRoute('PATCH',  $uri, $handler);
    }
    public function delete(string $uri, array|callable $handler): RouteRegistration
    {
        return $this->addRoute('DELETE', $uri, $handler);
    }

    /**
     * Adds the route record and returns a "registration" wrapper so callers
     * can do ->middleware('auth') fluently.
     */
    private function addRoute(string $method, string $uri, array|callable $handler): RouteRegistration
    {
        $uri = '/' . trim($uri, '/');

        // Convert "/users/{id}" -> regex "#^/users/(?P<id>[A-Za-z0-9_-]+)$#"
        $params = [];
        $regex  = preg_replace_callback(
            '/\{([A-Za-z_][A-Za-z0-9_]*)\}/',
            function ($m) use (&$params) {
                $params[] = $m[1];
                return '(?P<' . $m[1] . '>[A-Za-z0-9_-]+)';
            },
            $uri
        );
        $regex = '#^' . $regex . '$#';

        $idx = array_push($this->routes, [
            'method'     => $method,
            'pattern'    => $uri,
            'regex'      => $regex,
            'params'     => $params,
            'handler'    => $handler,
            'middleware' => [],
        ]) - 1;

        return new RouteRegistration($this->routes, $idx);
    }

    /**
     * Match the current Request and execute the matching route.
     */
    public function dispatch(Request $request): void
    {
        $method = $request->method();
        $uri    = $request->uri();

        // Method override (?_method=DELETE) for forms.
        if ($method === 'POST') {
            $override = strtoupper((string) $request->input('_method', ''));
            if (in_array($override, ['PUT', 'PATCH', 'DELETE'], true)) {
                $method = $override;
            }
        }

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }
            if (preg_match($route['regex'], $uri, $matches)) {
                // Extract named route params and pass them to the request.
                $params = [];
                foreach ($route['params'] as $name) {
                    $params[$name] = $matches[$name] ?? null;
                }
                $request->setRouteParams($params);

                // CSRF check on all state-changing methods.
                if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
                    $token = (string) $request->input(Csrf::fieldName(), '');
                    if (!Csrf::check($token)) {
                        Response::abort(419, 'CSRF token mismatch.');
                    }
                }

                // Run middleware in order.
                foreach ($route['middleware'] as $alias) {
                    if (!isset($this->aliases[$alias])) {
                        Response::abort(500, "Middleware alias not registered: {$alias}");
                    }
                    $mw = new $this->aliases[$alias]();
                    if (!$mw instanceof Middleware) {
                        Response::abort(500, "Middleware {$alias} does not implement Middleware interface.");
                    }
                    $mw->handle($request);
                }

                $this->invoke($route['handler'], $request);
                return;
            }
        }

        // No route matched.
        Response::abort(404, 'Page not found.');
    }

    /**
     * Invoke the route handler — either a callable or [Class, 'method'].
     */
    private function invoke(array|callable $handler, Request $request): void
    {
        if (is_array($handler) && count($handler) === 2) {
            [$class, $method] = $handler;
            if (!class_exists($class)) {
                Response::abort(500, "Controller not found: {$class}");
            }
            $controller = new $class($request);
            if (!method_exists($controller, $method)) {
                Response::abort(500, "Method {$method} missing on {$class}");
            }
            $controller->{$method}();
            return;
        }

        if (is_callable($handler)) {
            $handler($request);
            return;
        }

        Response::abort(500, 'Invalid route handler.');
    }
}

/**
 * RouteRegistration
 * -----------------
 * Returned by Router::get/post/etc to enable fluent ->middleware('auth') calls.
 */
class RouteRegistration
{
    /** @var array<int, mixed> Reference to the parent routes array. */
    private array $routesRef;
    private int $index;

    public function __construct(array &$routes, int $index)
    {
        $this->routesRef = &$routes;
        $this->index     = $index;
    }

    /**
     * Attach one or more middleware aliases. Returns $this for chaining.
     */
    public function middleware(string ...$aliases): self
    {
        $this->routesRef[$this->index]['middleware'] = array_merge(
            $this->routesRef[$this->index]['middleware'],
            $aliases
        );
        return $this;
    }
}
