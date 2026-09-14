<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateNotificationSettings extends Migration
{
    public function up(): void
    {
        // Single-row settings tables (row id=1), editable from Settings & Integrations.
        $this->forge->addField([
            'id'            => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'auth_key_enc'  => ['type' => 'TEXT', 'null' => true],
            'sender_id'     => ['type' => 'VARCHAR', 'constraint' => 15, 'default' => 'VHPHTC'],
            'dlt_entity_id' => ['type' => 'VARCHAR', 'constraint' => 40, 'null' => true],
            'route'         => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'transactional'],
            'status'        => ['type' => 'ENUM', 'constraint' => ['inactive', 'active'], 'default' => 'inactive'],
            'updated_at'    => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->createTable('sms_settings');

        $this->forge->addField([
            'id'               => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'purpose'          => ['type' => 'VARCHAR', 'constraint' => 60],
            'dlt_template_id'  => ['type' => 'VARCHAR', 'constraint' => 60, 'null' => true],
            'msg91_template_id'=> ['type' => 'VARCHAR', 'constraint' => 60, 'null' => true],
            'status'           => ['type' => 'ENUM', 'constraint' => ['pending', 'approved', 'rejected'], 'default' => 'pending'],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->createTable('sms_templates');

        $this->forge->addField([
            'id'                 => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'integrated_number'  => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true],
            'api_key_enc'        => ['type' => 'TEXT', 'null' => true],
            'waba_namespace_id'  => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'status'             => ['type' => 'ENUM', 'constraint' => ['inactive', 'active'], 'default' => 'inactive'],
            'updated_at'         => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->createTable('whatsapp_settings');

        $this->forge->addField([
            'id'     => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'name'   => ['type' => 'VARCHAR', 'constraint' => 80],
            'lang'   => ['type' => 'VARCHAR', 'constraint' => 30],
            'status' => ['type' => 'ENUM', 'constraint' => ['pending', 'approved', 'rejected'], 'default' => 'pending'],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->createTable('whatsapp_templates');

        $this->forge->addField([
            'id'            => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'provider'      => ['type' => 'ENUM', 'constraint' => ['smtp', 'sendgrid', 'ses'], 'default' => 'smtp'],
            'from_address'  => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
            'from_name'     => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
            'smtp_host'     => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
            'smtp_port'     => ['type' => 'INT', 'null' => true],
            'smtp_user'     => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
            'smtp_pass_enc' => ['type' => 'TEXT', 'null' => true],
            'smtp_crypto'   => ['type' => 'VARCHAR', 'constraint' => 10, 'null' => true],
            'api_key_enc'   => ['type' => 'TEXT', 'null' => true],
            'daily_quota'   => ['type' => 'INT', 'null' => true],
            'status'        => ['type' => 'ENUM', 'constraint' => ['inactive', 'active'], 'default' => 'inactive'],
            'updated_at'    => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->createTable('email_settings');
    }

    public function down(): void
    {
        $this->forge->dropTable('email_settings', true);
        $this->forge->dropTable('whatsapp_templates', true);
        $this->forge->dropTable('whatsapp_settings', true);
        $this->forge->dropTable('sms_templates', true);
        $this->forge->dropTable('sms_settings', true);
    }
}
