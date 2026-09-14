<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Lets a Pranta Admin be assigned multiple Prants (or "all"), a Jila
 * Admin multiple Jilas within their one fixed Prant (or "all" within
 * it), and a Prakhand Admin multiple Prakhands within their one fixed
 * Jila (or "all" within it) — each role's own location tier becomes a
 * set instead of a single value; the tier(s) above it stay exactly as
 * before (a Jila Admin still has exactly one prant_id, a Prakhand Admin
 * still has exactly one prant_id + jila_id).
 *
 * users.prant_id/jila_id/prakhand_id are kept as-is and still authoritative
 * for Jila/Prakhand/Karyakarta's fixed upper tiers. For a role whose own
 * tier is now a set, an empty junction table (no rows, scope_all = 0)
 * falls back to that same legacy single column as its one assignment —
 * an existing account needs no data migration to keep working exactly
 * as it did before this.
 */
class CreateUserLocationScopes extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('users', [
            'scope_all' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'default'    => 0,
                'null'       => false,
                'after'      => 'prakhand_id',
            ],
        ]);

        $this->createScopeTable('user_prant_scopes', 'prant_id', 'prants');
        $this->createScopeTable('user_jila_scopes', 'jila_id', 'jilas');
        $this->createScopeTable('user_prakhand_scopes', 'prakhand_id', 'prakhands');
    }

    public function down(): void
    {
        $this->forge->dropTable('user_prakhand_scopes', true);
        $this->forge->dropTable('user_jila_scopes', true);
        $this->forge->dropTable('user_prant_scopes', true);
        $this->forge->dropColumn('users', 'scope_all');
    }

    private function createScopeTable(string $table, string $fkColumn, string $fkTable): void
    {
        $this->forge->addField([
            'id'        => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'user_id'   => ['type' => 'INT', 'unsigned' => true],
            $fkColumn   => ['type' => 'INT', 'unsigned' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey(['user_id', $fkColumn]);
        $this->forge->addForeignKey('user_id', 'users', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey($fkColumn, $fkTable, 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable($table);
    }
}
