<?php

namespace Tests\Services;

use App\Services\EmailSenderService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

final class EmailSenderServiceTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $refresh = true;
    protected $namespace = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->db->table('users')->insert([
            'id' => 1, 'name' => 'Admin', 'email' => 'admin@test.com',
            'password_hash' => password_hash('x', PASSWORD_DEFAULT), 'role' => 'owner', 'status' => 'active',
            'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $this->db->table('recipients')->insert([
            'id' => 1, 'name' => 'Jane', 'email' => 'jane@example.com', 'status' => 'active',
            'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function testMissingSmtpConfigRecordsFailedEmail(): void
    {
        $result = (new EmailSenderService())->send(1, 'Hello', '<p>Hi</p>', null, 1);

        $this->assertSame('failed', $result['status']);
        $this->seeInDatabase('emails', ['recipient_id' => 1, 'status' => 'failed']);
    }

    public function testUnknownRecipientRecordsNothingAndReturnsError(): void
    {
        $result = (new EmailSenderService())->send(999, 'Hello', '<p>Hi</p>', null, 1);

        $this->assertSame('failed', $result['status']);
        $this->assertSame('Recipient not found.', $result['error']);
        $this->assertSame(0, $this->db->table('emails')->countAllResults());
    }

    public function testUnsubscribedRecipientIsRejected(): void
    {
        $this->db->table('recipients')->where('id', 1)->update(['status' => 'unsubscribed']);

        $result = (new EmailSenderService())->send(1, 'Hello', '<p>Hi</p>', null, 1);

        $this->assertSame('failed', $result['status']);
        $this->assertSame('Recipient has unsubscribed.', $result['error']);
    }
}
