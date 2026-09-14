<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Adds a per-Prant list of enrolment-form languages (e.g. Dakshin
 * Karnataka -> kn,en,hi), stored as a comma-separated list of the same
 * codes I18n::LANGUAGES uses. Comma-separated rather than a join table:
 * it's a short, order-matters list (the enrolment form pins these to the
 * top of the language dropdown in this order) with no per-row metadata
 * of its own, so a join table would only add a query for no benefit.
 * Null/empty means "not configured yet" — the enrolment form falls back
 * to showing all 13 languages for that Prant, same as before this
 * column existed.
 */
class AddPrantLanguages extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('prants', [
            'languages' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true, 'after' => 'code'],
        ]);
    }

    public function down(): void
    {
        $this->forge->dropColumn('prants', 'languages');
    }
}
