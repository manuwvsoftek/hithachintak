<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateReceiptSequences extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'            => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'programme_code'=> ['type' => 'VARCHAR', 'constraint' => 10],
            'year'          => ['type' => 'SMALLINT', 'unsigned' => true],
            'next_number'   => ['type' => 'INT', 'unsigned' => true, 'default' => 1],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['programme_code', 'year']);
        $this->forge->createTable('receipt_sequences');
    }

    public function down(): void
    {
        $this->forge->dropTable('receipt_sequences', true);
    }
}
