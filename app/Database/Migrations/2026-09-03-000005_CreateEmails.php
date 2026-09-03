<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateEmails extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'            => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'recipient_id'  => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'template_id'   => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'user_id'       => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'subject'       => ['type' => 'VARCHAR', 'constraint' => 255],
            'body_html'     => ['type' => 'MEDIUMTEXT'],
            'body_text'     => ['type' => 'TEXT', 'null' => true],
            'status'        => ['type' => 'ENUM', 'constraint' => ['pending', 'sent', 'failed', 'draft'], 'default' => 'pending'],
            'error_message' => ['type' => 'TEXT', 'null' => true],
            'message_id'    => ['type' => 'VARCHAR', 'constraint' => 191, 'null' => true],
            'attempt_count' => ['type' => 'SMALLINT', 'unsigned' => true, 'default' => 0],
            'sent_at'       => ['type' => 'DATETIME', 'null' => true],
            'created_at'    => ['type' => 'DATETIME', 'null' => true],
            'updated_at'    => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('recipient_id');
        $this->forge->addKey('status');
        $this->forge->addKey('sent_at');
        $this->forge->addForeignKey('recipient_id', 'recipients', 'id', '', 'CASCADE');
        $this->forge->addForeignKey('template_id', 'email_templates', 'id', 'SET NULL', 'SET NULL');
        $this->forge->addForeignKey('user_id', 'users', 'id', '', 'CASCADE');
        $this->forge->createTable('emails');
    }

    public function down()
    {
        $this->forge->dropTable('emails');
    }
}
