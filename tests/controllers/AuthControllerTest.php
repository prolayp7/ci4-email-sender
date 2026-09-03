<?php

namespace Tests\Controllers;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

final class AuthControllerTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $refresh = true;
    protected $namespace = null;

    public function testLoginPageLoads(): void
    {
        $result = $this->get('/login');
        $result->assertStatus(200);
        $result->assertSee('Sign in');
    }

    public function testValidLoginRedirectsToDashboard(): void
    {
        $this->db->table('users')->insert([
            'name' => 'Admin', 'email' => 'admin@test.com',
            'password_hash' => password_hash('Secret123!', PASSWORD_DEFAULT),
            'role' => 'owner', 'status' => 'active',
            'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $result = $this->withSession([])->post('/login', [
            'email' => 'admin@test.com', 'password' => 'Secret123!',
        ]);

        $result->assertRedirectTo('/dashboard');
    }

    public function testUnauthorizedAccessRedirectsToLogin(): void
    {
        $result = $this->get('/dashboard');
        $result->assertRedirectTo('/login');
    }
}
