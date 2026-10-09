<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\AuditLogModel;
use App\Models\UserModel;

/** First-login "set your own password" flow for users created by an admin. */
class AccountController extends BaseController
{
    public const AYAMS = [
        'Bajrang Dal', 'Dharma Prasar', 'Dharma Yatra', 'Dharmacharya Sampark', 'Durgavahini', 'Goraksha', 'Mandir & Archak Purohit', 'Matrushakti', 'Naitik Shiksha', 'Prachar Prasar', 'Samajik Samarasata', 'Sangathan', 'Sanskrit & Ved Vidyalay', 'Satsang', 'Seva', 'Vidhi Prakostha', 'Vishesh Sampark'
    ];
     public function showProfile()
    {
        return view('account/profile', [
            'title' => 'My Account',
            'user'  => $this->currentUser(),
            'ayams' => self::AYAMS,
        ]);
    }

    public function updateProfile()
    {
        $rules = [
            'name'          => 'required|min_length[2]|max_length[150]',
            'email'         => 'permit_empty|valid_email|max_length[150]',
            'address'       => 'permit_empty|max_length[500]',
            'aadhar_number' => 'permit_empty|max_length[20]',
            'profession'    => 'permit_empty|max_length[100]',
        ];
        if (! $this->validate($rules)) {
            return redirect()->to('/account/profile')->withInput()->with('errors', $this->validator->getErrors());
        }

        $user = $this->currentUser();
        (new UserModel())->update($user->id, [
            'name'          => $this->request->getPost('name'),
            'email'         => $this->request->getPost('email') ?: null,
            'address'       => $this->request->getPost('address') ?: null,
            'aadhar_number' => $this->request->getPost('aadhar_number') ?: null,
            'profession'    => $this->request->getPost('profession') ?: null,
        ]);
        (new AuditLogModel())->record($user->id, 'profile_self_updated');

        // The topbar shows the session's own copy of the name — refresh
        // it so a changed name appears right away, not only after the
        // next login.
        session()->set('user_name', $this->request->getPost('name'));

        return redirect()->to('/account/profile')->with('success', 'Profile updated.');
    }

    /**
     * Voluntary password change for an already-signed-in user — distinct
     * from setPassword() below, which only ever runs once, right after a
     * forced first login, with no current password to check yet.
     */
    public function changePassword()
    {
        $rules = [
            'current_password' => 'required',
            'password'         => 'required|min_length[8]',
            'password_confirm' => 'matches[password]',
        ];
        if (! $this->validate($rules)) {
            return redirect()->to('/account/profile')->with('errors', $this->validator->getErrors());
        }

        $user = $this->currentUser();
        if (! password_verify((string) $this->request->getPost('current_password'), (string) $user->password_hash)) {
            return redirect()->to('/account/profile')->with('errors', ['current_password' => 'Current password is incorrect.']);
        }

        (new UserModel())->update($user->id, [
            'password_hash'  => password_hash($this->request->getPost('password'), PASSWORD_DEFAULT),
            // Chosen by the user themselves — no longer the admin's to
            // look up via "View password", same as every other
            // self-chosen password in this app.
            'password_plain' => null,
        ]);
        (new AuditLogModel())->record($user->id, 'password_self_changed');

        return redirect()->to('/account/profile')->with('success', 'Password changed.');
    }
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
