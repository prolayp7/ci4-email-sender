<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateEmailTemplates extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'         => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'name'       => ['type' => 'VARCHAR', 'constraint' => 150],
            'subject'    => ['type' => 'VARCHAR', 'constraint' => 255],
            'html_body'  => ['type' => 'MEDIUMTEXT'],
            'text_body'  => ['type' => 'TEXT', 'null' => true],
            'status'     => ['type' => 'ENUM', 'constraint' => ['active', 'draft'], 'default' => 'active'],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->createTable('email_templates');
    }

    public function down()
    {
        $this->forge->dropTable('email_templates');
    }
}
