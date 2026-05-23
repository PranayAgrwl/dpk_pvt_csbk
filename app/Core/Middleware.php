<?php
/**
 * Middleware.php
 * ------------------------------------------------------------
 * Contract every middleware must implement.
 *
 * A middleware can either:
 *   - return (or void) to allow the request through, OR
 *   - Response::redirect()/abort() to short-circuit it.
 * ------------------------------------------------------------
 */

namespace App\Core;

interface Middleware
{
    /**
     * Examine the request and either let it through or terminate it.
     */
    public function handle(Request $request): void;
}
