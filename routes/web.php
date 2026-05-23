<?php
/**
 * routes/web.php
 * ------------------------------------------------------------
 * Laravel-style route definitions.
 *
 * The $router instance is provided by App::run() before this file is required.
 * Two middleware aliases are pre-registered:
 *   'auth'  → user must be logged in
 *   'guest' → user must NOT be logged in
 * ------------------------------------------------------------
 */

use App\Controllers\AuthController;
use App\Controllers\HomeController;
use App\Controllers\ProfileController;
use App\Controllers\Master\MasterController;

/** @var \App\Core\Router $router */

// ---------- Guest (anonymous) routes ----------
$router->get( '/login',  [AuthController::class, 'showLogin'])->middleware('guest');
$router->post('/login',  [AuthController::class, 'login']    )->middleware('guest');

// ---------- Authenticated routes --------------
$router->get( '/',        [HomeController::class,    'index'] )->middleware('auth');
$router->post('/logout',  [AuthController::class,    'logout'])->middleware('auth');

$router->get( '/profile', [ProfileController::class, 'edit']  )->middleware('auth');
$router->post('/profile', [ProfileController::class, 'update'])->middleware('auth');

// ---------- Master module (clients: customers & suppliers) ----------
$router->get( '/master',               [MasterController::class, 'index']  )->middleware('auth');
$router->post('/master/store',         [MasterController::class, 'store']  )->middleware('auth');
$router->post('/master/{id}/update',   [MasterController::class, 'update'] )->middleware('auth');
$router->post('/master/{id}/delete',   [MasterController::class, 'destroy'])->middleware('auth');
