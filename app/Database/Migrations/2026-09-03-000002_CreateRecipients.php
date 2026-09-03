<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateRecipients extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'         => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'name'       => ['type' => 'VARCHAR', 'constraint' => 150],
            'email'      => ['type' => 'VARCHAR', 'constraint' => 191],
            'company'    => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
            'phone'      => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true],
            'status'     => ['type' => 'ENUM', 'constraint' => ['active', 'unsubscribed'], 'default' => 'active'],
            'notes'      => ['type' => 'TEXT', 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('email');
        $this->forge->createTable('recipients');
    }

    public function down()
    {
        $this->forge->dropTable('recipients');
    }
}
