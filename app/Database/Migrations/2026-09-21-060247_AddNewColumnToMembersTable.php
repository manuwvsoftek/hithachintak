<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddNewColumnToMembersTable extends Migration
{
    public function up()
    {
         $this->forge->addColumn('members', [
            'gender' => ['type' => 'VARCHAR', 'constraint' => 15, 'null' => true, 'after' => 'age_or_dob'],
        ]);
    }

    public function down()
    {
         $this->forge->dropColumn('members', ['gender']);
    }
}
