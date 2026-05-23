<?php
/**
 * TrxController
 * ------------------------------------------------------------
 * Cashbook transaction module - CREATE step only.
 * (Read / update / delete are scoped for the next step per the bible.)
 *
 *   GET  /trx/create   → create()  render the new-entry form
 *   POST /trx/store    → store()   validate + insert one row
 *
 * Validation rules (PHP-side; DB has belt-and-braces CHECK too):
 *   - trx_date  required, parseable as YYYY-MM-DD
 *   - master_id required, integer, must exist in the `master` table
 *   - cr / dr   numeric (>= 0, max 2 decimals), and EXACTLY ONE of them set
 *   - remark    optional, max 255 chars
 *
 * On validation failure: flashes `errors` + `old` and redirects back to
 * /trx/create so the form re-renders with the user's input restored.
 * ------------------------------------------------------------
 */

namespace App\Controllers\Trx;

use App\Core\Controller;
use App\Core\Response;
use App\Core\Session;
use App\Models\Master\Master;
use App\Models\Trx\Trx;

class TrxController extends Controller
{
    /**
     * GET /trx/create — render the create form.
     *
     * Sends the full master list (id, name, station) and a per-master
     * balance map down to the view as JSON; the combobox + balance display
     * are fully client-side from that point on (no extra round-trips).
     */
    public function create(): void
    {
        $masters  = Master::all();
        $balances = Trx::balancesByMaster();

        // Slim payload for the front-end combobox (no created_at / updated_at noise).
        $mastersForJs = array_map(
            static fn(array $m): array => [
                'id'      => (int)    $m['id'],
                'name'    => (string) $m['name'],
                'station' => (string) ($m['station'] ?? ''),
            ],
            $masters
        );

        $this->view('Trx/create', [
            'title'        => 'New Transaction',
            'masters'      => $mastersForJs,
            'balances'     => $balances,
            'nextTrxId'    => Trx::nextTrxId(),
            'todayDate'    => date('Y-m-d'),
            'errors'       => Session::getFlash('errors', []),
            'old'          => Session::getFlash('old',    []),
        ]);
    }

    /**
     * POST /trx/store — validate and insert a new transaction row.
     */
    public function store(): void
    {
        // Pull raw inputs (the Request layer already trims whitespace).
        $trxDate  = (string) $this->request->input('trx_date',  '');
        $masterId = (string) $this->request->input('master_id', '');
        $crRaw    = (string) $this->request->input('cr',        '');
        $drRaw    = (string) $this->request->input('dr',        '');
        $remark   = (string) $this->request->input('remark',    '');

        $errors = [];

        // ---- trx_date: required, must look like YYYY-MM-DD --------------
        if ($trxDate === '') {
            $errors['trx_date'][] = 'Date is required.';
        } elseif (!self::isValidDate($trxDate)) {
            $errors['trx_date'][] = 'Date must be a valid YYYY-MM-DD.';
        }

        // ---- master_id: required + must exist in master table -----------
        $masterIdInt = ctype_digit($masterId) ? (int) $masterId : 0;
        if ($masterIdInt <= 0) {
            $errors['master_id'][] = 'Please select a master from the list.';
        } elseif (!Master::find($masterIdInt)) {
            $errors['master_id'][] = 'Selected master does not exist.';
        }

        // ---- cr / dr: exactly one set; numeric with up to 2 decimals ----
        $crSet = ($crRaw !== '');
        $drSet = ($drRaw !== '');

        if (!$crSet && !$drSet) {
            $errors['cr'][] = 'Enter either a credit (Cr) or a debit (Dr).';
        } elseif ($crSet && $drSet) {
            $errors['cr'][] = 'Only one of Cr / Dr can be entered.';
        }

        $cr = null;
        $dr = null;
        if ($crSet && !$drSet) {
            if (!self::isValidMoney($crRaw)) {
                $errors['cr'][] = 'Cr must be a number with up to 2 decimals (e.g. 1234.50).';
            } else {
                $cr = (float) $crRaw;
                if ($cr <= 0) {
                    $errors['cr'][] = 'Cr must be greater than 0.';
                }
            }
        } elseif ($drSet && !$crSet) {
            if (!self::isValidMoney($drRaw)) {
                $errors['dr'][] = 'Dr must be a number with up to 2 decimals (e.g. 1234.50).';
            } else {
                $dr = (float) $drRaw;
                if ($dr <= 0) {
                    $errors['dr'][] = 'Dr must be greater than 0.';
                }
            }
        }

        // ---- remark: optional, max 255 ----------------------------------
        if ($remark !== '' && mb_strlen($remark) > 255) {
            $errors['remark'][] = 'Remark must be at most 255 characters.';
        }

        // If anything failed, flash + redirect back so the form re-renders
        // with the user's input restored (no retyping).
        if (!empty($errors)) {
            Session::flash('errors', $errors);
            Session::flash('old', [
                'trx_date'  => $trxDate,
                'master_id' => $masterId,
                'cr'        => $crRaw,
                'dr'        => $drRaw,
                'remark'    => $remark,
            ]);
            $this->redirect('/trx/create');
        }

        // Compute the next trx_id at INSERT time (no race-protection here;
        // single-user private cashbook so MAX+1 is fine for now).
        $nextTrxId = Trx::nextTrxId();

        // Per bible: "all entries stored in uppercase - in mysql database".
        // `remark` is the only user-typed text field on this form
        // (date / master / cr / dr / trx_id carry no letters).
        // mb_strtoupper handles unicode safely.
        $remarkStored = $remark === '' ? null : mb_strtoupper($remark, 'UTF-8');

        Trx::create([
            'master_id'  => $masterIdInt,
            'trx_date'   => $trxDate,
            'trx_id'     => $nextTrxId,
            'cr'         => $cr,                                  // one of these
            'dr'         => $dr,                                  // is NULL
            'remark'     => $remarkStored,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        Session::flash('success', 'Transaction #' . $nextTrxId . ' saved.');
        $this->redirect('/trx/create');
    }

    // ---- helpers ----------------------------------------------------------

    /**
     * Strict YYYY-MM-DD check — accepts only real calendar dates.
     * (HTML5 date inputs already format this way, but never trust the client.)
     */
    private static function isValidDate(string $date): bool
    {
        $d = \DateTime::createFromFormat('Y-m-d', $date);
        return $d instanceof \DateTime && $d->format('Y-m-d') === $date;
    }

    /**
     * Validate a "money" string: optional leading digits, optional dot and
     * 1-2 decimal places. No sign, no commas, no scientific notation.
     */
    private static function isValidMoney(string $v): bool
    {
        return (bool) preg_match('/^\d{1,13}(\.\d{1,2})?$/', $v);
    }
}
