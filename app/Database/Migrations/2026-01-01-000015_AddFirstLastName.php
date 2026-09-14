<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * The enrolment form now captures First name (mandatory) + Last name
 * (optional) instead of one Full name field, for the head of family and
 * every additional family member alike. `name` stays on both tables as
 * the derived "first + last" display value — every existing read site
 * (receipts, SMS, search, exports, reports) keeps working unchanged; only
 * the write path and anything that specifically needs the first name
 * alone (the receipt's per-person family list) touch the new columns.
 */
class AddFirstLastName extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('members', [
            'first_name' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true, 'after' => 'name'],
            'last_name'  => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true, 'after' => 'first_name'],
        ]);

        $this->forge->addColumn('enrolment_family_members', [
            'first_name' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true, 'after' => 'name'],
            'last_name'  => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true, 'after' => 'first_name'],
        ]);
    }

    public function down(): void
    {
        $this->forge->dropColumn('members', ['first_name', 'last_name']);
        $this->forge->dropColumn('enrolment_family_members', ['first_name', 'last_name']);
    }
}
