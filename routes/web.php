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
use App\Controllers\Ledger\LedgerController;
use App\Controllers\Master\MasterController;
use App\Controllers\Trx\TrxController;

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

// ---------- Trx module (cashbook transactions) ----------
// Read + Update + Delete + drag-reorder live on /trx (AJAX-driven table).
// Create has its own form page at /trx/create.
$router->get( '/trx',                  [TrxController::class,    'index']   )->middleware('auth');
$router->get( '/trx/create',           [TrxController::class,    'create']  )->middleware('auth');
$router->post('/trx/store',            [TrxController::class,    'store']   )->middleware('auth');
$router->post('/trx/{id}/update',      [TrxController::class,    'update']  )->middleware('auth');
$router->post('/trx/{id}/delete',      [TrxController::class,    'destroy'] )->middleware('auth');
$router->post('/trx/{id}/reorder',     [TrxController::class,    'reorder'] )->middleware('auth');

// ---------- Ledger module (read-only views over trx data) ----------
// /ledger        — list of all masters with current balance + View link.
// /ledger/{id}   — per-master ledger (live edit + delete via the shared
//                  trx_edit_modal / trx_delete_modal partials and the
//                  existing /trx/{id}/update + /trx/{id}/delete endpoints).
$router->get( '/ledger',          [LedgerController::class, 'index'])->middleware('auth');
$router->get( '/ledger/{id}',     [LedgerController::class, 'show'] )->middleware('auth');
