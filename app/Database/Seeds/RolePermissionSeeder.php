<?php

declare(strict_types=1);

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

/**
 * Seeds the module x role permission matrix, matching the Hithachintak
 * prototype's PERM_SEED default. Levels: None, Own, View, Edit, Full.
 * These are the *defaults* — Dev Admin can change them at runtime from
 * Settings & Integrations > Roles & Permissions, which edits this table.
 *
 * Dev Admin is a strict superset of Super Admin: Full on every module
 * Super Admin has, plus Full on Settings & Integrations (which Super
 * Admin does not have, and which now also covers this matrix). Dev
 * Admin's account itself is never visible through Users & Hierarchy
 * regardless of who's looking (see UserModel::visibleTo()) — it isn't
 * meant to be discoverable, only usable by whoever holds the one set
 * of credentials (see DevAdminSeeder).
 */
class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $modules = [
            'Dashboard',
            'Masters',
            'Users & Hierarchy',
            'Enrolments',
            'Reports',
            'Settings & Integrations',
        ];

        $roles = ['dev_admin', 'super_admin', 'pranta_admin', 'sub_admin', 'prakhand_admin', 'karyakarta'];

        $matrix = [
            'Dashboard'                => ['Full', 'Full', 'Full', 'View', 'View', 'Own'],
            'Masters'                  => ['Full', 'Full', 'Edit', 'View', 'None', 'None'],
            'Users & Hierarchy'        => ['Full', 'Full', 'Edit', 'Edit', 'Edit', 'None'],
            'Enrolments'               => ['Full', 'Full', 'Full', 'Edit', 'Edit', 'Own'],
            'Reports'                  => ['Full', 'Full', 'View', 'View', 'View', 'None'],
            'Settings & Integrations'  => ['Full', 'None', 'None', 'None', 'None', 'None'],
        ];

        $db  = db_connect();
        $now = date('Y-m-d H:i:s');

        // Collections was folded into Enrolments (one merged list, one
        // permission module) — clear out any rows an earlier seed run
        // left behind so the matrix UI doesn't show a stray column.
        $db->table('role_permissions')->where('module', 'Collections')->delete();

        foreach ($modules as $module) {
            foreach ($roles as $i => $role) {
                $exists = $db->table('role_permissions')
                    ->where('role', $role)
                    ->where('module', $module)
                    ->countAllResults();
                if ($exists > 0) {
                    continue;
                }
                $db->table('role_permissions')->insert([
                    'role'   => $role,
                    'module' => $module,
                    'level'  => $matrix[$module][$i],
                ]);
            }
        }
    }
}
