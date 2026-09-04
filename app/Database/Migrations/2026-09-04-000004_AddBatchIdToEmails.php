<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddBatchIdToEmails extends Migration
{
    public function up()
    {
        $this->forge->addColumn('emails', [
            'batch_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true, 'after' => 'deleted_at'],
        ]);

        $this->forge->addForeignKey('batch_id', 'email_batches', 'id', 'SET NULL', 'SET NULL', 'fk_emails_batch_id');
        $this->forge->processIndexes('emails');
    }

    public function down()
    {
        $this->forge->dropForeignKey('emails', 'fk_emails_batch_id');
        $this->forge->dropColumn('emails', 'batch_id');
    }
}
