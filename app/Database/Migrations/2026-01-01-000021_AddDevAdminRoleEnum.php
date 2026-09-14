<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * `users.role` is a database-level ENUM, separate from the PHP-side
 * User::ROLE_* constants — adding App\Entities\User::ROLE_DEV_ADMIN and
 * the dev_admin rows in role_permissions (see SeparateDevAdminPermissions
 * / DevAdminFullAccess) never touched this column's own constraint, so on
 * a real ENUM-enforcing driver (MySQL) inserting or updating a user with
 * role='dev_admin' was rejected outright — DevAdminSeeder could never
 * actually create the account. SQLite doesn't enforce column-type
 * constraints the same way, which is why this went unnoticed against the
 * SQLite dev database. This migration is the fix: expand the enum to
 * include dev_admin, exactly as ExpandUserRolesAndFields did for
 * prakhand_admin.
 */
class AddDevAdminRoleEnum extends Migration
{
    public function up(): void
    {
        $this->forge->modifyColumn('users', [
            'role' => ['type' => 'ENUM', 'constraint' => ['dev_admin', 'super_admin', 'pranta_admin', 'sub_admin', 'prakhand_admin', 'karyakarta']],
        ]);
    }

    public function down(): void
    {
        $this->forge->modifyColumn('users', [
            'role' => ['type' => 'ENUM', 'constraint' => ['super_admin', 'pranta_admin', 'sub_admin', 'prakhand_admin', 'karyakarta']],
        ]);
    }
}
