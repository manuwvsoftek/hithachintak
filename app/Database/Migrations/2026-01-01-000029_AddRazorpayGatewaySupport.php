<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Adds Razorpay as a second payment-gateway provider alongside Cashfree.
 *
 * gateway_credentials previously had a UNIQUE(prant_id) — one row per
 * Prant, provider always 'cashfree' — so a Prant can now have one row per
 * provider instead. The original migration added that unique key without
 * an explicit name (`addUniqueKey('prant_id')`), so its real index name
 * isn't reliably knowable across drivers here — this rebuilds the table
 * under a new name instead of guessing a dropKey() name, which works
 * identically on MySQL and SQLite (same portability tradeoff already
 * made elsewhere in this codebase, e.g. AddAutopayDonations skipping a
 * hard FK on SQLite).
 *
 * Every existing row (today, always Cashfree, status='configured' or
 * 'pending') is carried over with `enabled` backfilled to 1 only where it
 * was already 'configured' — those are live, already-working merchant
 * accounts and must keep working without an admin having to re-opt-in;
 * a 'pending' (never configured) row has nothing to enable anyway.
 */
class AddRazorpayGatewaySupport extends Migration
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
            'enabled'                => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'created_at'             => ['type' => 'DATETIME', 'null' => true],
            'updated_at'             => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['prant_id', 'provider'], 'gateway_credentials_prant_provider_unique');
        $this->forge->addForeignKey('prant_id', 'prants', 'id', '', 'CASCADE');
        $this->forge->createTable('gateway_credentials_new');

        $db = db_connect();
        $db->query('
            INSERT INTO gateway_credentials_new
                (id, prant_id, provider, app_id_enc, secret_key_enc, merchant_id,
                 settlement_account_enc, ifsc, webhook_secret_enc, environment, status, enabled,
                 created_at, updated_at)
            SELECT
                id, prant_id, provider, app_id_enc, secret_key_enc, merchant_id,
                settlement_account_enc, ifsc, webhook_secret_enc, environment, status,
                CASE WHEN status = ? THEN 1 ELSE 0 END,
                created_at, updated_at
            FROM gateway_credentials
        ', ['configured']);

        $this->forge->dropTable('gateway_credentials', true);
        $this->forge->renameTable('gateway_credentials_new', 'gateway_credentials');

        // Which gateway actually processed a given payment/order (the
        // Prant could have had either enabled at the time), plus
        // Razorpay's own order/payment identifiers mirroring the
        // existing cashfree_* columns one-for-one.
        $this->forge->addColumn('enrolments', [
            'payment_gateway'       => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true, 'after' => 'payment_mode'],
            'razorpay_order_id'     => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true, 'after' => 'cashfree_order_status'],
            'razorpay_payment_id'   => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true, 'after' => 'razorpay_order_id'],
            'razorpay_order_status' => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true, 'after' => 'razorpay_payment_id'],
        ]);

        // Razorpay subscriptions run against a Plan object (one per
        // distinct monthly amount) rather than Cashfree's inline
        // plan_details, so the plan id has to be tracked too.
        $this->forge->addColumn('autopay_subscriptions', [
            'gateway'                  => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'cashfree', 'after' => 'prant_id'],
            'razorpay_plan_id'         => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true, 'after' => 'cashfree_subscription_id'],
            'razorpay_subscription_id' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true, 'after' => 'razorpay_plan_id'],
        ]);
        $this->forge->addUniqueKey('razorpay_subscription_id', 'autopay_subscriptions_razorpay_sub_unique');
        $this->forge->processIndexes('autopay_subscriptions');
    }

    public function down(): void
    {
        $this->forge->dropKey('autopay_subscriptions', 'autopay_subscriptions_razorpay_sub_unique', true);
        $this->forge->dropColumn('autopay_subscriptions', ['gateway', 'razorpay_plan_id', 'razorpay_subscription_id']);
        $this->forge->dropColumn('enrolments', ['payment_gateway', 'razorpay_order_id', 'razorpay_payment_id', 'razorpay_order_status']);

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
        $this->forge->createTable('gateway_credentials_old');

        // Only the Cashfree row survives the downgrade (the original
        // schema had no concept of more than one provider per Prant) —
        // any Razorpay-only row is dropped, which is the correct
        // behaviour for a rollback of this exact migration.
        $db = db_connect();
        $db->query("
            INSERT INTO gateway_credentials_old
                (id, prant_id, provider, app_id_enc, secret_key_enc, merchant_id,
                 settlement_account_enc, ifsc, webhook_secret_enc, environment, status,
                 created_at, updated_at)
            SELECT
                id, prant_id, provider, app_id_enc, secret_key_enc, merchant_id,
                settlement_account_enc, ifsc, webhook_secret_enc, environment, status,
                created_at, updated_at
            FROM gateway_credentials
            WHERE provider = 'cashfree'
        ");

        $this->forge->dropTable('gateway_credentials', true);
        $this->forge->renameTable('gateway_credentials_old', 'gateway_credentials');
    }
}
