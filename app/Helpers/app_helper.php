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
