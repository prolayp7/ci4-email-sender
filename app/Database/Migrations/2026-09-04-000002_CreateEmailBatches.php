<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateEmailBatches extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'               => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'subject'          => ['type' => 'VARCHAR', 'constraint' => 255],
            'body_html'        => ['type' => 'MEDIUMTEXT'],
            'template_id'      => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'user_id'          => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'recipient_count'  => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'default' => 0],
            'created_at'       => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addForeignKey('template_id', 'email_templates', 'id', 'SET NULL', 'SET NULL');
        $this->forge->addForeignKey('user_id', 'users', 'id', '', 'CASCADE');
        $this->forge->createTable('email_batches');
    }

    public function down()
    {
        $this->forge->dropTable('email_batches');
    }
}
