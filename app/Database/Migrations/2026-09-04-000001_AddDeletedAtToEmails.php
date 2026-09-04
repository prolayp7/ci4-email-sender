<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddDeletedAtToEmails extends Migration
{
    public function up()
    {
        $this->forge->addColumn('emails', [
            'deleted_at' => ['type' => 'DATETIME', 'null' => true, 'after' => 'updated_at'],
        ]);

        $this->forge->addKey('deleted_at');
        $this->forge->processIndexes('emails');
    }

    public function down()
    {
        $this->forge->dropKey('emails', 'deleted_at');
        $this->forge->dropColumn('emails', 'deleted_at');
    }
}
