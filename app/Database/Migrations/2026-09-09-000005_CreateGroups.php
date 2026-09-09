<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateGroups extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'         => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'name'       => ['type' => 'VARCHAR', 'constraint' => 100],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('name');
        $this->forge->createTable('groups');

        $this->forge->addField([
            'recipient_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'group_id'     => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
        ]);
        $this->forge->addPrimaryKey(['recipient_id', 'group_id']);
        $this->forge->addForeignKey('recipient_id', 'recipients', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('group_id', 'groups', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('recipient_groups');
    }

    public function down()
    {
        $this->forge->dropTable('recipient_groups');
        $this->forge->dropTable('groups');
    }
}
