<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateEmailBatchAttachments extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'                => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'batch_id'          => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'original_filename' => ['type' => 'VARCHAR', 'constraint' => 255],
            'stored_filename'   => ['type' => 'VARCHAR', 'constraint' => 191],
            'mime_type'         => ['type' => 'VARCHAR', 'constraint' => 127],
            'size_bytes'        => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'created_at'        => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('batch_id');
        $this->forge->addForeignKey('batch_id', 'email_batches', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('email_batch_attachments');
    }

    public function down()
    {
        $this->forge->dropTable('email_batch_attachments');
    }
}
