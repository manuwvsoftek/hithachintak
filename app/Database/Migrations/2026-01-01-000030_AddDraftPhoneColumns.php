<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Promotes the phone number already sitting inside each draft's `payload`
 * JSON into its own column — `user_drafts.name` and
 * `enrolment_drafts.member_name` got the same treatment when those tables
 * were created, for the same reason: the Pending Drafts search box needs
 * a real column to query, not a LIKE over a JSON blob. Existing rows are
 * backfilled from their own payload here so search works on them
 * immediately, not just on drafts saved after this migration runs.
 */
class AddDraftPhoneColumns extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('user_drafts', [
            'phone' => ['type' => 'VARCHAR', 'constraint' => 15, 'null' => true, 'after' => 'name'],
        ]);
        $this->forge->addColumn('enrolment_drafts', [
            'member_phone' => ['type' => 'VARCHAR', 'constraint' => 15, 'null' => true, 'after' => 'member_name'],
        ]);

        $db = db_connect();

        $userDrafts = $db->table('user_drafts')->select('id, payload')->get()->getResultArray();
        foreach ($userDrafts as $row) {
            $payload = json_decode($row['payload'], true);
            $phone   = trim((string) ($payload['phone'] ?? ''));
            if ($phone !== '') {
                $db->table('user_drafts')->where('id', $row['id'])->update(['phone' => $phone]);
            }
        }

        $enrolmentDrafts = $db->table('enrolment_drafts')->select('id, payload')->get()->getResultArray();
        foreach ($enrolmentDrafts as $row) {
            $payload = json_decode($row['payload'], true);
            $phone   = trim((string) ($payload['member_phone'] ?? ''));
            if ($phone !== '') {
                $db->table('enrolment_drafts')->where('id', $row['id'])->update(['member_phone' => $phone]);
            }
        }
    }

    public function down(): void
    {
        $this->forge->dropColumn('user_drafts', 'phone');
        $this->forge->dropColumn('enrolment_drafts', 'member_phone');
    }
}
