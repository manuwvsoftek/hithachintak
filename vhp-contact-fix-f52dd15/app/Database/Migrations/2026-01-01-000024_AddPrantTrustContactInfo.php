<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * The Contact Us page needs a way to actually reach a Prant's Trust, not
 * just its legal name/address/PAN — add a phone and email per Prant,
 * same optional/nullable pattern as the rest of Trust Details.
 */
class AddPrantTrustContactInfo extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('prants', [
            'trust_phone' => ['type' => 'VARCHAR', 'constraint' => 15, 'null' => true, 'after' => 'trust_pan'],
            'trust_email' => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true, 'after' => 'trust_phone'],
        ]);
    }

    public function down(): void
    {
        $this->forge->dropColumn('prants', ['trust_phone', 'trust_email']);
    }
}
