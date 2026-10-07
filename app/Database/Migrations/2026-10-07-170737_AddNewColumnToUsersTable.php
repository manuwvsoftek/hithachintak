<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddNewColumnToUsersTable extends Migration
{
    public function up()
    {
          $this->forge->addColumn('users', [
            'gender' => ['type' => 'VARCHAR', 'constraint' => 10, 'null' => true],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('users', ['gender']);
    }
}
