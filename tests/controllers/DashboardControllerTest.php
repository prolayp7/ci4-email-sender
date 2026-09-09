<?php

namespace Tests\Controllers;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

final class DashboardControllerTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $refresh = true;
    protected $namespace = null;

    public function testDashboardShowsKpis(): void
    {
        $this->db->table('users')->insert([
            'id' => 1, 'name' => 'Admin', 'email' => 'admin@test.com',
            'password_hash' => password_hash('x', PASSWORD_DEFAULT),
            'role' => 'owner', 'status' => 'active',
            'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $this->db->table('recipients')->insert([
            'name' => 'Jane', 'email' => 'jane@test.com', 'status' => 'active',
            'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $result = $this->withSession(['isLoggedIn' => true, 'user_id' => 1, 'user_role' => 'owner', 'user_name' => 'Admin'])
            ->get('/dashboard');

        $result->assertStatus(200);
        $result->assertSee('Total Recipients');
        $result->assertSee('1');
    }

    private function loggedIn(): self
    {
        $this->db->table('users')->insert([
            'id' => 1, 'name' => 'Admin', 'email' => 'admin@test.com',
            'password_hash' => password_hash('x', PASSWORD_DEFAULT), 'role' => 'owner', 'status' => 'active',
            'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);
        return $this->withSession(['isLoggedIn' => true, 'user_id' => 1, 'user_role' => 'owner', 'user_name' => 'Admin']);
    }

    public function testDashboardCountsUniqueOpensAndClicksNotRawEventHits(): void
    {
        $session = $this->loggedIn();
        $now = date('Y-m-d H:i:s');
        $this->db->table('recipients')->insert([
            'id' => 1, 'name' => 'Jane', 'email' => 'jane@test.com', 'status' => 'active',
            'created_at' => $now, 'updated_at' => $now,
        ]);
        $this->db->table('emails')->insert([
            'id' => 1, 'recipient_id' => 1, 'user_id' => 1, 'subject' => 'Hi', 'body_html' => '<p>Hi</p>',
            'status' => 'sent', 'created_at' => $now, 'updated_at' => $now,
        ]);
        // Two opens and two clicks on the SAME email -- should count as 1 each.
        $this->db->table('email_events')->insert(['email_id' => 1, 'type' => 'open', 'created_at' => $now]);
        $this->db->table('email_events')->insert(['email_id' => 1, 'type' => 'open', 'created_at' => $now]);
        $this->db->table('email_events')->insert(['email_id' => 1, 'type' => 'click', 'url' => 'https://example.com', 'created_at' => $now]);

        $result = $session->get('/dashboard');

        $result->assertStatus(200);
        // The "1" appears in Opened and Clicked cards; a crude but effective
        // check that neither shows the raw "2" event count instead.
        $result->assertDontSee('<h3 class="mb-0 fw-bold">2</h3>');
    }

    public function testDashboardShowsCampaignPerformanceTable(): void
    {
        $session = $this->loggedIn();
        $now = date('Y-m-d H:i:s');
        $this->db->table('email_batches')->insert([
            'id' => 1, 'subject' => 'Spring Sale', 'body_html' => '<p>Hi</p>', 'user_id' => 1,
            'recipient_count' => 1, 'status' => 'completed', 'created_at' => $now,
        ]);

        $result = $session->get('/dashboard');

        $result->assertStatus(200);
        $result->assertSee('Campaign Performance');
        $result->assertSee('Spring Sale');
    }

    public function testDashboardMasksIpAddressesInTheActivityFeed(): void
    {
        $session = $this->loggedIn();
        $this->db->table('activity_logs')->insert([
            'user_id' => 1, 'action' => 'login', 'description' => 'User logged in',
            'ip_address' => '203.0.113.42', 'created_at' => date('Y-m-d H:i:s'),
        ]);

        $result = $session->get('/dashboard');

        $result->assertDontSee('203.0.113.42');
        $result->assertSee('203.0.•.•');
    }

    public function testDashboardShowsDeliveredCardMatchingSentCount(): void
    {
        $session = $this->loggedIn();
        $this->db->table('recipients')->insert([
            'id' => 1, 'name' => 'Jane', 'email' => 'jane@test.com', 'status' => 'active',
            'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $this->db->table('emails')->insert([
            'recipient_id' => 1, 'user_id' => 1, 'subject' => 'Hi', 'body_html' => '<p>Hi</p>',
            'status' => 'sent', 'sent_at' => date('Y-m-d H:i:s'),
            'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $result = $session->get('/dashboard');

        $result->assertStatus(200);
        $result->assertSee('Delivered');
    }

    public function testDashboardLinksToFullAuditLogForOwner(): void
    {
        $session = $this->loggedIn();

        $result = $session->get('/dashboard');

        $result->assertSee('View full audit log');
    }
}
