<?php
/**
 * GuestMiddleware
 * ------------------------------------------------------------
 * Mirror image of AuthMiddleware - allows the request through
 * ONLY when the user is NOT logged in. Used on /login so an
 * already-authenticated user is bounced to the dashboard.
 * ------------------------------------------------------------
 */

namespace App\Middleware;

use App\Core\Middleware;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;

class GuestMiddleware implements Middleware
{
    public function handle(Request $request): void
    {
        if (Session::get('user_id')) {
            Response::redirect('/');
        }
    }
}
