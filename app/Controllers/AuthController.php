<?php
/**
 * AuthController
 * ------------------------------------------------------------
 * Handles login & logout.
 *
 * - GET  /login   showLogin()  — render the form (guest-only)
 * - POST /login   login()      — verify credentials, start session
 * - POST /logout  logout()     — destroy session, redirect to /login
 * ------------------------------------------------------------
 */

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Csrf;
use App\Core\Session;
use App\Models\User;

class AuthController extends Controller
{
    /**
     * GET /login — render the login form.
     */
    public function showLogin(): void
    {
        $this->view('auth/login', [
            'title'  => 'Login',
            'errors' => Session::getFlash('errors', []),
        ]);
    }

    /**
     * POST /login — verify credentials and log the user in.
     */
    public function login(): void
    {
        // Validate request (length checks help thwart obviously-bad input).
        $this->validate([
            'username' => 'required|min:3|max:50',
            'password' => 'required|min:1|max:255',
        ], '/login');

        $username = (string) $this->request->input('username');
        $password = (string) $this->request->input('password');

        $user = User::byUsername($username);

        // We deliberately give a SAME generic error for both
        // "user not found" and "wrong password" - avoids username enumeration.
        if (!$user || !User::verifyPassword($user['password'], $password)) {
            Session::flash('error', 'Invalid username or password.');
            Session::flash('old',   ['username' => $username]);
            $this->redirect('/login');
        }

        // Session fixation defence: regenerate ID on privilege change.
        Session::regenerate();
        Csrf::rotate();

        Session::set('user_id', (int) $user['id']);
        Session::flash('success', 'Welcome back, ' . $user['name'] . '!');

        // Honour the "intended" URL if AuthMiddleware stored one.
        $intended = Session::getFlash('intended_url', '/');
        $this->redirect($intended ?: '/');
    }

    /**
     * POST /logout — kill the session and return to /login.
     */
    public function logout(): void
    {
        Session::destroy();
        // Start a fresh session so we can flash a goodbye message.
        Session::start();
        Session::flash('success', 'You have been logged out.');
        $this->redirect('/login');
    }
}
