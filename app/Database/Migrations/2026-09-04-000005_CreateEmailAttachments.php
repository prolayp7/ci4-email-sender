<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateEmailAttachments extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'                => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'email_id'          => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'original_filename' => ['type' => 'VARCHAR', 'constraint' => 255],
            'stored_filename'   => ['type' => 'VARCHAR', 'constraint' => 191],
            'mime_type'         => ['type' => 'VARCHAR', 'constraint' => 127],
            'size_bytes'        => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'created_at'        => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('email_id');
        $this->forge->addKey('stored_filename');
        $this->forge->addForeignKey('email_id', 'emails', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('email_attachments');
    }

    public function down()
    {
        $this->forge->dropTable('email_attachments');
    }
}
