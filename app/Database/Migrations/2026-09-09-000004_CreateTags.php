<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateTags extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'         => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'name'       => ['type' => 'VARCHAR', 'constraint' => 60],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('name');
        $this->forge->createTable('tags');

        $this->forge->addField([
            'recipient_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'tag_id'       => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
        ]);
        $this->forge->addPrimaryKey(['recipient_id', 'tag_id']);
        $this->forge->addForeignKey('recipient_id', 'recipients', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('tag_id', 'tags', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('recipient_tags');
    }

    public function down()
    {
        $this->forge->dropTable('recipient_tags');
        $this->forge->dropTable('tags');
    }
}
