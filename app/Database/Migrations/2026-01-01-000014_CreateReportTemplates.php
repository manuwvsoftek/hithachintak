<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Saved Reports filter/field combinations, so a user can reopen the same
 * shaped report later instead of re-picking filters and columns every
 * time. Personal to the user who saved it — not shared across accounts.
 */
class CreateReportTemplates extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'           => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'user_id'      => ['type' => 'INT', 'unsigned' => true],
            'name'         => ['type' => 'VARCHAR', 'constraint' => 100],
            'filters_json' => ['type' => 'TEXT'],
            'fields_json'  => ['type' => 'TEXT'],
            'created_at'   => ['type' => 'DATETIME', 'null' => true],
            'updated_at'   => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('user_id');
        $this->forge->addForeignKey('user_id', 'users', 'id', '', 'CASCADE');
        $this->forge->createTable('report_templates');
    }

    public function down(): void
    {
        $this->forge->dropTable('report_templates', true);
    }
}
