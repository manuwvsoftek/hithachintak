<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateUsersTable extends Migration
{
    public function up()
    {
       $this->forge->addColumn('users', [
            'ayam' => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true, 'after' => 'profession'],
        ]);
    }

    public function down()
    {
         $this->forge->dropColumn('users', ['ayam']);
    }
}
