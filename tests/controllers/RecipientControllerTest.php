<?php

namespace Tests\Controllers;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

require_once __DIR__ . '/../_support/Files/uploaded_file_test_overrides.php';

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

    protected function tearDown(): void
    {
        parent::tearDown();
        service('superglobals')->setFilesArray([]);
    }

    private function uploadCsv(string $content): string
    {
        $path = WRITEPATH . 'uploads/test_upload_' . uniqid() . '.csv';
        file_put_contents($path, $content);

        service('superglobals')->setFilesArray([
            'csv' => [
                'name' => 'recipients.csv', 'type' => 'text/csv', 'tmp_name' => $path,
                'error' => UPLOAD_ERR_OK, 'size' => filesize($path),
            ],
        ]);

        return $path;
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

    public function testImportUploadReturnsHeadersAndSuggestedMapping(): void
    {
        $this->uploadCsv("Name,Email,Company\nJane,jane@example.com,Acme\n");

        $result = $this->loggedIn()->post('/recipients/import/upload');

        $body = json_decode($result->getJSON(), true);
        $this->assertTrue($body['success']);
        $this->assertNotEmpty($body['token']);
        $this->assertSame(['Name', 'Email', 'Company'], $body['headers']);
        $this->assertSame(0, $body['suggestedMapping']['name']);
        $this->assertSame(1, $body['suggestedMapping']['email']);
    }

    public function testImportUploadRejectsNonCsvFile(): void
    {
        $path = WRITEPATH . 'uploads/test_upload_' . uniqid() . '.txt';
        file_put_contents($path, 'not a csv');
        service('superglobals')->setFilesArray([
            'csv' => ['name' => 'file.txt', 'type' => 'text/plain', 'tmp_name' => $path, 'error' => UPLOAD_ERR_OK, 'size' => filesize($path)],
        ]);

        $result = $this->loggedIn()->post('/recipients/import/upload');

        $body = json_decode($result->getJSON(), true);
        $this->assertFalse($body['success']);
        @unlink($path);
    }

    public function testImportValidateReturnsCountsWithoutWriting(): void
    {
        $session = $this->loggedIn();
        $this->uploadCsv("Name,Email\nJane,jane@example.com\nBad,not-an-email\n");
        $upload = json_decode($session->post('/recipients/import/upload')->getJSON(), true);

        $result = $session->post('/recipients/import/validate', [
            'token' => $upload['token'], 'map_name' => 0, 'map_email' => 1,
        ]);

        $body = json_decode($result->getJSON(), true);
        $this->assertTrue($body['success']);
        $this->assertSame(1, $body['summary']['imported']);
        $this->assertSame(1, $body['summary']['invalid']);
        $this->dontSeeInDatabase('recipients', ['email' => 'jane@example.com']);
    }

    public function testImportCommitWritesRowsAndCleansUpTempFile(): void
    {
        $session = $this->loggedIn();
        $path = $this->uploadCsv("Name,Email\nJane,jane@example.com\n");
        $upload = json_decode($session->post('/recipients/import/upload')->getJSON(), true);
        $storedPath = WRITEPATH . 'uploads/' . $upload['token'];

        $result = $session->post('/recipients/import/commit', [
            'token' => $upload['token'], 'map_name' => 0, 'map_email' => 1, 'duplicate_mode' => 'skip',
        ]);

        $body = json_decode($result->getJSON(), true);
        $this->assertTrue($body['success']);
        $this->assertSame(1, $body['summary']['imported']);
        $this->seeInDatabase('recipients', ['email' => 'jane@example.com']);
        $this->assertFileDoesNotExist($storedPath);
        @unlink($path);
    }

    public function testImportCommitWithUpdateModeOverwritesExisting(): void
    {
        $session = $this->loggedIn();
        $this->db->table('recipients')->insert([
            'name' => 'Old Name', 'email' => 'jane@example.com', 'status' => 'active',
            'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $this->uploadCsv("Name,Email\nJane New,jane@example.com\n");
        $upload = json_decode($session->post('/recipients/import/upload')->getJSON(), true);

        $result = $session->post('/recipients/import/commit', [
            'token' => $upload['token'], 'map_name' => 0, 'map_email' => 1, 'duplicate_mode' => 'update',
        ]);

        $body = json_decode($result->getJSON(), true);
        $this->assertSame(1, $body['summary']['updated']);
        $this->seeInDatabase('recipients', ['email' => 'jane@example.com', 'name' => 'Jane New']);
    }

    public function testImportValidateRejectsAnUnknownToken(): void
    {
        $result = $this->loggedIn()->post('/recipients/import/validate', [
            'token' => '../../etc/passwd', 'map_email' => 0,
        ]);

        $body = json_decode($result->getJSON(), true);
        $this->assertFalse($body['success']);
    }

    public function testCreateRecipientPersistsTags(): void
    {
        $this->loggedIn()->post('/recipients/create', [
            'name' => 'Jane', 'email' => 'jane@example.com', 'tags' => 'WordPress, Hot Lead',
        ]);

        $recipient = $this->db->table('recipients')->where('email', 'jane@example.com')->get()->getRowArray();
        $this->assertSame(['Hot Lead', 'WordPress'], (new \App\Services\TagService())->namesForRecipient((int) $recipient['id']));
    }

    public function testEditRecipientUpdatesTags(): void
    {
        $this->db->table('recipients')->insert([
            'id' => 1, 'name' => 'Jane', 'email' => 'jane@example.com', 'status' => 'active',
            'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);
        (new \App\Services\TagService())->syncForRecipient(1, 'WordPress');

        $this->loggedIn()->post('/recipients/edit/1', [
            'name' => 'Jane', 'email' => 'jane@example.com', 'tags' => 'Developer',
        ]);

        $this->assertSame(['Developer'], (new \App\Services\TagService())->namesForRecipient(1));
    }

    public function testFilterByTagOnlyShowsTaggedRecipients(): void
    {
        $this->db->table('recipients')->insert([
            'id' => 1, 'name' => 'Tagged Jane', 'email' => 'jane@example.com', 'status' => 'active',
            'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $this->db->table('recipients')->insert([
            'id' => 2, 'name' => 'Untagged John', 'email' => 'john@example.com', 'status' => 'active',
            'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);
        (new \App\Services\TagService())->syncForRecipient(1, 'Hot Lead');
        $tagId = $this->db->table('tags')->where('name', 'Hot Lead')->get()->getRow()->id;

        $result = $this->loggedIn()->get('/recipients?tag_id=' . $tagId);

        $result->assertSee('Tagged Jane');
        $result->assertDontSee('Untagged John');
    }

    public function testFilterByLocationOnlyShowsMatchingRecipients(): void
    {
        $this->db->table('recipients')->insert([
            'id' => 1, 'name' => 'Boston Jane', 'email' => 'jane@example.com', 'location' => 'Boston', 'status' => 'active',
            'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $this->db->table('recipients')->insert([
            'id' => 2, 'name' => 'Seattle John', 'email' => 'john@example.com', 'location' => 'Seattle', 'status' => 'active',
            'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $result = $this->loggedIn()->get('/recipients?location=Boston');

        $result->assertSee('Boston Jane');
        $result->assertDontSee('Seattle John');
    }

    public function testFilterByNeverContactedExcludesRecipientsWithASentEmail(): void
    {
        $session = $this->loggedIn();
        $this->db->table('recipients')->insert([
            'id' => 1, 'name' => 'Contacted Jane', 'email' => 'jane@example.com', 'status' => 'active',
            'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $this->db->table('recipients')->insert([
            'id' => 2, 'name' => 'Never John', 'email' => 'john@example.com', 'status' => 'active',
            'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $this->db->table('emails')->insert([
            'recipient_id' => 1, 'user_id' => 1, 'subject' => 'Hi', 'body_html' => '<p>Hi</p>',
            'status' => 'sent', 'sent_at' => date('Y-m-d H:i:s'),
            'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $result = $session->get('/recipients?last_activity=never');

        $result->assertSee('Never John');
        $result->assertDontSee('Contacted Jane');
    }

    public function testPerPageOptionLimitsResultsPerPage(): void
    {
        for ($i = 1; $i <= 30; $i++) {
            $this->db->table('recipients')->insert([
                'name' => 'Recipient ' . $i, 'email' => "recipient{$i}@example.com", 'status' => 'active',
                'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
            ]);
        }

        $result = $this->loggedIn()->get('/recipients?per_page=25');

        $result->assertSee('Showing 1–25 of 30');
    }

    public function testBulkStatusUpdatesSelectedRecipients(): void
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

        $result = $this->loggedIn()->post('/recipients/bulk-status', ['ids' => [1, 2], 'status' => 'suppressed']);

        $result->assertRedirect();
        $this->seeInDatabase('recipients', ['id' => 1, 'status' => 'suppressed']);
        $this->seeInDatabase('recipients', ['id' => 2, 'status' => 'suppressed']);
        $this->seeInDatabase('recipients', ['id' => 3, 'status' => 'active']);
    }

    public function testBulkStatusRejectsInvalidStatus(): void
    {
        $this->db->table('recipients')->insert([
            'id' => 1, 'name' => 'Jane', 'email' => 'jane@example.com', 'status' => 'active',
            'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $result = $this->loggedIn()->post('/recipients/bulk-status', ['ids' => [1], 'status' => 'not-a-status']);

        $result->assertRedirect();
        $this->seeInDatabase('recipients', ['id' => 1, 'status' => 'active']);
    }

    public function testUpdateStatusChangesSingleRecipient(): void
    {
        $this->db->table('recipients')->insert([
            'id' => 1, 'name' => 'Jane', 'email' => 'jane@example.com', 'status' => 'active',
            'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $result = $this->loggedIn()->post('/recipients/status/1', ['status' => 'unsubscribed']);

        $result->assertRedirect();
        $this->seeInDatabase('recipients', ['id' => 1, 'status' => 'unsubscribed']);
    }

    public function testExportWithSelectedIdsOnlyExportsThoseRecipients(): void
    {
        $this->db->table('recipients')->insert([
            'id' => 1, 'name' => 'Included', 'email' => 'included@example.com', 'status' => 'active',
            'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $this->db->table('recipients')->insert([
            'id' => 2, 'name' => 'Excluded', 'email' => 'excluded@example.com', 'status' => 'active',
            'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $result = $this->loggedIn()->post('/recipients/export', ['ids' => [1]]);

        $body = $result->response()->getBody();
        $this->assertStringContainsString('included@example.com', $body);
        $this->assertStringNotContainsString('excluded@example.com', $body);
    }

    public function testProfilePageShowsContactDetailsAndTimeline(): void
    {
        $session = $this->loggedIn();
        $this->db->table('recipients')->insert([
            'id' => 1, 'name' => 'Jane Doe', 'email' => 'jane@example.com', 'company' => 'Acme', 'status' => 'active',
            'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $this->db->table('email_templates')->insert([
            'id' => 1, 'name' => 'Welcome', 'subject' => 'Hi', 'html_body' => '<p>Hi</p>', 'status' => 'active',
            'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $this->db->table('emails')->insert([
            'id' => 1, 'recipient_id' => 1, 'template_id' => 1, 'user_id' => 1, 'subject' => 'Welcome aboard',
            'body_html' => '<p>Hi</p>', 'status' => 'sent', 'sent_at' => date('Y-m-d H:i:s'),
            'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $this->db->table('email_events')->insert([
            'email_id' => 1, 'type' => 'open', 'ip_address' => '203.0.113.5', 'created_at' => date('Y-m-d H:i:s'),
        ]);

        $result = $session->get('/recipients/view/1');

        $result->assertOK();
        $result->assertSee('Jane Doe');
        $result->assertSee('Acme');
        $result->assertSee('Welcome aboard');
        $result->assertSee('Opened the email');
    }

    public function testProfilePageRedirectsWhenRecipientMissing(): void
    {
        $result = $this->loggedIn()->get('/recipients/view/9999');

        $result->assertRedirect();
    }

    public function testLastActivityColumnShownForRecipients(): void
    {
        $session = $this->loggedIn();
        $this->db->table('recipients')->insert([
            'id' => 1, 'name' => 'Contacted Jane', 'email' => 'jane@example.com', 'status' => 'active',
            'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $this->db->table('emails')->insert([
            'recipient_id' => 1, 'user_id' => 1, 'subject' => 'Hi', 'body_html' => '<p>Hi</p>',
            'status' => 'sent', 'sent_at' => '2020-01-15 10:00:00',
            'created_at' => '2020-01-15 10:00:00', 'updated_at' => '2020-01-15 10:00:00',
        ]);

        $result = $session->get('/recipients');

        $result->assertSee('Jan 15, 2020');
    }

    public function testCampaignColumnShowsMostRecentTemplateSent(): void
    {
        $session = $this->loggedIn();
        $this->db->table('recipients')->insert([
            'id' => 1, 'name' => 'Jane', 'email' => 'jane@example.com', 'status' => 'active',
            'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $this->db->table('email_templates')->insert([
            'id' => 1, 'name' => 'Old Newsletter', 'subject' => 'Hi', 'html_body' => '<p>Hi</p>', 'status' => 'active',
            'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $this->db->table('email_templates')->insert([
            'id' => 2, 'name' => 'Latest Announcement', 'subject' => 'Hi', 'html_body' => '<p>Hi</p>', 'status' => 'active',
            'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $this->db->table('emails')->insert([
            'recipient_id' => 1, 'template_id' => 1, 'user_id' => 1, 'subject' => 'Hi', 'body_html' => '<p>Hi</p>',
            'status' => 'sent', 'sent_at' => '2020-01-01 10:00:00',
            'created_at' => '2020-01-01 10:00:00', 'updated_at' => '2020-01-01 10:00:00',
        ]);
        $this->db->table('emails')->insert([
            'recipient_id' => 1, 'template_id' => 2, 'user_id' => 1, 'subject' => 'Hi', 'body_html' => '<p>Hi</p>',
            'status' => 'sent', 'sent_at' => '2020-06-01 10:00:00',
            'created_at' => '2020-06-01 10:00:00', 'updated_at' => '2020-06-01 10:00:00',
        ]);

        $result = $session->get('/recipients');

        $result->assertSee('Latest Announcement');
    }

    public function testCampaignColumnShowsOneOffForUntemplatedSend(): void
    {
        $session = $this->loggedIn();
        $this->db->table('recipients')->insert([
            'id' => 1, 'name' => 'Jane', 'email' => 'jane@example.com', 'status' => 'active',
            'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $this->db->table('emails')->insert([
            'recipient_id' => 1, 'template_id' => null, 'user_id' => 1, 'subject' => 'Hi', 'body_html' => '<p>Hi</p>',
            'status' => 'sent', 'sent_at' => date('Y-m-d H:i:s'),
            'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $result = $session->get('/recipients');

        $result->assertSee('One-off email');
    }

    public function testCreateFormOffersExistingLocationsAsSuggestions(): void
    {
        $session = $this->loggedIn();
        $this->db->table('recipients')->insert([
            'name' => 'Jane', 'email' => 'jane@example.com', 'status' => 'active', 'location' => 'Canada • Ontario',
            'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $result = $session->get('/recipients/create');

        $result->assertStatus(200);
        $result->assertSee('locationSuggestions');
        $result->assertSee('Canada • Ontario');
    }

    public function testEditFormOffersExistingLocationsAsSuggestions(): void
    {
        $session = $this->loggedIn();
        $this->db->table('recipients')->insert([
            'id' => 1, 'name' => 'Jane', 'email' => 'jane@example.com', 'status' => 'active', 'location' => 'Canada • Ontario',
            'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $this->db->table('recipients')->insert([
            'id' => 2, 'name' => 'Bob', 'email' => 'bob@example.com', 'status' => 'active', 'location' => 'Canada • Alberta',
            'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $result = $session->get('/recipients/edit/1');

        $result->assertStatus(200);
        // Both this recipient's own location and the other recipient's
        // location should be offered -- reusing any existing value is the
        // point, not just this record's current one.
        $result->assertSee('Canada • Ontario');
        $result->assertSee('Canada • Alberta');
    }
}
