<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Libraries\Msg91\Msg91Sms;
use App\Models\AuditLogModel;
use App\Models\OtpVerificationModel;
use App\Models\RolePermissionModel;
use App\Models\UserModel;

class AuthController extends BaseController
{
    private const MAX_LOGIN_FAILURES = 5;
    private const LOCKOUT_SECONDS    = 15 * 60;

    // Per-phone lockout alone doesn't stop an attacker rotating through
    // many phone numbers from one source (a distributed-looking
    // dictionary attack against the login form) — this is the same
    // cache-based lockout, keyed by IP instead, as a second layer. The
    // threshold is higher since a shared office/NAT connection, or a
    // Karyakarta who mistypes their own password a few times, can
    // legitimately produce several failed logins from one IP.
    private const MAX_IP_FAILURES    = 20;
    private const IP_LOCKOUT_SECONDS = 15 * 60;

    public function showLogin()
    {
        if (session('user_id')) {
            return redirect()->to('/admin');
        }

        return view('auth/login');
    }

    public function login()
    {
        $phone    = trim((string) $this->request->getPost('phone'));
        $phone    = preg_replace('/\D/', '', $phone);
        $password = (string) $this->request->getPost('password');
        $ip       = $this->request->getIPAddress();
        $ipKey = md5($ip);
        $cache      = service('cache');
        $lockKey    = 'login_lock_' . $phone;
        $failKey    = 'login_fail_' . $phone;
        $ipLockKey  = 'login_ip_lock_' . $ipKey;
        $ipFailKey  = 'login_ip_fail_' . $ipKey;

        if ($cache->get($lockKey) || $cache->get($ipLockKey)) {
            return redirect()->back()->withInput()
                ->with('error', 'Too many failed attempts. Please try again in a few minutes.');
        }

        $userModel = new UserModel();
        $user      = $phone !== '' ? $userModel->findByPhone($phone) : null;

        // Always run password_verify, even against a dummy hash for an
        // unknown phone, so response time doesn't reveal whether a phone
        // number is registered (a cheap defence against enumeration).
        $hashToCheck = $user->password_hash ?? '$2y$10$invalidsaltinvalidsaltinvalidsaltuv';
        $passwordOk  = password_verify($password, $hashToCheck);

        if (! $user || ! $passwordOk) {
            $failures = (int) $cache->get($failKey) + 1;
            $cache->save($failKey, $failures, self::LOCKOUT_SECONDS);
            if ($failures >= self::MAX_LOGIN_FAILURES) {
                $cache->save($lockKey, true, self::LOCKOUT_SECONDS);
            }

            $ipFailures = (int) $cache->get($ipFailKey) + 1;
            $cache->save($ipFailKey, $ipFailures, self::IP_LOCKOUT_SECONDS);
            if ($ipFailures >= self::MAX_IP_FAILURES) {
                $cache->save($ipLockKey, true, self::IP_LOCKOUT_SECONDS);
            }

            (new AuditLogModel())->record(null, 'login_failed', 'user', $user->id ?? null, ['phone' => $phone, 'ip' => $ip]);

            return redirect()->back()->withInput()->with('error', 'Invalid phone number or password.');
        }

        if ($user->isBlocked()) {
            return redirect()->back()->withInput()->with('error', 'Your account has been blocked. Contact your administrator.');
        }

        $cache->delete($failKey);
        $cache->delete($lockKey);

        session()->regenerate(true);
        session()->set([
            'user_id'       => $user->id,
            'user_name'     => $user->name,
            'user_role'     => $user->role,
            'user_status'   => $user->status,
            'user_prant_id' => $user->prant_id,
            'user_jila_id'  => $user->jila_id,
        ]);

        $userModel->update($user->id, ['last_login_at' => date('Y-m-d H:i:s')]);
        (new AuditLogModel())->record($user->id, 'login_success', 'user', $user->id);

        if ($user->must_reset_password) {
            return redirect()->to('/account/set-password')->with('info', 'Please set a new password to continue.');
        }

        $redirect = session()->getFlashdata('redirect_after_login');

        return redirect()->to($redirect ?: $this->defaultLandingUrl($user->role));
    }

    public function logout()
    {
        $userId = session('user_id');
        (new AuditLogModel())->record($userId, 'logout');
        session()->destroy();

        return redirect()->to('/login')->with('info', 'You have been signed out.');
    }

    /**
     * Where to send a role after login when nothing more specific was
     * requested. Dashboard is the landing page for every role that has
     * one — but Dev Admin doesn't (it's None on Dashboard by design,
     * Full only on Settings & Integrations), so a hardcoded '/admin'
     * fallback would 403 it immediately after every login. Checked in
     * priority order against the same permission matrix the routes
     * themselves enforce, so this never drifts from what a role can
     * actually reach.
     */
    private function defaultLandingUrl(string $role): string
    {
        $model = new RolePermissionModel();
        $candidates = [
            ['admin', 'Dashboard', 'Own'],
            ['admin/settings', 'Settings & Integrations', 'Full'],
            ['admin/enrolments', 'Enrolments', 'Own'],
            ['admin/masters', 'Masters', 'View'],
            ['admin/reports', 'Reports', 'View'],
            ['admin/users', 'Users & Hierarchy', 'View'],
        ];

        foreach ($candidates as [$path, $module, $minLevel]) {
            if ($model->atLeast($role, $module, $minLevel)) {
                return '/' . $path;
            }
        }

        return '/admin';
    }

    // ---------------------------------------------------------------
    // Forgot password (SMS OTP)
    // ---------------------------------------------------------------

    public function showForgotPassword()
    {
        return view('auth/forgot_password');
    }

    public function requestReset()
    {
        $phone = preg_replace('/\D/', '', (string) $this->request->getPost('phone'));

        $user = (new UserModel())->findByPhone($phone);

        // Always show the same message whether or not the phone is
        // registered, so login enumeration isn't possible from this form.
        session()->set('pwreset_phone', $phone);

        if ($user && ! $user->isBlocked()) {
            try {
                $otp = (new OtpVerificationModel())->issue($phone, 'login_reset', $this->request->getIPAddress());
                (new Msg91Sms())->sendPasswordResetOtp($phone, $otp);
            } catch (\RuntimeException $e) {
                return redirect()->back()->withInput()->with('error', $e->getMessage());
            }
        }

        return redirect()->to('/forgot-password/verify')
            ->with('info', 'If that number is registered, an OTP has been sent by SMS.');
    }

    public function showVerifyReset()
    {
        if (! session('pwreset_phone')) {
            return redirect()->to('/forgot-password');
        }

        return view('auth/verify_reset');
    }

    public function verifyReset()
    {
        $phone = session('pwreset_phone');
        if (! $phone) {
            return redirect()->to('/forgot-password');
        }

        $code = (string) $this->request->getPost('otp');
        $ok   = (new OtpVerificationModel())->verify($phone, 'login_reset', $code);

        if (! $ok) {
            return redirect()->back()->with('error', 'Incorrect or expired OTP. Please try again.');
        }

        session()->set('pwreset_verified', true);

        return redirect()->to('/forgot-password/new');
    }

    public function showNewPassword()
    {
        if (! session('pwreset_verified')) {
            return redirect()->to('/forgot-password');
        }

        return view('auth/new_password');
    }

    public function submitNewPassword()
    {
        if (! session('pwreset_verified')) {
            return redirect()->to('/forgot-password');
        }

        $rules = [
            'password'         => 'required|min_length[8]',
            'password_confirm' => 'matches[password]',
        ];
        if (! $this->validate($rules)) {
            return redirect()->back()->with('errors', $this->validator->getErrors());
        }

        $phone     = session('pwreset_phone');
        $userModel = new UserModel();
        $user      = $userModel->findByPhone($phone);

        if ($user) {
            $userModel->update($user->id, [
                'password_hash'       => password_hash($this->request->getPost('password'), PASSWORD_DEFAULT),
                // The user is choosing this one themselves — no longer the
                // admin's to look up via "View password".
                'password_plain'      => null,
                'must_reset_password' => false,
            ]);
            (new AuditLogModel())->record($user->id, 'password_reset');
        }

        session()->remove(['pwreset_phone', 'pwreset_verified']);

        return redirect()->to('/login')->with('success', 'Password updated. Please sign in.');
    }
}
