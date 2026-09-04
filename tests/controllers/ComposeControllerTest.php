<?php

namespace Tests\Controllers;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

require_once __DIR__ . '/../_support/Files/uploaded_file_test_overrides.php';

final class ComposeControllerTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $refresh = true;
    protected $namespace = null;

    private function loggedIn(): self
    {
        // Idempotent: bulk-send tests call loggedIn() more than once per test
        // (once per client-driven request), so re-inserting the same user
        // row must not blow up on the duplicate key.
        if ($this->db->table('users')->where('id', 1)->countAllResults() === 0) {
            $this->db->table('users')->insert([
                'id' => 1, 'name' => 'Admin', 'email' => 'admin@test.com',
                'password_hash' => password_hash('x', PASSWORD_DEFAULT), 'role' => 'owner', 'status' => 'active',
                'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
            ]);
        }
        return $this->withSession(['isLoggedIn' => true, 'user_id' => 1, 'user_role' => 'owner', 'user_name' => 'Admin']);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        service('superglobals')->setFilesArray([]);
    }

    public function testComposePageLoads(): void
    {
        $result = $this->loggedIn()->get('/compose');
        $result->assertStatus(200);
        $result->assertSee('Compose Email');
    }

    public function testSendWithoutSmtpRecordsFailure(): void
    {
        $this->db->table('recipients')->insert([
            'id' => 1, 'name' => 'Jane', 'email' => 'jane@example.com', 'status' => 'active',
            'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $result = $this->loggedIn()->post('/compose/send', [
            'recipient_id' => 1, 'subject' => 'Hi', 'body_html' => '<p>Hi</p>',
        ]);

        $result->assertOK();
        $this->seeInDatabase('emails', ['recipient_id' => 1, 'status' => 'failed']);
    }

    public function testSaveDraftStoresWithoutSending(): void
    {
        $this->db->table('recipients')->insert([
            'id' => 1, 'name' => 'Jane', 'email' => 'jane@example.com', 'status' => 'active',
            'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $result = $this->loggedIn()->post('/compose/draft', [
            'recipient_id' => 1, 'subject' => 'Draft subject', 'body_html' => '<p>Draft</p>',
        ]);

        $result->assertOK();
        $this->seeInDatabase('emails', ['recipient_id' => 1, 'status' => 'draft', 'subject' => 'Draft subject']);
    }

    public function testSendPersistsAttachmentRecordAndKeepsFileOnDisk(): void
    {
        $this->db->table('recipients')->insert([
            'id' => 1, 'name' => 'Jane', 'email' => 'jane@example.com', 'status' => 'active',
            'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $file = WRITEPATH . 'uploads/test_attach_' . uniqid() . '.txt';
        file_put_contents($file, 'hello');
        $fileSize = filesize($file);

        // Inject $_FILES data directly using the framework's superglobals service
        // getFileMultiple expects an array-indexed field (name, type, tmp_name, error, size all arrays)
        service('superglobals')->setFilesArray([
            'attachments' => [
                'name'     => ['note.txt'],
                'type'     => ['text/plain'],
                'tmp_name' => [$file],
                'error'    => [UPLOAD_ERR_OK],
                'size'     => [$fileSize],
            ],
        ]);

        $result = $this->loggedIn()->post('/compose/send', [
            'recipient_id' => 1, 'subject' => 'Hi', 'body_html' => '<p>Hi</p>',
        ]);

        $result->assertOK();
        $emailId = (int) $this->db->table('emails')->where('recipient_id', 1)->get()->getRow()->id;
        $this->seeInDatabase('email_attachments', ['email_id' => $emailId, 'original_filename' => 'note.txt']);

        $storedName = $this->db->table('email_attachments')->where('email_id', $emailId)->get()->getRow()->stored_filename;
        $this->assertFileExists(WRITEPATH . 'uploads/' . $storedName);

        @unlink(WRITEPATH . 'uploads/' . $storedName);
    }

    public function testSaveDraftPersistsAttachments(): void
    {
        $this->db->table('recipients')->insert([
            'id' => 1, 'name' => 'Jane', 'email' => 'jane@example.com', 'status' => 'active',
            'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $file = WRITEPATH . 'uploads/test_attach_' . uniqid() . '.txt';
        file_put_contents($file, 'hello');
        $fileSize = filesize($file);

        // Inject $_FILES data directly using the framework's superglobals service
        // getFileMultiple expects an array-indexed field (name, type, tmp_name, error, size all arrays)
        service('superglobals')->setFilesArray([
            'attachments' => [
                'name'     => ['note.txt'],
                'type'     => ['text/plain'],
                'tmp_name' => [$file],
                'error'    => [UPLOAD_ERR_OK],
                'size'     => [$fileSize],
            ],
        ]);

        $result = $this->loggedIn()->post('/compose/draft', [
            'recipient_id' => 1, 'subject' => 'Draft subject', 'body_html' => '<p>Draft</p>',
        ]);

        $result->assertOK();
        $emailId = (int) $this->db->table('emails')->where('recipient_id', 1)->get()->getRow()->id;
        $this->seeInDatabase('email_attachments', ['email_id' => $emailId, 'original_filename' => 'note.txt']);

        $storedName = $this->db->table('email_attachments')->where('email_id', $emailId)->get()->getRow()->stored_filename;
        $this->assertFileExists(WRITEPATH . 'uploads/' . $storedName);

        @unlink(WRITEPATH . 'uploads/' . $storedName);
    }

    public function testEditLoadsDraftIntoComposeForm(): void
    {
        $this->db->table('users')->insert([
            'id' => 1, 'name' => 'Admin', 'email' => 'admin@test.com',
            'password_hash' => password_hash('x', PASSWORD_DEFAULT), 'role' => 'owner', 'status' => 'active',
            'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $this->db->table('recipients')->insert([
            'id' => 1, 'name' => 'Jane', 'email' => 'jane@example.com', 'status' => 'active',
            'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $this->db->table('emails')->insert([
            'id' => 1, 'recipient_id' => 1, 'user_id' => 1, 'subject' => 'My draft', 'body_html' => '<p>Hi</p>',
            'status' => 'draft', 'attempt_count' => 0, 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $result = $this->withSession(['isLoggedIn' => true, 'user_id' => 1, 'user_role' => 'owner', 'user_name' => 'Admin'])->get('/compose/edit/1');

        $result->assertOK();
        $result->assertSee('My draft');
    }

    public function testEditOnNonDraftRedirectsWithError(): void
    {
        $this->db->table('users')->insert([
            'id' => 1, 'name' => 'Admin', 'email' => 'admin@test.com',
            'password_hash' => password_hash('x', PASSWORD_DEFAULT), 'role' => 'owner', 'status' => 'active',
            'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $this->db->table('recipients')->insert([
            'id' => 1, 'name' => 'Jane', 'email' => 'jane@example.com', 'status' => 'active',
            'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $this->db->table('emails')->insert([
            'id' => 1, 'recipient_id' => 1, 'user_id' => 1, 'subject' => 'Sent one', 'body_html' => '<p>Hi</p>',
            'status' => 'sent', 'attempt_count' => 1, 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $this->withSession(['isLoggedIn' => true, 'user_id' => 1, 'user_role' => 'owner', 'user_name' => 'Admin'])->get('/compose/edit/1')->assertRedirectTo('/emails/drafts');
    }

    public function testUpdateSavesChangesToExistingDraft(): void
    {
        $this->db->table('recipients')->insert([
            'id' => 1, 'name' => 'Jane', 'email' => 'jane@example.com', 'status' => 'active',
            'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $this->db->table('users')->insert([
            'id' => 1, 'name' => 'Admin', 'email' => 'admin@test.com',
            'password_hash' => password_hash('x', PASSWORD_DEFAULT), 'role' => 'owner', 'status' => 'active',
            'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $this->db->table('emails')->insert([
            'id' => 1, 'recipient_id' => 1, 'user_id' => 1, 'subject' => 'Old subject', 'body_html' => '<p>Old</p>',
            'status' => 'draft', 'attempt_count' => 0, 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $result = $this->withSession(['isLoggedIn' => true, 'user_id' => 1, 'user_role' => 'owner', 'user_name' => 'Admin'])->post('/compose/update/1', [
            'recipient_id' => 1, 'subject' => 'New subject', 'body_html' => '<p>New</p>',
        ]);

        $result->assertOK();
        $this->seeInDatabase('emails', ['id' => 1, 'subject' => 'New subject', 'status' => 'draft']);
        $this->assertSame(1, $this->db->table('emails')->countAllResults());
    }

    public function testUpdateRemovesSelectedAttachmentAndKeepsOthers(): void
    {
        $this->db->table('recipients')->insert([
            'id' => 1, 'name' => 'Jane', 'email' => 'jane@example.com', 'status' => 'active',
            'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $this->db->table('users')->insert([
            'id' => 1, 'name' => 'Admin', 'email' => 'admin@test.com',
            'password_hash' => password_hash('x', PASSWORD_DEFAULT), 'role' => 'owner', 'status' => 'active',
            'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $this->db->table('emails')->insert([
            'id' => 1, 'recipient_id' => 1, 'user_id' => 1, 'subject' => 'Subject', 'body_html' => '<p>Hi</p>',
            'status' => 'draft', 'attempt_count' => 0, 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $keepPath = WRITEPATH . 'uploads/keep_' . uniqid() . '.txt';
        $removePath = WRITEPATH . 'uploads/remove_' . uniqid() . '.txt';
        file_put_contents($keepPath, 'x');
        file_put_contents($removePath, 'x');
        $this->db->table('email_attachments')->insert([
            'email_id' => 1, 'original_filename' => 'keep.txt', 'stored_filename' => basename($keepPath),
            'mime_type' => 'text/plain', 'size_bytes' => 1, 'created_at' => date('Y-m-d H:i:s'),
        ]);
        $this->db->table('email_attachments')->insert([
            'email_id' => 1, 'original_filename' => 'remove.txt', 'stored_filename' => basename($removePath),
            'mime_type' => 'text/plain', 'size_bytes' => 1, 'created_at' => date('Y-m-d H:i:s'),
        ]);
        $removeId = (int) $this->db->table('email_attachments')->where('stored_filename', basename($removePath))->get()->getRow()->id;

        $result = $this->withSession(['isLoggedIn' => true, 'user_id' => 1, 'user_role' => 'owner', 'user_name' => 'Admin'])->post('/compose/update/1', [
            'recipient_id' => 1, 'subject' => 'Subject', 'body_html' => '<p>Hi</p>',
            'remove_attachments' => [$removeId],
        ]);

        $result->assertOK();
        $this->seeInDatabase('email_attachments', ['email_id' => 1, 'original_filename' => 'keep.txt']);
        $this->dontSeeInDatabase('email_attachments', ['id' => $removeId]);
        $this->assertFileDoesNotExist($removePath);
        $this->assertFileExists($keepPath);

        @unlink($keepPath);
    }

    public function testUpdateOnNonDraftFails(): void
    {
        $this->db->table('recipients')->insert([
            'id' => 1, 'name' => 'Jane', 'email' => 'jane@example.com', 'status' => 'active',
            'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $this->db->table('users')->insert([
            'id' => 1, 'name' => 'Admin', 'email' => 'admin@test.com',
            'password_hash' => password_hash('x', PASSWORD_DEFAULT), 'role' => 'owner', 'status' => 'active',
            'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $this->db->table('emails')->insert([
            'id' => 1, 'recipient_id' => 1, 'user_id' => 1, 'subject' => 'Sent', 'body_html' => '<p>Hi</p>',
            'status' => 'sent', 'attempt_count' => 1, 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $result = $this->withSession(['isLoggedIn' => true, 'user_id' => 1, 'user_role' => 'owner', 'user_name' => 'Admin'])->post('/compose/update/1', [
            'recipient_id' => 1, 'subject' => 'Changed', 'body_html' => '<p>Changed</p>',
        ]);

        $result->assertOK();
        $this->seeInDatabase('emails', ['id' => 1, 'subject' => 'Sent']);
    }

    public function testBulkStartCreatesBatchAndReturnsId(): void
    {
        $result = $this->loggedIn()->post('/compose/bulk/start', [
            'subject' => 'Hi {{name}}', 'body_html' => '<p>Hi {{name}}</p>',
        ]);

        $result->assertOK();
        $body = json_decode($result->getJSON(), true);
        $this->assertTrue($body['success']);
        $this->assertGreaterThan(0, $body['batch_id']);
        $this->seeInDatabase('email_batches', ['id' => $body['batch_id'], 'subject' => 'Hi {{name}}']);
    }

    public function testBulkStartPersistsAttachmentsOnceToBatchStaging(): void
    {
        $file = WRITEPATH . 'uploads/bulk_test_' . uniqid() . '.txt';
        file_put_contents($file, 'hello');

        service('superglobals')->setFilesArray([
            'attachments' => [
                'name'     => ['flyer.txt'],
                'type'     => ['text/plain'],
                'tmp_name' => [$file],
                'error'    => [UPLOAD_ERR_OK],
                'size'     => [filesize($file)],
            ],
        ]);

        $result = $this->loggedIn()->post('/compose/bulk/start', [
            'subject' => 'Hi', 'body_html' => '<p>Hi</p>',
        ]);

        $body = json_decode($result->getJSON(), true);
        $this->seeInDatabase('email_batch_attachments', ['batch_id' => $body['batch_id'], 'original_filename' => 'flyer.txt']);
    }

    public function testBulkSendOneCreatesEmailRowForThatRecipient(): void
    {
        $this->db->table('recipients')->insert([
            'id' => 1, 'name' => 'Jane', 'email' => 'jane@example.com', 'status' => 'active',
            'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $start = $this->loggedIn()->post('/compose/bulk/start', ['subject' => 'Hi {{name}}', 'body_html' => '<p>Hi {{name}}</p>']);
        $batchId = json_decode($start->getJSON(), true)['batch_id'];

        $result = $this->loggedIn()->post('/compose/bulk/send-one', ['batch_id' => $batchId, 'recipient_id' => 1]);

        $result->assertOK();
        $this->seeInDatabase('emails', ['recipient_id' => 1, 'batch_id' => $batchId]);
    }

    public function testBulkSendOneWithInactiveRecipientFailsGracefully(): void
    {
        $this->db->table('recipients')->insert([
            'id' => 1, 'name' => 'Jane', 'email' => 'jane@example.com', 'status' => 'unsubscribed',
            'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $start = $this->loggedIn()->post('/compose/bulk/start', ['subject' => 'Hi', 'body_html' => '<p>Hi</p>']);
        $batchId = json_decode($start->getJSON(), true)['batch_id'];

        $result = $this->loggedIn()->post('/compose/bulk/send-one', ['batch_id' => $batchId, 'recipient_id' => 1]);

        $result->assertOK();
        $body = json_decode($result->getJSON(), true);
        $this->assertFalse($body['success']);
    }

    public function testBulkSendOneCopiesBatchAttachmentsOntoEachEmail(): void
    {
        $this->db->table('recipients')->insert([
            'id' => 1, 'name' => 'Jane', 'email' => 'jane@example.com', 'status' => 'active',
            'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $file = WRITEPATH . 'uploads/bulk_test_' . uniqid() . '.txt';
        file_put_contents($file, 'hello');

        service('superglobals')->setFilesArray([
            'attachments' => [
                'name'     => ['flyer.txt'],
                'type'     => ['text/plain'],
                'tmp_name' => [$file],
                'error'    => [UPLOAD_ERR_OK],
                'size'     => [filesize($file)],
            ],
        ]);

        $start = $this->loggedIn()->post('/compose/bulk/start', ['subject' => 'Hi', 'body_html' => '<p>Hi</p>']);
        $batchId = json_decode($start->getJSON(), true)['batch_id'];

        service('superglobals')->setFilesArray([]);

        $this->loggedIn()->post('/compose/bulk/send-one', ['batch_id' => $batchId, 'recipient_id' => 1]);

        $emailId = (int) $this->db->table('emails')->where('recipient_id', 1)->get()->getRow()->id;
        $this->seeInDatabase('email_attachments', ['email_id' => $emailId, 'original_filename' => 'flyer.txt']);
    }

    /**
     * Regression test for the bug where bulkSendOne() always passed [] as
     * EmailSenderService::send()'s attachment list, so the batch's staged
     * file was recorded in email_attachments (via copyBatchAttachments, which
     * is all the older testBulkSendOneCopiesBatchAttachmentsOntoEachEmail
     * above checks) but never actually attached to the outgoing message.
     *
     * EmailSenderService builds its Email instance via
     * Config\Services::email(null, false) -- the `false` (not shared) means
     * it always bypasses CI4's test mock-injection registry, so send()'s
     * arguments can't be spied on through the framework's normal mocking.
     * Proving the fix over the wire would need a real SMTP responder, but
     * smtp_settings.encryption is a DB-level ENUM('tls','ssl') with no
     * plaintext option, so that responder would have to speak real TLS --
     * disproportionate machinery for this one regression check.
     *
     * Instead this calls ComposeController::batchAttachmentPaths() directly
     * (via reflection, since it's private) -- the exact helper bulkSendOne()
     * now passes to send() -- and asserts it resolves the batch's staged
     * file to the same absolute path AttachmentService::copyBatchAttachments()
     * already proves (in the test above) gets copied onto the sent email.
     */
    public function testBulkSendOneResolvesBatchAttachmentsToDiskPaths(): void
    {
        $file = WRITEPATH . 'uploads/bulk_path_test_' . uniqid() . '.txt';
        file_put_contents($file, 'hello');

        service('superglobals')->setFilesArray([
            'attachments' => [
                'name'     => ['flyer.txt'],
                'type'     => ['text/plain'],
                'tmp_name' => [$file],
                'error'    => [UPLOAD_ERR_OK],
                'size'     => [filesize($file)],
            ],
        ]);

        $start = $this->loggedIn()->post('/compose/bulk/start', ['subject' => 'Hi', 'body_html' => '<p>Hi</p>']);
        $batchId = json_decode($start->getJSON(), true)['batch_id'];

        $storedFilename = $this->db->table('email_batch_attachments')->where('batch_id', $batchId)->get()->getRow()->stored_filename;

        $method = new \ReflectionMethod(\App\Controllers\ComposeController::class, 'batchAttachmentPaths');
        $method->setAccessible(true);
        $paths = $method->invoke(new \App\Controllers\ComposeController(), $batchId);

        $this->assertSame([WRITEPATH . 'uploads/' . $storedFilename], $paths);

        @unlink($file);
    }

    /**
     * The reflection test above proves batchAttachmentPaths() resolves
     * correctly in isolation, but that alone wouldn't catch a regression back
     * to bulkSendOne() passing [] to send() while leaving the (now-unused)
     * helper method orphaned. Pin the actual wiring: bulkSendOne() must call
     * it and pass the result to send(), not a literal [].
     */
    public function testBulkSendOnePassesResolvedPathsToSend(): void
    {
        $method = new \ReflectionMethod(\App\Controllers\ComposeController::class, 'bulkSendOne');
        $lines = file($method->getFileName());
        $body = implode('', array_slice($lines, $method->getStartLine() - 1, $method->getEndLine() - $method->getStartLine() + 1));

        $this->assertStringContainsString('$this->batchAttachmentPaths($batchId)', $body);
    }
}
