<?php
/**
 * LedgerController
 * ------------------------------------------------------------
 * Read-only pages backed by the existing trx data + three PDF reports:
 *
 *   GET /ledger                   → index()        list of all masters with
 *                                                  current party balance + View.
 *   GET /ledger/{id}              → show()         per-master ledger
 *                                                  (trx_id, date, dr, cr,
 *                                                   running balance, edit, delete
 *                                                   — all live via AJAX).
 *   GET /ledger/print/regular     → printRegular() FPDF summary of every master
 *                                                  whose `station` is empty
 *                                                  (NULL / '' / whitespace).
 *   GET /ledger/print/local       → printLocal()   FPDF summary of every master
 *                                                  whose `station` is NOT empty
 *                                                  (currently the "LOCAL" set).
 *   GET /ledger/{id}/print        → printParty()   FPDF party-wise ledger for
 *                                                  ONE master — every trx row
 *                                                  sorted ASC by vno (trx_id),
 *                                                  with running balance, totals
 *                                                  and closing balance.
 *
 * All PDF reports share the same minimalist visual spec (Courier, 7.5mm
 * margins, black on white, no grey). Layout files live under app/Reports/
 * Ledger/ and are intended to be hand-edited freely. Output is sent inline
 * so the browser opens it in a new tab.
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

    /**
     * GET /ledger/print/regular — FPDF summary of "Regular" parties.
     *
     * A master is considered "Regular" when its `station` is NULL, empty,
     * or whitespace-only (the master form normalises empty input to NULL,
     * so in practice this matches every row that isn't an out-station like
     * "LOCAL", "DELHI", etc.).
     *
     * This method only gathers the data and hands it off to the report
     * template at `app/Reports/Ledger/regular.php`. That file owns the
     * entire PDF layout (margins, fonts, colours, column widths, spacing)
     * and is intended to be edited freely without touching this controller.
     *
     * Rows with a zero balance are intentionally skipped (per user request).
     * Within each section rows are sorted alphabetically by master name,
     * matching the order used on /ledger.
     */
    public function printRegular(): void
    {
        // ---- 1) Gather the "Regular" masters + their balances ----------------
        $masters  = Master::all();              // sorted by name ASC server-side
        $balances = Trx::balancesByMaster();    // { master_id => float }

        // A master is "Regular" when station is NULL, '', or just whitespace.
        // (The master form already trims + uppercases input and stores empty
        // values as NULL, so in practice this is "everyone without a station".)
        $regular = array_values(array_filter(
            $masters,
            static function (array $m): bool {
                $s = $m['station'] ?? null;
                if ($s === null) return true;
                return trim((string) $s) === '';
            }
        ));

        // ---- 2) Bucket into positive / negative; drop zero balances ----------
        $positives = [];
        $negatives = [];
        foreach ($regular as $m) {
            $bal = (float) ($balances[(int) $m['id']] ?? 0);
            if ($bal > 0) {
                $positives[] = ['name' => (string) $m['name'], 'balance' => $bal];
            } elseif ($bal < 0) {
                $negatives[] = ['name' => (string) $m['name'], 'balance' => $bal];
            }
        }

        // Within each section: alphabetical by name (consistent with /ledger).
        usort($positives, static fn(array $a, array $b): int => strcmp($a['name'], $b['name']));
        usort($negatives, static fn(array $a, array $b): int => strcmp($a['name'], $b['name']));

        $posSum = array_sum(array_column($positives, 'balance'));
        $negSum = array_sum(array_column($negatives, 'balance'));
        $grand  = $posSum + $negSum;

        // Today's date in DD/MM/YYYY (matches the format used elsewhere in the app).
        $generated = date('d/m/Y');

        // ---- 3) Hand off to the report template ------------------------------
        // The template is a plain PHP file (not a class) so the layout reads
        // top-to-bottom and can be tweaked without OOP plumbing. It uses the
        // locals defined above ($positives, $negatives, $posSum, $negSum,
        // $grand, $generated) and ends with $pdf->Output(...) — so this
        // method has nothing to do after the require.
        require APP_BASE . '/app/Reports/Ledger/regular.php';
    }

    /**
     * GET /ledger/print/local — FPDF summary of "Local" parties.
     *
     * Inverse of printRegular(): a master is "Local" when its `station` is
     * NOT empty (i.e. any non-blank value, currently only "LOCAL"). The two
     * reports together partition every master into exactly one section, so
     * you can run both and the rows never overlap.
     *
     * Layout, sort order, and zero-balance handling are identical to the
     * Regular report — see app/Reports/Ledger/local.php for the PDF and
     * app/Reports/Ledger/regular.php for its twin.
     */
    public function printLocal(): void
    {
        // ---- 1) Gather the "Local" masters + their balances ------------------
        $masters  = Master::all();              // sorted by name ASC server-side
        $balances = Trx::balancesByMaster();    // { master_id => float }

        // A master is "Local" when station is NOT empty/whitespace/NULL — the
        // exact inverse of the printRegular() filter. Keep this in lock-step
        // with that method so the two reports stay mutually exclusive.
        $local = array_values(array_filter(
            $masters,
            static function (array $m): bool {
                $s = $m['station'] ?? null;
                if ($s === null) return false;
                return trim((string) $s) !== '';
            }
        ));

        // ---- 2) Bucket into positive / negative; drop zero balances ----------
        $positives = [];
        $negatives = [];
        foreach ($local as $m) {
            $bal = (float) ($balances[(int) $m['id']] ?? 0);
            if ($bal > 0) {
                $positives[] = ['name' => (string) $m['name'], 'balance' => $bal];
            } elseif ($bal < 0) {
                $negatives[] = ['name' => (string) $m['name'], 'balance' => $bal];
            }
        }

        // Within each section: alphabetical by name (consistent with /ledger).
        usort($positives, static fn(array $a, array $b): int => strcmp($a['name'], $b['name']));
        usort($negatives, static fn(array $a, array $b): int => strcmp($a['name'], $b['name']));

        $posSum = array_sum(array_column($positives, 'balance'));
        $negSum = array_sum(array_column($negatives, 'balance'));
        $grand  = $posSum + $negSum;

        // Today's date in DD/MM/YYYY.
        $generated = date('d/m/Y');

        // ---- 3) Hand off to the report template ------------------------------
        require APP_BASE . '/app/Reports/Ledger/local.php';
    }

    /**
     * GET /ledger/{id}/print — FPDF party-wise ledger for ONE master.
     *
     * Renders every transaction belonging to this master sorted ascending
     * by trx_id (= "vno" per the bible), with a running-balance column and
     * a TOTAL + CLOSING BALANCE row at the bottom. 404s when the master
     * doesn't exist.
     *
     * The PDF layout lives in app/Reports/Ledger/party.php and can be edited
     * independently without touching this controller.
     */
    public function printParty(): void
    {
        $id     = (int) $this->request->param('id');
        $master = Master::find($id);
        if (!$master) {
            Response::abort(404, 'Master not found.');
        }

        // Trx::listForMaster() already returns rows ordered ASC by trx_id and
        // sets each row's `running_balance` (per-master cumulative SUM(dr-cr)
        // walked top-to-bottom). That's exactly what the printout needs.
        $rows = Trx::listForMaster($id);

        // ---- Totals + closing balance ---------------------------------------
        $sumDr = 0.0;
        $sumCr = 0.0;
        foreach ($rows as $r) {
            $sumDr += (float) ($r['dr'] ?? 0);
            $sumCr += (float) ($r['cr'] ?? 0);
        }
        // Closing balance = SUM(dr) - SUM(cr); also equals the last row's
        // running_balance, so we have two sanity-checked sources for the same
        // number. The subtraction form is the canonical one used app-wide.
        $closing = $sumDr - $sumCr;

        // ---- Locals expected by app/Reports/Ledger/party.php ----------------
        $partyName     = (string) $master['name'];
        $partyStation  = (string) ($master['station'] ?? '');
        $partyBalance  = $closing;                              // same thing
        $balanceStr    = number_format($partyBalance, 2, '.', ',');
        $generated     = date('d/m/Y');

        require APP_BASE . '/app/Reports/Ledger/party.php';
    }
}
