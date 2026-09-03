<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateSmtpSettings extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'                 => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'label'              => ['type' => 'VARCHAR', 'constraint' => 100],
            'host'               => ['type' => 'VARCHAR', 'constraint' => 191],
            'port'               => ['type' => 'SMALLINT', 'unsigned' => true],
            'encryption'         => ['type' => 'ENUM', 'constraint' => ['tls', 'ssl']],
            'username'           => ['type' => 'VARCHAR', 'constraint' => 191],
            'password_encrypted' => ['type' => 'TEXT'],
            'from_email'         => ['type' => 'VARCHAR', 'constraint' => 191],
            'from_name'          => ['type' => 'VARCHAR', 'constraint' => 150],
            'is_active'          => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'created_at'         => ['type' => 'DATETIME', 'null' => true],
            'updated_at'         => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->createTable('smtp_settings');
    }

    public function down()
    {
        $this->forge->dropTable('smtp_settings');
    }
}
