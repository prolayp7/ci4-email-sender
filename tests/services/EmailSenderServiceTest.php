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

    public function testBouncedRecipientIsRejected(): void
    {
        $this->db->table('recipients')->where('id', 1)->update(['status' => 'bounced']);

        $result = (new EmailSenderService())->send(1, 'Hello', '<p>Hi</p>', null, 1);

        $this->assertSame('failed', $result['status']);
        $this->assertSame('Recipient email previously bounced.', $result['error']);
    }

    public function testSuppressedRecipientIsRejected(): void
    {
        $this->db->table('recipients')->where('id', 1)->update(['status' => 'suppressed']);

        $result = (new EmailSenderService())->send(1, 'Hello', '<p>Hi</p>', null, 1);

        $this->assertSame('failed', $result['status']);
        $this->assertSame('Recipient is suppressed.', $result['error']);
    }

    // Note: auto-bounce-marking on a permanent RCPT rejection (see
    // EmailSenderService::send()) isn't covered by an end-to-end test here --
    // that wiring is 4 lines of glue code onto BounceClassifier, which is
    // fully covered by BounceClassifierTest. A real integration test would
    // need a TLS-capable fake SMTP server (CI4's Email always does
    // STARTTLS/implicit-SSL, with no way to disable peer verification),
    // which means generating a trusted cert and mutating PHP's default SSL
    // stream context for the process -- disproportionate for this much code.
}
