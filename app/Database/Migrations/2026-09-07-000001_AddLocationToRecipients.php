<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddLocationToRecipients extends Migration
{
    public function up()
    {
        $this->forge->addColumn('recipients', [
            'location' => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true, 'after' => 'company'],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('recipients', 'location');
    }
}
