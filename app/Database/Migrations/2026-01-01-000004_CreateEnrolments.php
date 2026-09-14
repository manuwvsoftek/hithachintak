<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateEnrolments extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'                     => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'receipt_no'             => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true],
            'member_id'              => ['type' => 'INT', 'unsigned' => true],
            'programme_id'           => ['type' => 'INT', 'unsigned' => true],
            'prant_id'               => ['type' => 'INT', 'unsigned' => true],
            'jila_id'                => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'prakhand_id'            => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'collected_by_user_id'   => ['type' => 'INT', 'unsigned' => true],
            'amount'                 => ['type' => 'DECIMAL', 'constraint' => '10,2'],
            'language'               => ['type' => 'VARCHAR', 'constraint' => 6, 'default' => 'en'],
            'payment_mode'           => ['type' => 'ENUM', 'constraint' => ['upi', 'qr', 'cash'], 'null' => true],
            'status'                 => [
                'type'       => 'ENUM',
                'constraint' => ['otp_pending', 'awaiting_payment', 'paid', 'cash_collected', 'cash_pending_remit', 'remitted', 'failed', 'cancelled'],
                'default'    => 'otp_pending',
            ],
            'cashfree_order_id'      => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'cashfree_payment_id'    => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'cashfree_order_status'  => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true],
            'otp_code_hash'          => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'otp_expires_at'         => ['type' => 'DATETIME', 'null' => true],
            'otp_attempts'           => ['type' => 'TINYINT', 'constraint' => 3, 'default' => 0],
            'otp_verified_at'        => ['type' => 'DATETIME', 'null' => true],
            'paid_at'                => ['type' => 'DATETIME', 'null' => true],
            'receipt_sent_whatsapp_at' => ['type' => 'DATETIME', 'null' => true],
            'receipt_sent_email_at'  => ['type' => 'DATETIME', 'null' => true],
            'receipt_sent_sms_at'    => ['type' => 'DATETIME', 'null' => true],
            'created_at'             => ['type' => 'DATETIME', 'null' => true],
            'updated_at'             => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('receipt_no');
        $this->forge->addKey(['programme_id', 'status']);
        $this->forge->addKey('collected_by_user_id');
        $this->forge->addKey('prant_id');
        $this->forge->addForeignKey('member_id', 'members', 'id', '', 'CASCADE');
        $this->forge->addForeignKey('programme_id', 'programmes', 'id', '', 'RESTRICT');
        $this->forge->addForeignKey('prant_id', 'prants', 'id', '', 'RESTRICT');
        $this->forge->addForeignKey('jila_id', 'jilas', 'id', '', 'SET NULL');
        $this->forge->addForeignKey('prakhand_id', 'prakhands', 'id', '', 'SET NULL');
        $this->forge->addForeignKey('collected_by_user_id', 'users', 'id', '', 'RESTRICT');
        $this->forge->createTable('enrolments');

        $this->forge->addField([
            'id'            => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'enrolment_id'  => ['type' => 'INT', 'unsigned' => true],
            'name'          => ['type' => 'VARCHAR', 'constraint' => 150],
            'age_or_dob'    => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('enrolment_id');
        $this->forge->addForeignKey('enrolment_id', 'enrolments', 'id', '', 'CASCADE');
        $this->forge->createTable('enrolment_family_members');

        $this->forge->addField([
            'id'           => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'user_id'      => ['type' => 'INT', 'unsigned' => true],
            'enrolment_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'amount'       => ['type' => 'DECIMAL', 'constraint' => '10,2'],
            'upi_ref'      => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'status'       => ['type' => 'ENUM', 'constraint' => ['due', 'remitted'], 'default' => 'due'],
            'remitted_at'  => ['type' => 'DATETIME', 'null' => true],
            'created_at'   => ['type' => 'DATETIME', 'null' => true],
            'updated_at'   => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['user_id', 'status']);
        $this->forge->addForeignKey('user_id', 'users', 'id', '', 'CASCADE');
        $this->forge->addForeignKey('enrolment_id', 'enrolments', 'id', '', 'SET NULL');
        $this->forge->createTable('cash_remittances');
    }

    public function down(): void
    {
        $this->forge->dropTable('cash_remittances', true);
        $this->forge->dropTable('enrolment_family_members', true);
        $this->forge->dropTable('enrolments', true);
    }
}
