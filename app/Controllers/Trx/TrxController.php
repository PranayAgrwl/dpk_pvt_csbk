<?php
/**
 * TrxController
 * ------------------------------------------------------------
 * Cashbook transaction module.
 *
 *   GET  /trx                  → index()    table view with edit/delete/reorder (R+U+D)
 *   GET  /trx/create           → create()   render the new-entry form
 *   POST /trx/store            → store()    validate + insert (C)
 *   POST /trx/{id}/update      → update()   AJAX: validate + update one row
 *   POST /trx/{id}/delete      → destroy()  AJAX: delete + renumber trx_id sequence
 *   POST /trx/{id}/reorder     → reorder()  AJAX: drag-and-drop reorder (shift others)
 *
 * The AJAX endpoints always answer with JSON:
 *   { "ok": true,  "rows": [...], "balances": {...}, "totalRows": N }
 *   { "ok": false, "errors": { field: ["..."] }, "message": "..." }
 *
 * Validation, normalisation (uppercase remark, exactly one of cr/dr,
 * money regex, master must exist) is identical for store() and update()
 * — centralised in validateForm().
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
     * GET /trx — render the table page (R + U + D).
     *
     * Sends the full row list (already including running_balance), masters
     * list (for the edit-modal combobox) and per-master balances down to
     * the view — the table is then live-manipulated client-side via AJAX.
     */
    public function index(): void
    {
        $rows     = Trx::listWithMaster();
        $masters  = Master::all();
        $balances = Trx::balancesByMaster();

        // Slim master list payload for the front-end combobox.
        $mastersForJs = array_map(
            static fn(array $m): array => [
                'id'      => (int)    $m['id'],
                'name'    => (string) $m['name'],
                'station' => (string) ($m['station'] ?? ''),
            ],
            $masters
        );

        // Slim row payload (only the columns the table renders + JS needs).
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
            $rows
        );

        $this->view('Trx/index', [
            'title'        => 'Transactions',
            'rows'         => $rowsForJs,
            'masters'      => $mastersForJs,
            'balances'     => $balances,
            // Loaded by footer.php AFTER jQuery + Bootstrap; order matters:
            // TrxCombobox first (no deps beyond jQuery), Sortable second.
            'extraScripts' => [
                'js/trx-combobox.js',
                'vendor/sortablejs/Sortable.min.js',
            ],
        ]);
    }

    /**
     * GET /trx/create — render the create form.
     */
    public function create(): void
    {
        $masters  = Master::all();
        $balances = Trx::balancesByMaster();

        // Slim payload for the front-end combobox.
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
            'extraScripts' => ['js/trx-combobox.js'],
        ]);
    }

    /**
     * POST /trx/store — validate + insert one transaction.
     * Classic full-page form post (POST-redirect-GET pattern, no AJAX).
     */
    public function store(): void
    {
        $trxDate = (string) $this->request->input('trx_date', '');

        [$errors, $data] = $this->validateForm(/* requireDate */ true, $trxDate);

        if (!empty($errors)) {
            Session::flash('errors', $errors);
            Session::flash('old', [
                'trx_date'  => $trxDate,
                'master_id' => (string) $this->request->input('master_id', ''),
                'cr'        => (string) $this->request->input('cr',        ''),
                'dr'        => (string) $this->request->input('dr',        ''),
                'remark'    => (string) $this->request->input('remark',    ''),
            ]);
            $this->redirect('/trx/create');
        }

        $nextTrxId = Trx::nextTrxId();

        Trx::create([
            'master_id'  => $data['master_id'],
            'trx_date'   => $trxDate,
            'trx_id'     => $nextTrxId,
            'cr'         => $data['cr'],
            'dr'         => $data['dr'],
            'remark'     => $data['remark'],
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        Session::flash('success', 'Transaction #' . $nextTrxId . ' saved.');
        $this->redirect('/trx/create');
    }

    /**
     * POST /trx/{id}/update — AJAX. Update editable fields of one row.
     * Editable per bible step 4: trx_date, master_id, cr, dr, remark.
     * trx_id is NOT editable here (it moves via drag-reorder only).
     */
    public function update(): void
    {
        $id = (int) $this->request->param('id');

        $existing = Trx::find($id);
        if (!$existing) {
            $this->json(['ok' => false, 'message' => 'Transaction not found.'], 404);
        }

        $trxDate = (string) $this->request->input('trx_date', '');

        [$errors, $data] = $this->validateForm(/* requireDate */ true, $trxDate);

        if (!empty($errors)) {
            $this->json(['ok' => false, 'errors' => $errors], 422);
        }

        Trx::updateFields($id, $data + ['trx_date' => $trxDate]);

        $this->json($this->tablePayload() + ['ok' => true]);
    }

    /**
     * POST /trx/{id}/delete — AJAX. Delete row + renumber trx_id sequence.
     */
    public function destroy(): void
    {
        $id = (int) $this->request->param('id');

        $ok = Trx::deleteAndRenumber($id);
        if (!$ok) {
            $this->json(['ok' => false, 'message' => 'Transaction not found.'], 404);
        }

        $this->json($this->tablePayload() + ['ok' => true]);
    }

    /**
     * POST /trx/{id}/reorder — AJAX. Drag-and-drop reorder.
     * Body: new_trx_id (int, 1..total)
     */
    public function reorder(): void
    {
        $id        = (int) $this->request->param('id');
        $newTrxId  = (int) $this->request->input('new_trx_id', 0);

        if ($newTrxId < 1) {
            $this->json(['ok' => false, 'message' => 'Invalid target position.'], 422);
        }

        $resulting = Trx::reorder($id, $newTrxId);
        if ($resulting === null) {
            $this->json(['ok' => false, 'message' => 'Transaction not found.'], 404);
        }

        $this->json($this->tablePayload() + ['ok' => true]);
    }

    // -------------------------------------------------------------------------
    // Internal helpers
    // -------------------------------------------------------------------------

    /**
     * Pull + validate + normalise the transaction form fields.
     * Returns [$errors, $cleanData] where $cleanData is:
     *   [ 'master_id' => int, 'cr' => ?float, 'dr' => ?float, 'remark' => ?string ]
     *
     * @param bool        $requireDate  When true, also validates the trx_date
     *                                  field (used by store(), skipped by update()).
     * @param string|null $trxDate      The date string (only used when $requireDate).
     * @return array{0: array<string,string[]>, 1: array<string,mixed>}
     */
    private function validateForm(bool $requireDate, ?string $trxDate): array
    {
        $masterId = (string) $this->request->input('master_id', '');
        $crRaw    = (string) $this->request->input('cr',        '');
        $drRaw    = (string) $this->request->input('dr',        '');
        $remark   = (string) $this->request->input('remark',    '');

        $errors = [];

        // ---- trx_date (only when creating) ------------------------------
        if ($requireDate) {
            if ($trxDate === null || $trxDate === '') {
                $errors['trx_date'][] = 'Date is required.';
            } elseif (!self::isValidDate($trxDate)) {
                $errors['trx_date'][] = 'Date must be a valid YYYY-MM-DD.';
            }
        }

        // ---- master_id: required, integer, must exist -------------------
        $masterIdInt = ctype_digit($masterId) ? (int) $masterId : 0;
        if ($masterIdInt <= 0) {
            $errors['master_id'][] = 'Please select a master from the list.';
        } elseif (!Master::find($masterIdInt)) {
            $errors['master_id'][] = 'Selected master does not exist.';
        }

        // ---- cr / dr: exactly one, > 0, money format --------------------
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
                if ($cr <= 0) $errors['cr'][] = 'Cr must be greater than 0.';
            }
        } elseif ($drSet && !$crSet) {
            if (!self::isValidMoney($drRaw)) {
                $errors['dr'][] = 'Dr must be a number with up to 2 decimals (e.g. 1234.50).';
            } else {
                $dr = (float) $drRaw;
                if ($dr <= 0) $errors['dr'][] = 'Dr must be greater than 0.';
            }
        }

        // ---- remark: optional, max 255, stored uppercased ---------------
        if ($remark !== '' && mb_strlen($remark) > 255) {
            $errors['remark'][] = 'Remark must be at most 255 characters.';
        }
        // Per bible: "all entries stored in uppercase - in mysql database".
        $remarkStored = $remark === '' ? null : mb_strtoupper($remark, 'UTF-8');

        return [
            $errors,
            [
                'master_id' => $masterIdInt,
                'cr'        => $cr,
                'dr'        => $dr,
                'remark'    => $remarkStored,
            ],
        ];
    }

    /**
     * Build the JSON payload that every AJAX endpoint returns on success.
     * The client uses this to re-render the table + refresh per-master
     * balances without a full page reload.
     *
     * @return array{rows: array<int, array<string,mixed>>, balances: array<int,float>, totalRows: int}
     */
    private function tablePayload(): array
    {
        $rows = Trx::listWithMaster();

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
            $rows
        );

        return [
            'rows'      => $rowsForJs,
            'balances'  => Trx::balancesByMaster(),
            'totalRows' => count($rowsForJs),
        ];
    }

    // ---- value-format helpers ----------------------------------------------

    /**
     * Strict YYYY-MM-DD check — accepts only real calendar dates.
     */
    private static function isValidDate(string $date): bool
    {
        $d = \DateTime::createFromFormat('Y-m-d', $date);
        return $d instanceof \DateTime && $d->format('Y-m-d') === $date;
    }

    /**
     * Validate a "money" string: digits and an optional dot with 1-2 decimals.
     * No sign, no commas, no scientific notation.
     */
    private static function isValidMoney(string $v): bool
    {
        return (bool) preg_match('/^\d{1,13}(\.\d{1,2})?$/', $v);
    }
}
