<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateProgrammesAndMembers extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'         => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'code'       => ['type' => 'VARCHAR', 'constraint' => 10],
            'name'       => ['type' => 'VARCHAR', 'constraint' => 100],
            'mode'       => ['type' => 'ENUM', 'constraint' => ['min', 'open'], 'default' => 'open'],
            'rate'       => ['type' => 'DECIMAL', 'constraint' => '10,2', 'null' => true, 'comment' => 'minimum amount per person when mode=min'],
            'status'     => ['type' => 'ENUM', 'constraint' => ['active', 'inactive'], 'default' => 'active'],
            'sort_order' => ['type' => 'INT', 'default' => 0],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('code');
        $this->forge->createTable('programmes');

        $this->forge->addField([
            'id'             => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'phone'          => ['type' => 'VARCHAR', 'constraint' => 15],
            'email'          => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
            'name'           => ['type' => 'VARCHAR', 'constraint' => 150],
            'age_or_dob'     => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true],
            'profession'     => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'address'        => ['type' => 'TEXT', 'null' => true],
            'pincode'        => ['type' => 'VARCHAR', 'constraint' => 10, 'null' => true],
            'preferred_lang' => ['type' => 'VARCHAR', 'constraint' => 6, 'default' => 'en'],
            'created_at'     => ['type' => 'DATETIME', 'null' => true],
            'updated_at'     => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('phone');
        $this->forge->createTable('members');
    }

    public function down(): void
    {
        $this->forge->dropTable('members', true);
        $this->forge->dropTable('programmes', true);
    }
}
