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

    public function testRepeatedAttemptsAgainstOneAccountAreRateLimited(): void
    {
        $this->db->table('users')->insert([
            'name' => 'Admin', 'email' => 'admin@test.com',
            'password_hash' => password_hash('Secret123!', PASSWORD_DEFAULT),
            'role' => 'owner', 'status' => 'active',
            'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $service = new AuthService();
        $lastResult = null;
        // Vary the IP each time so only the per-email limit (5/min) can trip.
        for ($i = 0; $i < 6; $i++) {
            $lastResult = $service->attempt('admin@test.com', 'wrong-password', '10.0.0.' . $i);
        }

        $this->assertSame('rate_limited', $lastResult['reason']);
    }

    public function testNonExistentEmailStillPaysPasswordHashingCost(): void
    {
        // Regression guard for timing-based user enumeration: looking up a
        // non-existent email must still run password_verify() against a
        // dummy hash, not short-circuit before it.
        $service = new AuthService();
        $start = microtime(true);
        $service->attempt('nobody@example.com', 'whatever-password', '127.0.0.1');
        $elapsedMs = (microtime(true) - $start) * 1000;

        // A real bcrypt verify takes single-digit-to-tens of milliseconds;
        // a short-circuited lookup with no hashing completes in microseconds.
        $this->assertGreaterThan(1.0, $elapsedMs);
    }
}
