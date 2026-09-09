<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddSchedulingToEmailBatches extends Migration
{
    public function up()
    {
        $this->forge->addColumn('email_batches', [
            // Existing batches were always sent immediately by the client-side
            // loop, so backfill them as 'completed' rather than leaving a
            // status that implies something still needs to happen.
            'status'            => ['type' => 'ENUM', 'constraint' => ['scheduled', 'sending', 'completed', 'cancelled'], 'default' => 'completed', 'after' => 'recipient_count'],
            'scheduled_at'      => ['type' => 'DATETIME', 'null' => true, 'after' => 'status'],
            // Emails sent per hour while working through a scheduled batch;
            // null means send every pending recipient in one run.
            'throttle_per_hour' => ['type' => 'INT', 'unsigned' => true, 'null' => true, 'after' => 'scheduled_at'],
        ]);
        $this->forge->addKey('status');
        $this->forge->addKey('scheduled_at');
        $this->forge->processIndexes('email_batches');

        // A scheduled batch's target list has to be known up front (it's
        // processed later by a CLI command, not the browser's send-one loop),
        // so -- unlike an immediate bulk send, which never persists its
        // recipient list server-side -- this table is the source of truth for
        // who's left to send to and who's already been handled.
        $this->forge->addField([
            'id'           => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'batch_id'     => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'recipient_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'status'       => ['type' => 'ENUM', 'constraint' => ['pending', 'sent', 'failed'], 'default' => 'pending'],
            'created_at'   => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['batch_id', 'status']);
        $this->forge->addForeignKey('batch_id', 'email_batches', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('recipient_id', 'recipients', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('email_batch_recipients');
    }

    public function down()
    {
        $this->forge->dropTable('email_batch_recipients');

        $this->forge->dropKey('email_batches', 'status');
        $this->forge->dropKey('email_batches', 'scheduled_at');
        $this->forge->dropColumn('email_batches', ['status', 'scheduled_at', 'throttle_per_hour']);
    }
}
