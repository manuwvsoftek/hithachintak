<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddNewColumnToUsers extends Migration
{
    public function up()
    {
          $this->forge->addColumn('users', [
            'dob' => ['type' => 'DATE', 'null' => true],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('users', ['dob']);
    }
}
