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
}
