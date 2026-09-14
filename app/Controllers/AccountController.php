<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\AuditLogModel;
use App\Models\UserModel;

/** First-login "set your own password" flow for users created by an admin. */
class AccountController extends BaseController
{
    public function showSetPassword()
    {
        return view('auth/set_password');
    }

    public function setPassword()
    {
        $rules = [
            'password'         => 'required|min_length[8]',
            'password_confirm' => 'matches[password]',
        ];
        if (! $this->validate($rules)) {
            return redirect()->back()->with('errors', $this->validator->getErrors());
        }

        $user      = $this->currentUser();
        $userModel = new UserModel();

        $userModel->update($user->id, [
            'password_hash'       => password_hash($this->request->getPost('password'), PASSWORD_DEFAULT),
            // The user is choosing this one themselves — no longer the
            // admin's to look up via "View password".
            'password_plain'      => null,
            'must_reset_password' => false,
        ]);
        (new AuditLogModel())->record($user->id, 'password_set_first_login');

        return redirect()->to('/admin')->with('success', 'Password set. Welcome!');
    }

    /**
     * Sets the signed-in user's preferred language — picked from the
     * topbar selector on every admin page. This doesn't translate the
     * admin UI itself (which stays English), only what's shown to the
     * enrolling member: the New Enrolment form's own section labels
     * default to it, and it seeds that form's own language dropdown so
     * the receipt comes out in the same language too.
     */
    public function setLanguage()
    {
        $lang = (string) $this->request->getPost('lang');
        if (in_array($lang, \App\Libraries\Enrolment\I18n::LANGUAGES, true)) {
            session()->set('ui_language', $lang);
        }

        return redirect()->back();
    }
}
