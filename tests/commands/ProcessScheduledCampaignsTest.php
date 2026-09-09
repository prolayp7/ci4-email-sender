<?php

namespace Tests\Commands;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

final class ProcessScheduledCampaignsTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $refresh = true;
    protected $namespace = null;

    protected function setUp(): void
    {
        parent::setUp();
        $now = date('Y-m-d H:i:s');
        $this->db->table('users')->insert([
            'id' => 1, 'name' => 'Admin', 'email' => 'admin@test.com',
            'password_hash' => password_hash('x', PASSWORD_DEFAULT), 'role' => 'owner', 'status' => 'active',
            'created_at' => $now, 'updated_at' => $now,
        ]);
        $this->db->table('recipients')->insert([
            'id' => 1, 'name' => 'Jane', 'email' => 'jane@example.com', 'status' => 'active',
            'created_at' => $now, 'updated_at' => $now,
        ]);
        $this->db->table('recipients')->insert([
            'id' => 2, 'name' => 'John', 'email' => 'john@example.com', 'status' => 'active',
            'created_at' => $now, 'updated_at' => $now,
        ]);
    }

    private function insertBatch(array $overrides = []): int
    {
        $now = date('Y-m-d H:i:s');
        $this->db->table('email_batches')->insert(array_merge([
            'subject' => 'Hi', 'body_html' => '<p>Hi</p>', 'user_id' => 1, 'recipient_count' => 2,
            'status' => 'scheduled', 'scheduled_at' => date('Y-m-d H:i:s', strtotime('-1 minute')),
            'created_at' => $now,
        ], $overrides));

        return (int) $this->db->insertID();
    }

    private function addPendingRecipient(int $batchId, int $recipientId): void
    {
        $this->db->table('email_batch_recipients')->insert([
            'batch_id' => $batchId, 'recipient_id' => $recipientId, 'status' => 'pending',
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function testProcessesAllPendingRecipientsWhenNoThrottleIsSet(): void
    {
        $batchId = $this->insertBatch();
        $this->addPendingRecipient($batchId, 1);
        $this->addPendingRecipient($batchId, 2);

        command('campaigns:process');

        // No SMTP configured in this test env, so both sends fail -- but
        // "processed" (attempted) either way, which is what this test checks.
        $this->seeInDatabase('email_batch_recipients', ['batch_id' => $batchId, 'recipient_id' => 1, 'status' => 'failed']);
        $this->seeInDatabase('email_batch_recipients', ['batch_id' => $batchId, 'recipient_id' => 2, 'status' => 'failed']);
        $this->seeInDatabase('email_batches', ['id' => $batchId, 'status' => 'completed']);
    }

    public function testLeavesFutureScheduledBatchesUntouched(): void
    {
        $batchId = $this->insertBatch(['scheduled_at' => date('Y-m-d H:i:s', strtotime('+1 day'))]);
        $this->addPendingRecipient($batchId, 1);

        command('campaigns:process');

        $this->seeInDatabase('email_batch_recipients', ['batch_id' => $batchId, 'recipient_id' => 1, 'status' => 'pending']);
        $this->seeInDatabase('email_batches', ['id' => $batchId, 'status' => 'scheduled']);
    }

    public function testLeavesCancelledBatchesUntouched(): void
    {
        $batchId = $this->insertBatch(['status' => 'cancelled']);
        $this->addPendingRecipient($batchId, 1);

        command('campaigns:process');

        $this->seeInDatabase('email_batch_recipients', ['batch_id' => $batchId, 'recipient_id' => 1, 'status' => 'pending']);
        $this->seeInDatabase('email_batches', ['id' => $batchId, 'status' => 'cancelled']);
    }

    public function testThrottleLimitsHowManyAreProcessedInOneRun(): void
    {
        $batchId = $this->insertBatch(['throttle_per_hour' => 60]); // 60/hour -> 1 per one-minute tick
        $this->addPendingRecipient($batchId, 1);
        $this->addPendingRecipient($batchId, 2);

        command('campaigns:process');

        $remaining = $this->db->table('email_batch_recipients')->where('batch_id', $batchId)->where('status', 'pending')->countAllResults();
        $this->assertSame(1, $remaining);
        $this->seeInDatabase('email_batches', ['id' => $batchId, 'status' => 'sending']);
    }

    public function testMarksBatchSendingThenCompletedAcrossMultipleRuns(): void
    {
        $batchId = $this->insertBatch(['throttle_per_hour' => 60]);
        $this->addPendingRecipient($batchId, 1);
        $this->addPendingRecipient($batchId, 2);

        command('campaigns:process');
        $this->seeInDatabase('email_batches', ['id' => $batchId, 'status' => 'sending']);

        command('campaigns:process');
        $this->seeInDatabase('email_batches', ['id' => $batchId, 'status' => 'completed']);
    }
}
