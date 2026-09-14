<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateOtpAndAudit extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'          => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'phone'       => ['type' => 'VARCHAR', 'constraint' => 15],
            'purpose'     => ['type' => 'ENUM', 'constraint' => ['enrolment', 'login_reset', 'user_login_2fa']],
            'code_hash'   => ['type' => 'VARCHAR', 'constraint' => 255],
            'expires_at'  => ['type' => 'DATETIME'],
            'consumed_at' => ['type' => 'DATETIME', 'null' => true],
            'attempts'    => ['type' => 'TINYINT', 'constraint' => 3, 'default' => 0],
            'ip_address'  => ['type' => 'VARCHAR', 'constraint' => 45, 'null' => true],
            'created_at'  => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['phone', 'purpose']);
        $this->forge->createTable('otp_verifications');

        $this->forge->addField([
            'id'         => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'user_id'    => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'action'     => ['type' => 'VARCHAR', 'constraint' => 100],
            'entity'     => ['type' => 'VARCHAR', 'constraint' => 60, 'null' => true],
            'entity_id'  => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'meta_json'  => ['type' => 'TEXT', 'null' => true],
            'ip_address' => ['type' => 'VARCHAR', 'constraint' => 45, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['entity', 'entity_id']);
        $this->forge->addKey('user_id');
        $this->forge->createTable('audit_logs');
    }

    public function down(): void
    {
        $this->forge->dropTable('audit_logs', true);
        $this->forge->dropTable('otp_verifications', true);
    }
}
