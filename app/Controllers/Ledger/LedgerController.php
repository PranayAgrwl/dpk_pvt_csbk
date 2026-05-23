<?php
/**
 * LedgerController
 * ------------------------------------------------------------
 * Two read-only pages backed by the existing trx data:
 *
 *   GET /ledger              → index()  list of all masters with their
 *                                       current party balance + "View" button.
 *   GET /ledger/{id}         → show()   per-master ledger
 *                                       (trx_id, date, dr, cr, running balance,
 *                                        edit, delete — all live via AJAX).
 *
 * The Edit + Delete flows reuse the existing /trx/{id}/update and
 * /trx/{id}/delete endpoints — no duplicate write paths. The shared
 * trx_edit_modal + trx_delete_modal partials are included on /ledger/{id}
 * and wired by the shared TrxRowActions JS class.
 * ------------------------------------------------------------
 */

namespace App\Controllers\Ledger;

use App\Core\Controller;
use App\Core\Response;
use App\Core\Session;
use App\Models\Master\Master;
use App\Models\Trx\Trx;

class LedgerController extends Controller
{
    /**
     * GET /ledger — list every master alphabetically with current balance.
     */
    public function index(): void
    {
        $masters  = Master::all();              // sorted by name ASC
        $balances = Trx::balancesByMaster();    // { master_id => float }

        // Slim payload — only what the view actually renders.
        $rows = array_map(
            static fn(array $m): array => [
                'id'      => (int)    $m['id'],
                'name'    => (string) $m['name'],
                'station' => (string) ($m['station'] ?? ''),
                'balance' => (float)  ($balances[(int) $m['id']] ?? 0),
            ],
            $masters
        );

        $this->view('Ledger/index', [
            'title' => 'Ledger',
            'rows'  => $rows,
        ]);
    }

    /**
     * GET /ledger/{id} — per-master ledger page.
     * 404 if the master doesn't exist.
     */
    public function show(): void
    {
        $id     = (int) $this->request->param('id');
        $master = Master::find($id);
        if (!$master) {
            Response::abort(404, 'Master not found.');
        }

        $ledgerRows = Trx::listForMaster($id);   // per-master running balance
        $masters    = Master::all();             // for edit-modal combobox
        $balances   = Trx::balancesByMaster();   // for edit-modal baseline math

        $rowsForJs = array_map(
            static fn(array $r): array => [
                'id'              => (int)    $r['id'],
                'master_id'       => (int)    $r['master_id'],
                'master_name'     => (string) $r['master_name'],
                'master_station'  => (string) ($r['master_station'] ?? ''),
                'trx_date'        => (string) $r['trx_date'],
                'trx_id'          => (int)    $r['trx_id'],
                'cr'              => $r['cr'] === null ? null : (float) $r['cr'],
                'dr'              => $r['dr'] === null ? null : (float) $r['dr'],
                'remark'          => (string) ($r['remark'] ?? ''),
                'running_balance' => (float)  $r['running_balance'],
            ],
            $ledgerRows
        );

        $mastersForJs = array_map(
            static fn(array $m): array => [
                'id'      => (int)    $m['id'],
                'name'    => (string) $m['name'],
                'station' => (string) ($m['station'] ?? ''),
            ],
            $masters
        );

        $this->view('Ledger/show', [
            'title'        => 'Ledger · ' . $master['name'],
            'master'       => [
                'id'      => (int)    $master['id'],
                'name'    => (string) $master['name'],
                'station' => (string) ($master['station'] ?? ''),
                'balance' => (float)  ($balances[(int) $master['id']] ?? 0),
            ],
            'rows'         => $rowsForJs,
            'masters'      => $mastersForJs,
            'balances'     => $balances,
            'extraScripts' => [
                'js/trx-combobox.js',
                'js/trx-row-actions.js',
            ],
        ]);
    }
}
