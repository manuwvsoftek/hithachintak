<?php

declare(strict_types=1);

use App\Models\RolePermissionModel;

if (! function_exists('fmt_rupees')) {
    function fmt_rupees(float|int|string|null $amount): string
    {
        $amount ??= 0;

        return '₹' . number_format((float) $amount, (float) $amount == (int) $amount ? 0 : 2);
    }
}

if (! function_exists('mask_phone')) {
    function mask_phone(?string $phone): string
    {
        if (! $phone) {
            return '';
        }
        $digits = preg_replace('/\D/', '', $phone);

        return substr($digits, 0, 5) . ' ' . substr($digits, 5);
    }
}

if (! function_exists('status_pill_class')) {
    function status_pill_class(string $status): string
    {
        $map = [
            'active'             => 'pill-green',
            'configured'         => 'pill-green',
            'approved'           => 'pill-green',
            'paid'               => 'pill-green',
            'remitted'           => 'pill-green',
            'cleared'            => 'pill-green',
            'due'                => 'pill-amber',
            'pending'            => 'pill-amber',
            'cash_pending_remit' => 'pill-amber',
            'cash_collected'     => 'pill-amber',
            'awaiting_payment'   => 'pill-amber',
            'otp_pending'        => 'pill-amber',
            'blocked'            => 'pill-red',
            'failed'             => 'pill-red',
            'rejected'           => 'pill-red',
            'cancelled'          => 'pill-red',
            'inactive'           => 'pill-red',
        ];

        return $map[strtolower($status)] ?? 'pill-blue';
    }
}

if (! function_exists('enrolment_next_step_url')) {
    /**
     * Where an enrolment list row's action link should take the user —
     * mirrors EnrolmentController::nextStepUrl() (the same routing
     * already used after every payment-flow POST) so a row that isn't
     * receipted yet resumes at whatever step is actually next instead of
     * being a dead end with no link at all.
     */
    function enrolment_next_step_url(object $enrolment): string
    {
        return match ($enrolment->status) {
            'otp_pending'    => site_url("admin/enrolments/{$enrolment->id}/confirm-cash"),
            'cash_collected' => site_url("admin/enrolments/{$enrolment->id}/remit"),
            default          => $enrolment->hasReceipt()
                ? site_url("admin/enrolments/{$enrolment->id}/receipt")
                : site_url("admin/enrolments/{$enrolment->id}/payment"),
        };
    }
}

if (! function_exists('report_field_value')) {
    /** Plain-text rendering of one Reports field — shared by the on-screen table, CSV, Excel and PDF exports. */
    function report_field_value(object $row, string $field): string
    {
        $value = $row->{$field} ?? null;

        return match ($field) {
            'amount'                => number_format((float) $value, 2),
            'payment_mode'          => $value ? ucfirst((string) $value) : '—',
            'status'                => $value ? ucwords(str_replace('_', ' ', (string) $value)) : '—',
            'created_at', 'paid_at' => $value ? date('d M Y', strtotime((string) $value)) : '—',
            default                 => ($value === null || $value === '') ? '—' : (string) $value,
        };
    }
}

if (! function_exists('current_permission_level')) {
    function current_permission_level(string $module): string
    {
        $role = session('user_role');
        if (! $role) {
            return 'None';
        }

        return (new RolePermissionModel())->levelFor($role, $module);
    }
}

if (! function_exists('detect_inapp_browser')) {
    /**
     * A payment link sent over WhatsApp/SMS is usually tapped open inside
     * that app's own in-app browser (a WebView), not the phone's real
     * Chrome/Safari — and a WebView doesn't register the upi://, gpay://,
     * phonepe:// etc. custom schemes the way a real browser does, so the
     * Cashfree/Razorpay checkout's UPI app icons silently fail to appear
     * (only the QR code still works there). This can't be fixed in the
     * WebView itself since it belongs to WhatsApp/Instagram/etc., not
     * this app — the standard mitigation is detecting it and prompting
     * the donor to open the link in their real browser instead.
     *
     * Detection is necessarily best-effort: most Android in-app browsers
     * append a generic "; wv)" WebView marker to the UA regardless of
     * host app (reliable), while well-known hosts also leave their own
     * signature (FBAN/FBAV for Facebook, Instagram, Line, MicroMessenger
     * for WeChat, Twitter). iOS in-app browsers often leave no
     * detectable marker at all — WhatsApp's iOS one is a common case —
     * so a false negative there is expected and not fixable from here.
     *
     * @return array{inApp: bool, isAndroid: bool, isIOS: bool}
     */
    function detect_inapp_browser(?string $userAgent): array
    {
        $ua = $userAgent ?? '';

        $isAndroid = (bool) preg_match('/Android/i', $ua);
        $isIOS     = (bool) preg_match('/iPhone|iPad|iPod/i', $ua);

        $knownHost      = (bool) preg_match('/FBAN|FBAV|Instagram|Line\/|MicroMessenger|WhatsApp|Snapchat|TikTok|Twitter|LinkedInApp/i', $ua);
        $androidWebView = $isAndroid && (bool) preg_match('/; ?wv\)/i', $ua);

        return [
            'inApp'     => $knownHost || $androidWebView,
            'isAndroid' => $isAndroid,
            'isIOS'     => $isIOS,
        ];
    }
}
