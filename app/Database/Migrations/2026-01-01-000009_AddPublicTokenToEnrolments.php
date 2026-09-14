<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * A short, random (non-sequential) token used for the unauthenticated
 * public receipt link sent over SMS/WhatsApp — deliberately not the
 * auto-increment id or the human-readable receipt_no, so a link can't be
 * used to enumerate other members' receipts.
 */
class AddPublicTokenToEnrolments extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('enrolments', [
            'public_token' => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true, 'after' => 'receipt_no'],
        ]);
        $this->forge->addUniqueKey('public_token', 'enrolments_public_token_unique');
        $this->forge->processIndexes('enrolments');
    }

    public function down(): void
    {
        $this->forge->dropKey('enrolments', 'enrolments_public_token_unique', true);
        $this->forge->dropColumn('enrolments', 'public_token');
    }
}
