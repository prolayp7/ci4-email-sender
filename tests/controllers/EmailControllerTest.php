<?php

namespace Tests\Controllers;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

final class EmailControllerTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

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
    }

    private function loggedIn(): self
    {
        return $this->withSession(['isLoggedIn' => true, 'user_id' => 1, 'user_role' => 'owner', 'user_name' => 'Admin']);
    }

    private function insertFailedEmail(array $overrides = []): void
    {
        $now = date('Y-m-d H:i:s');
        $this->db->table('emails')->insert(array_merge([
            'id' => 1, 'recipient_id' => 1, 'user_id' => 1, 'subject' => 'Hi', 'body_html' => '<p>Hi</p>',
            'status' => 'failed', 'error_message' => 'SMTP connection refused', 'attempt_count' => 1,
            'created_at' => $now, 'updated_at' => $now,
        ], $overrides));
    }

    public function testHistoryListsEmailsWithFilter(): void
    {
        $this->insertFailedEmail();
        $result = $this->loggedIn()->get('/emails?status=failed');
        $result->assertStatus(200);
        $result->assertSee('Hi');
        $result->assertSee('jane@example.com');
    }

    public function testHistoryExcludesDraftsByDefault(): void
    {
        $this->insertFailedEmail(['status' => 'draft', 'subject' => 'Hidden draft']);
        $this->loggedIn()->get('/emails')->assertDontSee('Hidden draft');
    }

    public function testDraftFilterShowsDraftsAndOffersSend(): void
    {
        $this->insertFailedEmail(['status' => 'draft', 'subject' => 'My draft', 'error_message' => null]);
        $result = $this->loggedIn()->get('/emails?status=draft');
        $result->assertSee('My draft');
        $result->assertSee('/emails/send-draft/1');
    }

    public function testDetailPageShowsErrorMessageAndSandboxesBody(): void
    {
        $this->insertFailedEmail(['body_html' => '<script>alert(1)</script>']);
        $result = $this->loggedIn()->get('/emails/1');
        $result->assertSee('SMTP connection refused');
        $this->assertStringContainsString('sandbox', $result->getBody());
        $this->assertStringNotContainsString('<script>alert(1)</script>', $result->getBody());
    }

    public function testRetryPreservesRecordAndIncrementsAttempt(): void
    {
        $this->insertFailedEmail();
        $result = $this->loggedIn()->post('/emails/retry/1');
        $result->assertRedirectTo('/emails');
        $row = $this->db->table('emails')->where('id', 1)->get()->getRowArray();
        $this->assertNotNull($row);
        $this->assertSame('failed', $row['status']);
        $this->assertSame(2, (int) $row['attempt_count']);
        $this->assertSame(1, $this->db->table('emails')->countAllResults());
    }

    public function testNonFailedEmailCannotBeRetried(): void
    {
        $this->insertFailedEmail(['status' => 'sent']);
        $this->loggedIn()->post('/emails/retry/1')->assertRedirectTo('/emails');
        $row = $this->db->table('emails')->where('id', 1)->get()->getRowArray();
        $this->assertSame(1, (int) $row['attempt_count']);
    }

    public function testRetryOfNowUnsubscribedRecipientStillRecordsAttempt(): void
    {
        $this->insertFailedEmail();
        $this->db->table('recipients')->where('id', 1)->update(['status' => 'unsubscribed']);

        $this->loggedIn()->post('/emails/retry/1')->assertRedirectTo('/emails');

        $row = $this->db->table('emails')->where('id', 1)->get()->getRowArray();
        $this->assertSame(2, (int) $row['attempt_count']);
        $this->assertSame('Recipient has unsubscribed.', $row['error_message']);
    }

    public function testSendDraftAttemptsDeliveryAndKeepsSameRecordId(): void
    {
        $this->insertFailedEmail(['status' => 'draft', 'subject' => 'My draft', 'error_message' => null, 'attempt_count' => 0]);

        $result = $this->loggedIn()->post('/emails/send-draft/1');
        $result->assertRedirectTo('/emails?status=draft');

        // No SMTP configured in this test, so it must land on failed -- the
        // point of this test is that the ORIGINAL row #1 is the one updated
        // (no duplicate row created), not that delivery succeeds.
        $row = $this->db->table('emails')->where('id', 1)->get()->getRowArray();
        $this->assertNotNull($row);
        $this->assertSame('failed', $row['status']);
        $this->assertSame(1, (int) $row['attempt_count']);
        $this->assertSame(1, $this->db->table('emails')->countAllResults());
    }

    public function testNonDraftEmailCannotBeSentAsDraft(): void
    {
        $this->insertFailedEmail(['status' => 'sent', 'attempt_count' => 0]);
        $this->loggedIn()->post('/emails/send-draft/1')->assertRedirectTo('/emails');
        $row = $this->db->table('emails')->where('id', 1)->get()->getRowArray();
        $this->assertSame('sent', $row['status']);
        $this->assertSame(0, (int) $row['attempt_count']);
    }

    public function testAttachmentDownloadStreamsFileWithOriginalName(): void
    {
        $this->insertFailedEmail(['status' => 'sent']);
        $path = WRITEPATH . 'uploads/dl_test_' . uniqid() . '.txt';
        file_put_contents($path, 'file contents');
        $this->db->table('email_attachments')->insert([
            'email_id' => 1, 'original_filename' => 'report.txt', 'stored_filename' => basename($path),
            'mime_type' => 'text/plain', 'size_bytes' => 13, 'created_at' => date('Y-m-d H:i:s'),
        ]);
        $attachmentId = (int) $this->db->table('email_attachments')->where('email_id', 1)->get()->getRow()->id;

        $result = $this->loggedIn()->get('/emails/1/attachments/' . $attachmentId);

        $result->assertOK();
        $result->assertHeader('Content-Disposition', 'attachment; filename="report.txt"');

        @unlink($path);
    }

    public function testAttachmentDownloadMismatchedPairReturns404(): void
    {
        $this->insertFailedEmail(['status' => 'sent']);
        $this->loggedIn()->get('/emails/1/attachments/999')->assertStatus(404);
    }
}
