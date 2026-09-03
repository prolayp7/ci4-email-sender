<?php

namespace Tests\Controllers;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

final class SmtpControllerTest extends CIUnitTestCase
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

    public function testSaveSmtpSettingsNeverReturnsPasswordInResponse(): void
    {
        $session = $this->loggedIn();

        $result = $session->post('/smtp', [
            'provider' => 'gmail', 'label' => 'Gmail', 'host' => 'smtp.gmail.com', 'port' => 587, 'encryption' => 'tls',
            'username' => 'me@gmail.com', 'password' => 'super-secret-pass', 'from_email' => 'me@gmail.com', 'from_name' => 'Me',
        ]);

        $result->assertRedirect();
        $this->seeInDatabase('smtp_settings', ['provider' => 'gmail', 'host' => 'smtp.gmail.com']);
        $page = $session->get('/smtp');
        $page->assertDontSee('super-secret-pass');
    }

    public function testMicrosoft365ProviderIsNoLongerAccepted(): void
    {
        $session = $this->loggedIn();

        $result = $session->post('/smtp', [
            'provider' => 'microsoft365', 'label' => 'M365', 'host' => 'smtp.office365.com', 'port' => 587, 'encryption' => 'tls',
            'username' => 'me@example.com', 'password' => 'secret', 'from_email' => 'me@example.com', 'from_name' => 'Me',
        ]);

        $result->assertRedirect();
        $this->dontSeeInDatabase('smtp_settings', ['provider' => 'microsoft365']);
    }
}
