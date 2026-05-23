<?php
/**
 * AuthMiddleware
 * ------------------------------------------------------------
 * Allows the request through ONLY if a user is logged in.
 *
 * - Looks up the user_id stored in session, fetches the user
 *   from the DB once per request, and exposes the row via
 *   $_SESSION['auth_user'] so views can use it as $auth.
 * - On failure: flashes a message and redirects to /login.
 * ------------------------------------------------------------
 */

namespace App\Middleware;

use App\Core\Middleware;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Models\User;

class AuthMiddleware implements Middleware
{
    public function handle(Request $request): void
    {
        $userId = Session::get('user_id');

        if (!$userId) {
            Session::flash('error', 'Please log in to continue.');
            // Remember the page they tried to reach (so we can redirect back after login).
            Session::flash('intended_url', $request->uri());
            Response::redirect('/login');
        }

        // Hydrate the user row so views and controllers can use it.
        $user = User::find((int) $userId);
        if (!$user) {
            // The user account was deleted while logged in - kill the session.
            Session::destroy();
            Response::redirect('/login');
        }

        // Strip the password hash before exposing the user to views.
        unset($user['password']);
        $_SESSION['auth_user'] = $user;
    }
}
