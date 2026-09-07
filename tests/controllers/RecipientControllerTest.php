<?php

namespace Tests\Controllers;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

final class RecipientControllerTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $refresh = true;
    protected $namespace = null;

    private function loggedIn(): self
    {
        $this->db->table('users')->insert([
            'id' => 1, 'name' => 'Admin', 'email' => 'admin@test.com',
            'password_hash' => password_hash('x', PASSWORD_DEFAULT),
            'role' => 'owner', 'status' => 'active',
            'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);
        return $this->withSession(['isLoggedIn' => true, 'user_id' => 1, 'user_role' => 'owner', 'user_name' => 'Admin']);
    }

    public function testCreateRecipient(): void
    {
        $result = $this->loggedIn()->post('/recipients/create', [
            'name' => 'Jane Doe', 'email' => 'jane@example.com', 'company' => 'Acme',
        ]);

        $result->assertRedirect();
        $this->seeInDatabase('recipients', ['email' => 'jane@example.com']);
    }

    public function testCreateRecipientStoresLocation(): void
    {
        $result = $this->loggedIn()->post('/recipients/create', [
            'name' => 'Jane Doe', 'email' => 'jane@example.com', 'location' => 'New York',
        ]);

        $result->assertRedirect();
        $this->seeInDatabase('recipients', ['email' => 'jane@example.com', 'location' => 'New York']);
    }

    public function testDuplicateEmailRejected(): void
    {
        $this->db->table('recipients')->insert([
            'name' => 'Jane', 'email' => 'jane@example.com', 'status' => 'active',
            'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $result = $this->loggedIn()->post('/recipients/create', [
            'name' => 'Jane Two', 'email' => 'jane@example.com',
        ]);

        $result->assertOK();
        $this->assertSame(1, $this->db->table('recipients')->where('email', 'jane@example.com')->countAllResults());
    }

    public function testInvalidEmailRejected(): void
    {
        $result = $this->loggedIn()->post('/recipients/create', [
            'name' => 'Bad Email', 'email' => 'not-an-email',
        ]);

        $result->assertOK();
        $this->dontSeeInDatabase('recipients', ['name' => 'Bad Email']);
    }

    public function testUpdateRecipient(): void
    {
        $this->db->table('recipients')->insert([
            'id' => 1, 'name' => 'Jane', 'email' => 'jane@example.com', 'status' => 'active',
            'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $result = $this->loggedIn()->post('/recipients/edit/1', [
            'name' => 'Jane Updated', 'email' => 'jane@example.com',
        ]);

        $result->assertRedirect();
        $this->seeInDatabase('recipients', ['id' => 1, 'name' => 'Jane Updated']);
    }

    public function testUpdateRecipientViaAjaxReturnsJson(): void
    {
        $this->db->table('recipients')->insert([
            'id' => 1, 'name' => 'Jane', 'email' => 'jane@example.com', 'status' => 'active',
            'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $result = $this->loggedIn()->withHeaders(['Accept' => 'application/json'])->post('/recipients/edit/1', [
            'name' => 'Jane Ajax Updated', 'email' => 'jane@example.com', 'location' => 'Boston',
        ]);

        $result->assertOK();
        $this->assertSame(['success' => true], json_decode($result->getJSON(), true));
        $this->seeInDatabase('recipients', ['id' => 1, 'name' => 'Jane Ajax Updated', 'location' => 'Boston']);
    }

    public function testUpdateRecipientViaAjaxReturnsFieldErrorsOnInvalidEmail(): void
    {
        $this->db->table('recipients')->insert([
            'id' => 1, 'name' => 'Jane', 'email' => 'jane@example.com', 'status' => 'active',
            'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $result = $this->loggedIn()->withHeaders(['Accept' => 'application/json'])->post('/recipients/edit/1', [
            'name' => 'Jane', 'email' => 'not-an-email',
        ]);

        $result->assertStatus(422);
        $body = json_decode($result->getJSON(), true);
        $this->assertFalse($body['success']);
        $this->assertArrayHasKey('email', $body['errors']);
    }

    public function testDeleteRecipient(): void
    {
        $this->db->table('recipients')->insert([
            'id' => 1, 'name' => 'Jane', 'email' => 'jane@example.com', 'status' => 'active',
            'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $result = $this->loggedIn()->post('/recipients/delete/1');

        $result->assertRedirect();
        $this->dontSeeInDatabase('recipients', ['id' => 1]);
    }

    public function testBulkDeleteRemovesSelectedRecipients(): void
    {
        $this->db->table('recipients')->insert([
            'id' => 1, 'name' => 'Jane', 'email' => 'jane@example.com', 'status' => 'active',
            'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $this->db->table('recipients')->insert([
            'id' => 2, 'name' => 'John', 'email' => 'john@example.com', 'status' => 'active',
            'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $this->db->table('recipients')->insert([
            'id' => 3, 'name' => 'Keep', 'email' => 'keep@example.com', 'status' => 'active',
            'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $result = $this->loggedIn()->post('/recipients/bulk-delete', ['ids' => [1, 2]]);

        $result->assertRedirect();
        $this->dontSeeInDatabase('recipients', ['id' => 1]);
        $this->dontSeeInDatabase('recipients', ['id' => 2]);
        $this->seeInDatabase('recipients', ['id' => 3]);
    }

    public function testCampaignFilterOnlyShowsUnsentRecipients(): void
    {
        $session = $this->loggedIn();
        $this->db->table('recipients')->insert([
            'id' => 1, 'name' => 'Already Emailed', 'email' => 'sent@example.com', 'status' => 'active',
            'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $this->db->table('recipients')->insert([
            'id' => 2, 'name' => 'Never Emailed', 'email' => 'notsent@example.com', 'status' => 'active',
            'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $this->db->table('email_templates')->insert([
            'id' => 1, 'name' => 'Welcome', 'subject' => 'Hi', 'html_body' => '<p>Hi</p>', 'status' => 'active',
            'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $this->db->table('emails')->insert([
            'recipient_id' => 1, 'template_id' => 1, 'user_id' => 1, 'subject' => 'Hi', 'body_html' => '<p>Hi</p>',
            'status' => 'sent', 'sent_at' => date('Y-m-d H:i:s'),
            'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $result = $session->get('/recipients?template_id=1&sent_status=unsent');

        $result->assertOK();
        $result->assertSee('Never Emailed');
        $result->assertDontSee('Already Emailed');
    }

    public function testExportEscapesFormulaLikeFields(): void
    {
        $this->db->table('recipients')->insert([
            'name' => '=cmd|\'/c calc\'!A1', 'email' => 'jane@example.com', 'company' => '+SUM(1+1)', 'status' => 'active',
            'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $result = $this->loggedIn()->get('/recipients/export');

        $body = $result->response()->getBody();
        $this->assertStringNotContainsString("\n=cmd", $body);
        $this->assertStringContainsString("'=cmd", $body);
        $this->assertStringContainsString("'+SUM", $body);
    }
}
