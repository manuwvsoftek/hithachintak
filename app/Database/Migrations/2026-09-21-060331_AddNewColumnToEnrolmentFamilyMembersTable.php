<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddNewColumnToEnrolmentFamilyMembersTable extends Migration
{
    public function up()
    {
        $this->forge->addColumn('enrolment_family_members', [
            'gender' => ['type' => 'VARCHAR', 'constraint' => 15, 'null' => true, 'after' => 'age_or_dob'],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('enrolment_family_members', ['gender']);
    }
}
