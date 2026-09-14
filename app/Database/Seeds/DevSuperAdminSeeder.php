<?php

declare(strict_types=1);

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

/**
 * Dev/staging convenience only — creates one bootstrap Super Admin so the
 * app is usable immediately after install. Reads credentials from env vars
 * (seed.superAdminPhone / seed.superAdminPassword); if they're not set, a
 * random password is generated and printed once to the console. Refuses to
 * run in production without explicit env vars, since a fixed default
 * password shipped in every install would be a standing vulnerability.
 */
class DevSuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        $phone    = env('seed.superAdminPhone');
        $password = env('seed.superAdminPassword');

        if (ENVIRONMENT === 'production' && (! $phone || ! $password)) {
            echo "Skipped: set seed.superAdminPhone and seed.superAdminPassword in .env before seeding production.\n";

            return;
        }

        $phone ??= '9740000001';
        $generated = false;
        if (! $password) {
            $password  = bin2hex(random_bytes(6));
            $generated = true;
        }

        $db = db_connect();
        if ($db->table('users')->where('phone', $phone)->countAllResults() > 0) {
            echo "Super Admin with phone {$phone} already exists — skipping.\n";

            return;
        }

        $now = date('Y-m-d H:i:s');
        $db->table('users')->insert([
            'name'                => 'Super Admin',
            'phone'               => $phone,
            'password_hash'       => password_hash($password, PASSWORD_DEFAULT),
            'role'                => 'super_admin',
            'status'              => 'active',
            'must_reset_password' => $generated ? 1 : 0,
            'created_at'          => $now,
            'updated_at'          => $now,
        ]);

        echo "Seeded Super Admin — phone: {$phone}" . ($generated ? ", password: {$password} (change on first login)" : '') . "\n";
    }
}
