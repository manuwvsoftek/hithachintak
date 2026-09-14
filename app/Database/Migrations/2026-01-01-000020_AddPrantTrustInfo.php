<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Each Prant settles under its own registered Trust — this is its legal
 * identity for donation receipts and the public Contact Us page, entered
 * per Prant (individually or via the Settings & Integrations > Trust
 * Details bulk upload), not a single organisation-wide constant. Null
 * until an Admin fills it in, same as the Payment Gateway columns.
 */
class AddPrantTrustInfo extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('prants', [
            'trust_name'    => ['type' => 'VARCHAR', 'constraint' => 200, 'null' => true, 'after' => 'notes'],
            'trust_address' => ['type' => 'TEXT', 'null' => true, 'after' => 'trust_name'],
            'trust_pincode' => ['type' => 'VARCHAR', 'constraint' => 10, 'null' => true, 'after' => 'trust_address'],
            'trust_pan'     => ['type' => 'VARCHAR', 'constraint' => 10, 'null' => true, 'after' => 'trust_pincode'],
        ]);
    }

    public function down(): void
    {
        $this->forge->dropColumn('prants', ['trust_name', 'trust_address', 'trust_pincode', 'trust_pan']);
    }
}
