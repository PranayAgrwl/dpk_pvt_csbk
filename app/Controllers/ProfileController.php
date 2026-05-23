<?php
/**
 * ProfileController
 * ------------------------------------------------------------
 * Lets the signed-in user edit their own username/name and
 * change their password.
 *
 * - GET  /profile   edit()    — render the form
 * - POST /profile   update()  — apply changes
 * ------------------------------------------------------------
 */

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Session;
use App\Models\User;

class ProfileController extends Controller
{
    /**
     * GET /profile — show the edit form pre-populated with current values.
     */
    public function edit(): void
    {
        $user = $_SESSION['auth_user']; // populated by AuthMiddleware

        $this->view('profile/edit', [
            'title'  => 'Edit Profile',
            'user'   => $user,
            'errors' => Session::getFlash('errors', []),
        ]);
    }

    /**
     * POST /profile — update profile fields. Optional password change is
     * gated by "current password" verification.
     */
    public function update(): void
    {
        $authUser = $_SESSION['auth_user'];
        $userId   = (int) $authUser['id'];

        // Step 1 — basic field validation.
        $this->validate([
            'name'             => 'required|min:1|max:100',
            'username'         => 'required|min:3|max:50|alpha_num',
            'current_password' => 'required|min:1|max:255',
        ], '/profile');

        $name             = (string) $this->request->input('name');
        $username         = (string) $this->request->input('username');
        $currentPassword  = (string) $this->request->input('current_password');
        $newPassword      = (string) $this->request->input('new_password', '');
        $newPasswordConf  = (string) $this->request->input('new_password_confirmation', '');

        // Step 2 — verify "current password" before any change.
        $row = User::find($userId);
        if (!$row || !User::verifyPassword($row['password'], $currentPassword)) {
            Session::flash('errors', ['current_password' => ['Current password is incorrect.']]);
            Session::flash('old',    compact('name', 'username'));
            $this->redirect('/profile');
        }

        // Step 3 — username uniqueness (excluding self).
        if (User::usernameTakenByOther($username, $userId)) {
            Session::flash('errors', ['username' => ['That username is already taken.']]);
            Session::flash('old',    compact('name', 'username'));
            $this->redirect('/profile');
        }

        // Step 4 — apply name/username changes.
        User::updateProfile($userId, [
            'name'     => $name,
            'username' => $username,
        ]);

        // Step 5 — optional password change.
        if ($newPassword !== '' || $newPasswordConf !== '') {
            if (strlen($newPassword) < 3) {
                Session::flash('errors', ['new_password' => ['New password must be at least 3 characters.']]);
                $this->redirect('/profile');
            }
            if ($newPassword !== $newPasswordConf) {
                Session::flash('errors', ['new_password' => ['New password confirmation does not match.']]);
                $this->redirect('/profile');
            }
            User::changePassword($userId, $newPassword);
            Session::flash('success', 'Profile and password updated successfully.');
        } else {
            Session::flash('success', 'Profile updated successfully.');
        }

        $this->redirect('/profile');
    }
}
