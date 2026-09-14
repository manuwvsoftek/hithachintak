<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateUsers extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'                  => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'name'                => ['type' => 'VARCHAR', 'constraint' => 150],
            'phone'               => ['type' => 'VARCHAR', 'constraint' => 15],
            'password_hash'       => ['type' => 'VARCHAR', 'constraint' => 255],
            'role'                => ['type' => 'ENUM', 'constraint' => ['super_admin', 'pranta_admin', 'sub_admin', 'karyakarta']],
            'prant_id'            => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'jila_id'             => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'prakhand_id'         => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'unit_label'          => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
            'upi_id'              => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'id_reference'        => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'status'              => ['type' => 'ENUM', 'constraint' => ['active', 'blocked'], 'default' => 'active'],
            'must_reset_password' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'last_login_at'       => ['type' => 'DATETIME', 'null' => true],
            'created_by'          => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'created_at'          => ['type' => 'DATETIME', 'null' => true],
            'updated_at'          => ['type' => 'DATETIME', 'null' => true],
            'deleted_at'          => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('phone');
        $this->forge->addKey(['role', 'prant_id']);
        $this->forge->addForeignKey('prant_id', 'prants', 'id', '', 'SET NULL');
        $this->forge->addForeignKey('jila_id', 'jilas', 'id', '', 'SET NULL');
        $this->forge->addForeignKey('prakhand_id', 'prakhands', 'id', '', 'SET NULL');
        $this->forge->createTable('users');

        $this->forge->addField([
            'id'     => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'role'   => ['type' => 'VARCHAR', 'constraint' => 30],
            'module' => ['type' => 'VARCHAR', 'constraint' => 60],
            'level'  => ['type' => 'ENUM', 'constraint' => ['None', 'Own', 'View', 'Edit', 'Full'], 'default' => 'None'],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['role', 'module']);
        $this->forge->createTable('role_permissions');
    }

    public function down(): void
    {
        $this->forge->dropTable('role_permissions', true);
        $this->forge->dropTable('users', true);
    }
}
