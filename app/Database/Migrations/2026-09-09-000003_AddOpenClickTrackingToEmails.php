<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddOpenClickTrackingToEmails extends Migration
{
    public function up()
    {
        $this->forge->addColumn('emails', [
            // Opaque per-email identifier embedded in the tracking pixel/click
            // URLs -- looked up by an unauthenticated route, so it must not be
            // a guessable sequential id.
            'tracking_token' => ['type' => 'VARCHAR', 'constraint' => 64, 'null' => true, 'after' => 'message_id'],
        ]);
        $this->forge->addUniqueKey('tracking_token');
        $this->forge->processIndexes('emails');

        $this->forge->modifyColumn('emails', [
            'status' => ['name' => 'status', 'type' => 'ENUM', 'constraint' => ['pending', 'sent', 'failed', 'draft', 'bounced'], 'default' => 'pending'],
        ]);

        $this->forge->addField([
            'id'         => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'email_id'   => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'type'       => ['type' => 'ENUM', 'constraint' => ['open', 'click']],
            'url'        => ['type' => 'TEXT', 'null' => true],
            'ip_address' => ['type' => 'VARCHAR', 'constraint' => 45, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['email_id', 'type']);
        $this->forge->addForeignKey('email_id', 'emails', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('email_events');
    }

    public function down()
    {
        $this->forge->dropTable('email_events');

        // Shrinking the enum would fail outright if any row still uses the
        // value being removed -- see AddBouncedSuppressedToRecipientStatus
        // for the same issue on recipients.status.
        $this->db->table('emails')->where('status', 'bounced')->update(['status' => 'failed']);

        $this->forge->modifyColumn('emails', [
            'status' => ['name' => 'status', 'type' => 'ENUM', 'constraint' => ['pending', 'sent', 'failed', 'draft'], 'default' => 'pending'],
        ]);

        $this->forge->dropKey('emails', 'tracking_token');
        $this->forge->dropColumn('emails', 'tracking_token');
    }
}
