<?php
/**
 * MasterController
 * ------------------------------------------------------------
 * CRUD handler for the `master` table (clients — customers & suppliers).
 *
 *   GET  /master                  → index()    list all entries (table + modals)
 *   POST /master/store            → store()    create new entry
 *   POST /master/{id}/update      → update()   update existing entry
 *   POST /master/{id}/delete      → destroy()  delete entry
 *
 * Pattern is classic POST-redirect-GET: every mutation ends with a redirect
 * back to /master so the URL bar stays clean and refresh is safe (no
 * accidental double-submit).
 *
 * On validation failure the controller flashes 'errors', 'old' (the values
 * the user typed) and optionally 'editing_id' (so the view can re-open the
 * Add/Edit modal pre-filled with the bad input).
 *
 * Field normalisation (trim + UPPERCASE + empty→NULL) is applied in
 * normalize() before persisting, so the DB always stores uppercase data.
 * ------------------------------------------------------------
 */

namespace App\Controllers\Master;

use App\Core\Controller;
use App\Core\Session;
use App\Models\Master\Master;

class MasterController extends Controller
{
    /**
     * GET /master — render the table page.
     */
    public function index(): void
    {
        $rows = Master::all();

        $this->view('Master/index', [
            'title'       => 'Master',
            'rows'        => $rows,
            'errors'      => Session::getFlash('errors',     []),
            'old'         => Session::getFlash('old',        []),
            'editing_id'  => Session::getFlash('editing_id', null),
        ]);
    }

    /**
     * POST /master/store — create a new entry.
     */
    public function store(): void
    {
        // Validate. On failure, validate() flashes errors+old and redirects back to /master.
        $this->validate([
            'name'    => 'required|max:255',
            'station' => 'max:255',
            'remark'  => 'max:255',
        ], '/master');

        $data = $this->normalize([
            'name'    => $this->request->input('name'),
            'station' => $this->request->input('station'),
            'remark'  => $this->request->input('remark'),
        ]);

        Master::create($data + [
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        Session::flash('success', 'Entry added.');
        $this->redirect('/master');
    }

    /**
     * POST /master/{id}/update — apply changes to an existing entry.
     */
    public function update(): void
    {
        $id = (int) $this->request->param('id');

        // Confirm the row exists before doing anything else (avoid silent no-ops).
        $existing = Master::find($id);
        if (!$existing) {
            Session::flash('error', 'Entry not found.');
            $this->redirect('/master');
        }

        // If validation fails we also flash the row id so the modal reopens
        // in "edit" mode (otherwise it would default to "add").
        Session::flash('editing_id', $id);
        $this->validate([
            'name'    => 'required|max:255',
            'station' => 'max:255',
            'remark'  => 'max:255',
        ], '/master');

        $data = $this->normalize([
            'name'    => $this->request->input('name'),
            'station' => $this->request->input('station'),
            'remark'  => $this->request->input('remark'),
        ]);

        Master::updateById($id, $data + [
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        Session::flash('success', 'Entry updated.');
        $this->redirect('/master');
    }

    /**
     * POST /master/{id}/delete — remove an entry.
     */
    public function destroy(): void
    {
        $id = (int) $this->request->param('id');

        $existing = Master::find($id);
        if (!$existing) {
            Session::flash('error', 'Entry not found.');
            $this->redirect('/master');
        }

        Master::deleteById($id);

        Session::flash('success', 'Entry deleted.');
        $this->redirect('/master');
    }

    /**
     * Normalise a row's user-facing fields before persisting:
     *   - trim whitespace
     *   - uppercase (mb_strtoupper handles unicode safely)
     *   - empty strings become real NULL (cleaner than storing "")
     *
     * Required fields (`name`) stay as a string; optional fields
     * (`station`, `remark`) become null when blank.
     *
     * @param array<string,mixed> $data
     * @return array<string,string|null>
     */
    private function normalize(array $data): array
    {
        // Fields that are stored as NULL when blank (optional in the bible).
        $nullable = ['station', 'remark'];

        $out = [];
        foreach ($data as $key => $value) {
            $v = is_string($value) ? trim($value) : '';
            $v = $v === '' ? '' : mb_strtoupper($v, 'UTF-8');
            $out[$key] = ($v === '' && in_array($key, $nullable, true)) ? null : $v;
        }
        return $out;
    }
}
