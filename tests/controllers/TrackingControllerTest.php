<?php

namespace Tests\Controllers;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

final class TrackingControllerTest extends CIUnitTestCase
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
        $this->db->table('emails')->insert([
            'id' => 1, 'recipient_id' => 1, 'user_id' => 1, 'subject' => 'Hi', 'body_html' => '<p>Hi</p>',
            'status' => 'sent', 'tracking_token' => str_repeat('a', 40),
            'created_at' => $now, 'updated_at' => $now,
        ]);
    }

    public function testOpenLogsAnEventAndReturnsAGif(): void
    {
        $result = $this->get('/t/o/' . str_repeat('a', 40) . '.gif');

        $result->assertOK();
        $this->assertSame('image/gif', $result->response()->getHeaderLine('Content-Type'));
        $this->seeInDatabase('email_events', ['email_id' => 1, 'type' => 'open']);
    }

    public function testOpenWithUnknownTokenStillReturnsAGifButLogsNothing(): void
    {
        $result = $this->get('/t/o/' . str_repeat('f', 40) . '.gif');

        $result->assertOK();
        $this->assertSame('image/gif', $result->response()->getHeaderLine('Content-Type'));
        $this->assertSame(0, $this->db->table('email_events')->countAllResults());
    }

    public function testClickLogsAnEventAndRedirectsToTheRealUrl(): void
    {
        $result = $this->get('/t/c/' . str_repeat('a', 40) . '?u=' . urlencode('https://example.com/offer'));

        $result->assertRedirectTo('https://example.com/offer');
        $this->seeInDatabase('email_events', ['email_id' => 1, 'type' => 'click', 'url' => 'https://example.com/offer']);
    }

    public function testClickRejectsANonHttpUrlAndFallsBackHome(): void
    {
        $result = $this->get('/t/c/' . str_repeat('a', 40) . '?u=' . urlencode('javascript:alert(1)'));

        $result->assertRedirect();
        $location = $result->response()->getHeaderLine('Location');
        $this->assertStringNotContainsString('javascript:', $location);
    }

    public function testClickWithUnknownTokenStillRedirectsSafely(): void
    {
        $result = $this->get('/t/c/' . str_repeat('f', 40) . '?u=' . urlencode('https://example.com/offer'));

        $result->assertRedirectTo('https://example.com/offer');
        $this->assertSame(0, $this->db->table('email_events')->countAllResults());
    }
}
