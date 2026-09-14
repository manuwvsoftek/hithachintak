<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Schema for the "Autopay Monthly Donation to VHP" programme: a donor PAN
 * field (mandatory for this programme only, enforced in EnrolmentService),
 * a widened payment_mode to record autopay-originated payments, and a new
 * autopay_subscriptions table for the recurring mandate itself.
 *
 * Each individual monthly charge under a subscription becomes its own
 * `enrolments` row (same member/programme/prant, payment_mode=autopay,
 * autopay_subscription_id set) rather than a separate charge-log table —
 * that reuses the existing receipt numbering, PDF, public link and
 * SMS/WhatsApp notification pipeline as-is for every recurring charge,
 * and the existing Enrolments/Collections/Reports screens show recurring
 * history for free since they already list `enrolments`.
 * `autopay_subscriptions` is the "one mandate" record; `enrolments` stays
 * the append-only ledger of actual money-in events, exactly as before.
 */
class AddAutopayDonations extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('programmes', [
            'is_recurring' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0, 'after' => 'mode'],
        ]);

        $this->forge->addColumn('members', [
            'pan' => ['type' => 'VARCHAR', 'constraint' => 10, 'null' => true, 'after' => 'pincode'],
        ]);

        $this->forge->modifyColumn('enrolments', [
            'payment_mode' => ['type' => 'ENUM', 'constraint' => ['upi', 'qr', 'cash', 'autopay'], 'null' => true],
        ]);

        $this->forge->addField([
            'id'                    => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'enrolment_id'          => ['type' => 'INT', 'unsigned' => true, 'comment' => 'the founding enrolment that set up this mandate'],
            'member_id'             => ['type' => 'INT', 'unsigned' => true],
            'programme_id'          => ['type' => 'INT', 'unsigned' => true],
            'prant_id'              => ['type' => 'INT', 'unsigned' => true],
            'cashfree_subscription_id' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'amount'                => ['type' => 'DECIMAL', 'constraint' => '10,2'],
            'status'                => [
                'type'       => 'ENUM',
                'constraint' => ['pending', 'active', 'paused', 'cancelled', 'failed'],
                'default'    => 'pending',
            ],
            'authorized_at'         => ['type' => 'DATETIME', 'null' => true],
            'last_charge_at'        => ['type' => 'DATETIME', 'null' => true],
            'cancelled_at'          => ['type' => 'DATETIME', 'null' => true],
            'created_at'            => ['type' => 'DATETIME', 'null' => true],
            'updated_at'            => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('enrolment_id');
        $this->forge->addUniqueKey('cashfree_subscription_id');
        $this->forge->addKey(['prant_id', 'status']);
        $this->forge->addForeignKey('enrolment_id', 'enrolments', 'id', '', 'CASCADE');
        $this->forge->addForeignKey('member_id', 'members', 'id', '', 'RESTRICT');
        $this->forge->addForeignKey('programme_id', 'programmes', 'id', '', 'RESTRICT');
        $this->forge->addForeignKey('prant_id', 'prants', 'id', '', 'RESTRICT');
        $this->forge->createTable('autopay_subscriptions');

        // A plain index rather than a hard FK constraint: SQLite (dev/test)
        // cannot ALTER TABLE ADD a foreign key on an existing table without
        // a full table rebuild, and this column is optional/nullable
        // application-level linkage (only ever written by our own code) —
        // not worth the portability cost of a constraint here.
        $this->forge->addColumn('enrolments', [
            'autopay_subscription_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true, 'after' => 'prakhand_id'],
        ]);
        $this->forge->addKey('autopay_subscription_id');
        $this->forge->processIndexes('enrolments');
    }

    public function down(): void
    {
        $this->forge->dropColumn('enrolments', 'autopay_subscription_id');
        $this->forge->dropTable('autopay_subscriptions', true);
        $this->forge->modifyColumn('enrolments', [
            'payment_mode' => ['type' => 'ENUM', 'constraint' => ['upi', 'qr', 'cash'], 'null' => true],
        ]);
        $this->forge->dropColumn('members', 'pan');
        $this->forge->dropColumn('programmes', 'is_recurring');
    }
}
