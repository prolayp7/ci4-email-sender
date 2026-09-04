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
        $result->assertRedirectTo('/emails/drafts');

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
        $this->loggedIn()->post('/emails/send-draft/1')->assertRedirectTo('/emails/drafts');
        $row = $this->db->table('emails')->where('id', 1)->get()->getRowArray();
        $this->assertSame('sent', $row['status']);
        $this->assertSame(0, (int) $row['attempt_count']);
    }

    public function testRetryDoesNotRemoveOriginalRecordsAttachment(): void
    {
        $this->insertFailedEmail();
        $path = WRITEPATH . 'uploads/retry_test_' . uniqid() . '.txt';
        file_put_contents($path, 'x');
        $this->db->table('email_attachments')->insert([
            'email_id' => 1, 'original_filename' => 'a.txt', 'stored_filename' => basename($path),
            'mime_type' => 'text/plain', 'size_bytes' => 1, 'created_at' => date('Y-m-d H:i:s'),
        ]);

        $this->loggedIn()->post('/emails/retry/1')->assertRedirectTo('/emails');

        $this->seeInDatabase('email_attachments', ['email_id' => 1, 'original_filename' => 'a.txt']);

        @unlink($path);
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

    public function testDetailPageListsAttachmentsWithDownloadLink(): void
    {
        $this->insertFailedEmail(['status' => 'sent']);
        $this->db->table('email_attachments')->insert([
            'email_id' => 1, 'original_filename' => 'report.txt', 'stored_filename' => 'stored-report.txt',
            'mime_type' => 'text/plain', 'size_bytes' => 13, 'created_at' => date('Y-m-d H:i:s'),
        ]);

        $result = $this->loggedIn()->get('/emails/1');

        $result->assertSee('report.txt');
        $result->assertSee('/emails/1/attachments/');
    }

    public function testDetailPageWithNoAttachmentsShowsNoAttachmentsSection(): void
    {
        $this->insertFailedEmail();
        $this->loggedIn()->get('/emails/1')->assertDontSee('Attachments');
    }

    public function testDeletingEmailMovesItToTrashAndHidesItFromHistory(): void
    {
        $this->insertFailedEmail(['subject' => 'Discard me']);

        // delete() now uses redirect()->back() so it returns to whichever page
        // (History or Drafts) the request came from. previous_url() reads
        // session('_ci_previous_url') first -- and withSession() replaces the
        // whole simulated session per call, so it has to be seeded directly
        // rather than relying on an earlier request's side effect.
        $this->withSession([
            'isLoggedIn' => true, 'user_id' => 1, 'user_role' => 'owner', 'user_name' => 'Admin',
            '_ci_previous_url' => site_url('emails'),
        ])->post('/emails/delete/1')->assertRedirectTo('/emails');

        $row = $this->db->table('emails')->where('id', 1)->get()->getRowArray();
        $this->assertNotNull($row['deleted_at']);
        $this->loggedIn()->get('/emails')->assertDontSee('Discard me');
        $this->loggedIn()->get('/emails/trash')->assertSee('Discard me');
    }

    public function testRestoreReturnsEmailToHistory(): void
    {
        $this->insertFailedEmail(['subject' => 'Restore me', 'deleted_at' => date('Y-m-d H:i:s')]);

        $this->loggedIn()->post('/emails/restore/1')->assertRedirectTo('/emails/trash');

        $row = $this->db->table('emails')->where('id', 1)->get()->getRowArray();
        $this->assertNull($row['deleted_at']);
        $this->loggedIn()->get('/emails')->assertSee('Restore me');
    }

    public function testPermanentDeleteRemovesEmailAndAttachments(): void
    {
        $this->insertFailedEmail(['deleted_at' => date('Y-m-d H:i:s')]);
        $path = WRITEPATH . 'uploads/trash_test_' . uniqid() . '.txt';
        file_put_contents($path, 'delete me');
        $this->db->table('email_attachments')->insert([
            'email_id' => 1, 'original_filename' => 'trash.txt', 'stored_filename' => basename($path),
            'mime_type' => 'text/plain', 'size_bytes' => 9, 'created_at' => date('Y-m-d H:i:s'),
        ]);

        $this->loggedIn()->post('/emails/destroy/1')->assertRedirectTo('/emails/trash');

        $this->assertSame(0, $this->db->table('emails')->where('id', 1)->countAllResults());
        $this->assertSame(0, $this->db->table('email_attachments')->where('email_id', 1)->countAllResults());
        $this->assertFileDoesNotExist($path);
    }

    public function testDestroyOnNonTrashedRowIsRejected(): void
    {
        $this->insertFailedEmail();

        $this->loggedIn()->post('/emails/destroy/1')->assertRedirectTo('/emails/trash');

        $this->seeInDatabase('emails', ['id' => 1]);
    }

    public function testDraftsPageShowsOnlyNonTrashedDrafts(): void
    {
        $this->insertFailedEmail(['status' => 'draft', 'subject' => 'Visible draft', 'error_message' => null]);
        $this->db->table('emails')->insert([
            'id' => 2, 'recipient_id' => 1, 'user_id' => 1, 'subject' => 'Trashed draft', 'body_html' => '<p>Hi</p>',
            'status' => 'draft', 'attempt_count' => 0, 'deleted_at' => date('Y-m-d H:i:s'),
            'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $this->insertFailedEmail(['id' => 3, 'status' => 'sent', 'subject' => 'Sent one']);

        $result = $this->loggedIn()->get('/emails/drafts');

        $result->assertSee('Visible draft');
        $result->assertDontSee('Trashed draft');
        $result->assertDontSee('Sent one');
    }

    public function testHistoryGroupsBatchEmailsIntoOneSummaryRow(): void
    {
        $now = date('Y-m-d H:i:s');
        $this->db->table('email_batches')->insert([
            'id' => 1, 'subject' => 'Newsletter', 'body_html' => '<p>Hi</p>', 'user_id' => 1,
            'recipient_count' => 2, 'created_at' => $now,
        ]);
        $this->insertFailedEmail(['id' => 1, 'status' => 'sent', 'subject' => 'Newsletter', 'batch_id' => 1, 'error_message' => null]);
        $this->insertFailedEmail(['id' => 2, 'status' => 'failed', 'subject' => 'Newsletter', 'batch_id' => 1]);

        $result = $this->loggedIn()->get('/emails');

        $result->assertSee('Newsletter');
        $result->assertSee('2 recipients');
    }
}
