<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * The enrolment form's family-member rows collect Profession and Contact
 * number alongside Name/Age (per the Hithachintak prototype) — both
 * optional, unlike Name/Age which stay required.
 */
class AddFamilyMemberFields extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('enrolment_family_members', [
            'profession'     => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true, 'after' => 'age_or_dob'],
            'contact_number' => ['type' => 'VARCHAR', 'constraint' => 15, 'null' => true, 'after' => 'profession'],
        ]);
    }

    public function down(): void
    {
        $this->forge->dropColumn('enrolment_family_members', ['profession', 'contact_number']);
    }
}
