<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * In-progress enrolments autosaved as a Karyakarta types on the New
 * Enrolment form — the client generates the id (a UUID) as soon as they
 * start entering data, so the same draft can be resumed later from the
 * Enrolments list even after a refresh, an accidental close, or a network
 * outage that prevented the real enrolment from being submitted. The row
 * is deleted once the enrolment is actually submitted (EnrolmentService
 * never reads from this table — it's purely a resume aid).
 */
class CreateEnrolmentDrafts extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id' => [
                // Client-generated UUID (crypto.randomUUID()), not an
                // auto-increment int — the id must exist before the row is
                // ever saved server-side, since local autosave writes to
                // localStorage under this same id first.
                'type'       => 'VARCHAR',
                'constraint' => 36,
            ],
            'created_by_user_id' => ['type' => 'INT', 'unsigned' => true],
            'prant_id'           => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'jila_id'            => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'prakhand_id'        => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'programme_code'     => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true],
            'member_name'        => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
            'payload'            => ['type' => 'TEXT'],
            'created_at'         => ['type' => 'DATETIME', 'null' => true],
            'updated_at'         => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('created_by_user_id');
        $this->forge->addKey(['prant_id', 'jila_id', 'prakhand_id']);
        $this->forge->createTable('enrolment_drafts');
    }

    public function down(): void
    {
        $this->forge->dropTable('enrolment_drafts', true);
    }
}
