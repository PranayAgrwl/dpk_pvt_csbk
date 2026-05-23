<?php
/**
 * HomeController
 * ------------------------------------------------------------
 * Renders the dashboard / "hello world" landing page after login.
 * ------------------------------------------------------------
 */

namespace App\Controllers;

use App\Core\Controller;

class HomeController extends Controller
{
    /**
     * GET / — show the dashboard. The auth middleware has already
     * verified the user; $_SESSION['auth_user'] is available to views.
     */
    public function index(): void
    {
        $this->view('home/index', [
            'title' => 'Dashboard',
        ]);
    }
}
