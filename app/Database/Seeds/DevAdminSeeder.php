<?php

declare(strict_types=1);

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

/**
 * Provisions the one Dev Admin account — Settings & Integrations and
 * Roles & Permissions only (see RolePermissionSeeder / the
 * SeparateDevAdminPermissions migration for the matrix that enforces
 * that scope). Unlike DevSuperAdminSeeder there is no random-password
 * fallback here: this is meant to be a specific, deliberately-chosen
 * credential, not a bootstrap convenience, so both env vars
 * (seed.devAdminPhone / seed.devAdminPassword) are required in every
 * environment — the seeder simply refuses to run without them, rather
 * than ever inventing or hardcoding one.
 */
class DevAdminSeeder extends Seeder
{
    public function run(): void
    {
        $phone    = env('seed.devAdminPhone');
        $password = env('seed.devAdminPassword');

        if (! $phone || ! $password) {
            echo "Skipped: set seed.devAdminPhone and seed.devAdminPassword in .env before running this seeder.\n";

            return;
        }

        $db = db_connect();
        $existing = $db->table('users')->where('phone', $phone)->get()->getRowArray();

        $now = date('Y-m-d H:i:s');
        $data = [
            'name'                => 'Dev Admin',
            'phone'               => $phone,
            'password_hash'       => password_hash($password, PASSWORD_DEFAULT),
            'role'                => 'dev_admin',
            'status'              => 'active',
            'must_reset_password' => 0,
            'updated_at'          => $now,
        ];

        if ($existing) {
            // Re-running with a new password (e.g. a rotation) updates the
            // existing account in place rather than refusing outright —
            // there is only ever meant to be one Dev Admin.
            $db->table('users')->where('id', $existing['id'])->update($data);
            echo "Dev Admin with phone {$phone} already existed — credentials updated.\n";

            return;
        }

        $data['created_at'] = $now;
        $db->table('users')->insert($data);

        echo "Seeded Dev Admin — phone: {$phone}\n";
    }
}
