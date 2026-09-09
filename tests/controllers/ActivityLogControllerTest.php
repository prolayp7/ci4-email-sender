<?php

namespace Tests\Controllers;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

final class ActivityLogControllerTest extends CIUnitTestCase
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

    public function testShowsFullUnmaskedIpAddress(): void
    {
        $session = $this->loggedIn();
        $this->db->table('activity_logs')->insert([
            'user_id' => 1, 'action' => 'login', 'description' => 'User logged in',
            'ip_address' => '203.0.113.42', 'created_at' => date('Y-m-d H:i:s'),
        ]);

        $result = $session->get('/activity');

        $result->assertStatus(200);
        $result->assertSee('203.0.113.42');
        $result->assertSee('Admin');
    }

    public function testOperatorRoleIsForbidden(): void
    {
        $this->db->table('users')->insert([
            'id' => 2, 'name' => 'Op', 'email' => 'op@test.com',
            'password_hash' => password_hash('x', PASSWORD_DEFAULT),
            'role' => 'operator', 'status' => 'active',
            'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $result = $this->withSession(['isLoggedIn' => true, 'user_id' => 2, 'user_role' => 'operator', 'user_name' => 'Op'])
            ->get('/activity');

        $result->assertStatus(403);
    }
}
