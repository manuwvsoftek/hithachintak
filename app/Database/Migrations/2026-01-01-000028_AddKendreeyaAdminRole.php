<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * One new rung in the admin hierarchy — Kendreeya Admin, which outranks
 * Pranta Admin and creates/manages Pranta Admins. Not a new location tier:
 * it reuses the existing multi-Prant scope mechanism exactly as a
 * multi-Prant Pranta Admin already does (see LocationScope and
 * User::ROLE_RANK's own comments) — only the users.role ENUM and its
 * default role_permissions rows are new here.
 *
 * `users.role` is a database-level ENUM (see AddDevAdminRoleEnum for why
 * this has to be a migration, not just a PHP-side constant change) — on
 * MySQL, inserting role='kendreeya_admin' without first widening this
 * constraint would be rejected outright, and CI4 then returns false from
 * Model::insert() rather than throwing (DBDebug off in production), which
 * is what turned this into a confusing foreign-key crash in
 * UsersController::create() instead of a clear "invalid role" error —
 * see that method's own fix for the other half of this.
 *
 * This is version 000028, not a reuse of 000027 (which a since-deleted
 * migration — AddKshetraVibhagAdminRoles — used for the now-reverted
 * Kshetra/Vibhag Admin roles). Migrations are tracked by version number
 * in the `migrations` table, not by class name or file content — on any
 * database where that old migration already ran, re-running `migrate`
 * under the same version 000027 would silently no-op, never widening the
 * ENUM to include kendreeya_admin at all. A fresh version number is the
 * only way to guarantee this actually runs everywhere.
 *
 * Also defensively folds any existing kshetra_admin/vibhag_admin rows
 * into their nearest surviving equivalent — in case that reverted
 * migration had already run somewhere and real accounts were created
 * with those roles before the decision to revert them.
 *
 * Default permission levels mirror Pranta Admin exactly — same modules,
 * same levels, just a wider location scope. RolePermissionSeeder is also
 * updated to match for a fresh install; this migration is what actually
 * corrects an existing database, the same division of labour
 * SeparateDevAdminPermissions established.
 */
class AddKendreeyaAdminRole extends Migration
{
    private const MODULES = [
        'Dashboard', 'Masters', 'Users & Hierarchy', 'Enrolments', 'Reports', 'Settings & Integrations',
    ];

    public function up(): void
    {
        // Widen to a superset first — one that's guaranteed to accept
        // every value already in the column, including kshetra_admin/
        // vibhag_admin if they're present, plus the new kendreeya_admin —
        // so the reassignment below can run without itself hitting the
        // ENUM constraint, whether or not this database ever saw those
        // two roles.
        $this->forge->modifyColumn('users', [
            'role' => ['type' => 'ENUM', 'constraint' => [
                'dev_admin', 'super_admin', 'kendreeya_admin', 'kshetra_admin', 'pranta_admin',
                'vibhag_admin', 'sub_admin', 'prakhand_admin', 'karyakarta',
            ]],
        ]);

        $this->db->table('users')->where('role', 'kshetra_admin')->update(['role' => 'kendreeya_admin']);
        $this->db->table('users')->where('role', 'vibhag_admin')->update(['role' => 'sub_admin']);
        $this->db->table('role_permissions')->whereIn('role', ['kshetra_admin', 'vibhag_admin'])->delete();

        // Now narrow to the real, final role set.
        $this->forge->modifyColumn('users', [
            'role' => ['type' => 'ENUM', 'constraint' => [
                'dev_admin', 'super_admin', 'kendreeya_admin', 'pranta_admin',
                'sub_admin', 'prakhand_admin', 'karyakarta',
            ]],
        ]);

        $prantaLevels = [
            'Dashboard'               => 'Full',
            'Masters'                 => 'Edit',
            'Users & Hierarchy'       => 'Edit',
            'Enrolments'              => 'Full',
            'Reports'                 => 'View',
            'Settings & Integrations' => 'None',
        ];

        foreach (self::MODULES as $module) {
            $this->upsert('kendreeya_admin', $module, $prantaLevels[$module]);
        }
    }

    public function down(): void
    {
        $this->db->table('role_permissions')->where('role', 'kendreeya_admin')->delete();

        $this->forge->modifyColumn('users', [
            'role' => ['type' => 'ENUM', 'constraint' => [
                'dev_admin', 'super_admin', 'pranta_admin', 'sub_admin', 'prakhand_admin', 'karyakarta',
            ]],
        ]);
    }

    private function upsert(string $role, string $module, string $level): void
    {
        $builder  = $this->db->table('role_permissions');
        $existing = $builder->where('role', $role)->where('module', $module)->get()->getRowArray();

        if ($existing) {
            $builder->where('id', $existing['id'])->update(['level' => $level]);
        } else {
            $builder->insert(['role' => $role, 'module' => $module, 'level' => $level]);
        }
    }
}
