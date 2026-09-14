<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * The RBAC hierarchy stopped one level short of the location hierarchy it
 * scopes access to — Prant and Jila each had an admin tier, but Prakhand
 * (the level `users.prakhand_id` already had a column for) didn't. Adds
 * `prakhand_admin`, scoped the same way Sub Admin is scoped by Jila.
 *
 * Also reworks the "New user" form's fields: `unit_label`/`upi_id`/
 * `id_reference` were free-text placeholders never enforced or read by
 * any scoping logic — dropped in favour of the real Prant/Jila/Prakhand
 * assignment plus genuinely useful optional contact/ID fields.
 */
class ExpandUserRolesAndFields extends Migration
{
    public function up(): void
    {
        $this->forge->modifyColumn('users', [
            'role' => ['type' => 'ENUM', 'constraint' => ['super_admin', 'pranta_admin', 'sub_admin', 'prakhand_admin', 'karyakarta']],
        ]);

        $this->forge->addColumn('users', [
            'address'       => ['type' => 'TEXT', 'null' => true, 'after' => 'prakhand_id'],
            'aadhar_number' => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true, 'after' => 'address'],
            'email'         => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true, 'after' => 'aadhar_number'],
            'profession'    => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true, 'after' => 'email'],
        ]);

        $this->forge->dropColumn('users', 'unit_label');
        $this->forge->dropColumn('users', 'upi_id');
        $this->forge->dropColumn('users', 'id_reference');
    }

    public function down(): void
    {
        $this->forge->addColumn('users', [
            'unit_label'   => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
            'upi_id'       => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'id_reference' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
        ]);

        $this->forge->dropColumn('users', 'address');
        $this->forge->dropColumn('users', 'aadhar_number');
        $this->forge->dropColumn('users', 'email');
        $this->forge->dropColumn('users', 'profession');

        $this->forge->modifyColumn('users', [
            'role' => ['type' => 'ENUM', 'constraint' => ['super_admin', 'pranta_admin', 'sub_admin', 'karyakarta']],
        ]);
    }
}
