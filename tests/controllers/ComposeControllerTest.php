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
        $this->db->table('users')->insert([
            'id' => 1, 'name' => 'Admin', 'email' => 'admin@test.com',
            'password_hash' => password_hash('x', PASSWORD_DEFAULT), 'role' => 'owner', 'status' => 'active',
            'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);
        return $this->withSession(['isLoggedIn' => true, 'user_id' => 1, 'user_role' => 'owner', 'user_name' => 'Admin']);
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
}
