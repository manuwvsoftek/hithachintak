<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Holds the current password in reversible form (encrypted at rest via
 * App\Libraries\Secrets\Vault, same pattern as the Cashfree/MSG91/SMTP
 * credential columns) so an admin who set or reset a Karyakarta's — or
 * any managed account's — password can view it again later, not just
 * once at creation time. Null whenever the account holder has since set
 * their own password (first-login or forgot-password self-service),
 * since that one is theirs, not the admin's to see.
 */
class AddUserPasswordPlain extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('users', [
            'password_plain' => ['type' => 'TEXT', 'null' => true, 'after' => 'password_hash'],
        ]);
    }

    public function down(): void
    {
        $this->forge->dropColumn('users', 'password_plain');
    }
}
