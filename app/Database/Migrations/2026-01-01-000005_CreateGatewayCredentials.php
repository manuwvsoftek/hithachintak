<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateGatewayCredentials extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'                     => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'prant_id'               => ['type' => 'INT', 'unsigned' => true],
            'provider'               => ['type' => 'VARCHAR', 'constraint' => 30, 'default' => 'cashfree'],
            'app_id_enc'             => ['type' => 'TEXT', 'null' => true],
            'secret_key_enc'         => ['type' => 'TEXT', 'null' => true],
            'merchant_id'            => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'settlement_account_enc' => ['type' => 'TEXT', 'null' => true],
            'ifsc'                   => ['type' => 'VARCHAR', 'constraint' => 15, 'null' => true],
            'webhook_secret_enc'     => ['type' => 'TEXT', 'null' => true],
            'environment'            => ['type' => 'ENUM', 'constraint' => ['sandbox', 'production'], 'default' => 'sandbox'],
            'status'                 => ['type' => 'ENUM', 'constraint' => ['pending', 'configured'], 'default' => 'pending'],
            'created_at'             => ['type' => 'DATETIME', 'null' => true],
            'updated_at'             => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('prant_id');
        $this->forge->addForeignKey('prant_id', 'prants', 'id', '', 'CASCADE');
        $this->forge->createTable('gateway_credentials');
    }

    public function down(): void
    {
        $this->forge->dropTable('gateway_credentials', true);
    }
}
