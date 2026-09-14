<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Mirrors enrolment_drafts (see CreateEnrolmentDrafts) for the New User
 * form: autosaved as an admin fills it in, resumable after a refresh,
 * accidental close, or a network drop that would otherwise have thrown
 * the whole form away. Deleted once the account is actually created —
 * UsersController::create() never reads from this table, it's purely a
 * resume aid.
 */
class CreateUserDrafts extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id' => [
                // Client-generated UUID (crypto.randomUUID()) — must exist
                // before the row is ever saved server-side, since local
                // autosave writes to localStorage under this same id first.
                'type'       => 'VARCHAR',
                'constraint' => 36,
            ],
            'created_by_user_id' => ['type' => 'INT', 'unsigned' => true],
            'name'               => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
            'role'               => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true],
            'payload'            => ['type' => 'TEXT'],
            'created_at'         => ['type' => 'DATETIME', 'null' => true],
            'updated_at'         => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('created_by_user_id');
        $this->forge->createTable('user_drafts');
    }

    public function down(): void
    {
        $this->forge->dropTable('user_drafts', true);
    }
}
