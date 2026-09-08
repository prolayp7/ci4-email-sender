<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddBouncedSuppressedToRecipientStatus extends Migration
{
    public function up()
    {
        $this->forge->modifyColumn('recipients', [
            'status' => ['name' => 'status', 'type' => 'ENUM', 'constraint' => ['active', 'unsubscribed', 'bounced', 'suppressed'], 'default' => 'active'],
        ]);
    }

    public function down()
    {
        // Shrinking the enum would fail outright if any row still uses a
        // value being removed -- reassign those first, same as a DBA would
        // have to before narrowing a live column.
        $this->db->table('recipients')->whereIn('status', ['bounced', 'suppressed'])->update(['status' => 'unsubscribed']);

        $this->forge->modifyColumn('recipients', [
            'status' => ['name' => 'status', 'type' => 'ENUM', 'constraint' => ['active', 'unsubscribed'], 'default' => 'active'],
        ]);
    }
}
