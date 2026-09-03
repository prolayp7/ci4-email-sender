<?php

namespace Tests\Services;

use App\Services\AuthService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

final class AuthServiceTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $refresh = true;
    protected $namespace = null;

    public function testValidLoginSucceedsAndRegeneratesSession(): void
    {
        $this->db->table('users')->insert([
            'name' => 'Admin', 'email' => 'admin@test.com',
            'password_hash' => password_hash('Secret123!', PASSWORD_DEFAULT),
            'role' => 'owner', 'status' => 'active',
            'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $service = new AuthService();
        $result = $service->attempt('admin@test.com', 'Secret123!', '127.0.0.1');

        $this->assertTrue($result['success']);
        $this->assertSame('admin@test.com', $result['user']['email']);
    }

    public function testInvalidPasswordFails(): void
    {
        $this->db->table('users')->insert([
            'name' => 'Admin', 'email' => 'admin@test.com',
            'password_hash' => password_hash('Secret123!', PASSWORD_DEFAULT),
            'role' => 'owner', 'status' => 'active',
            'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $service = new AuthService();
        $result = $service->attempt('admin@test.com', 'wrong-password', '127.0.0.1');

        $this->assertFalse($result['success']);
        $this->assertSame('invalid_credentials', $result['reason']);
    }

    public function testDisabledUserCannotLogin(): void
    {
        $this->db->table('users')->insert([
            'name' => 'Admin', 'email' => 'admin@test.com',
            'password_hash' => password_hash('Secret123!', PASSWORD_DEFAULT),
            'role' => 'owner', 'status' => 'disabled',
            'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $service = new AuthService();
        $result = $service->attempt('admin@test.com', 'Secret123!', '127.0.0.1');

        $this->assertFalse($result['success']);
        $this->assertSame('account_disabled', $result['reason']);
    }
}
