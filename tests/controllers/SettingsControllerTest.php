<?php

namespace Tests\Controllers;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

final class SettingsControllerTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $refresh = true;
    protected $namespace = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->db->table('users')->insert([
            'id' => 1, 'name' => 'Admin', 'email' => 'admin@test.com',
            'password_hash' => password_hash('OldPass123!', PASSWORD_DEFAULT), 'role' => 'owner', 'status' => 'active',
            'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    private function loggedIn(): self
    {
        return $this->withSession(['isLoggedIn' => true, 'user_id' => 1, 'user_role' => 'owner', 'user_name' => 'Admin']);
    }

    public function testSettingsPageLoadsWithoutPasswordHash(): void
    {
        $result = $this->loggedIn()->get('/settings');
        $result->assertStatus(200);
        $result->assertSee('admin@test.com');
        $result->assertDontSee(password_hash('OldPass123!', PASSWORD_DEFAULT));
    }

    public function testChangePasswordRequiresCorrectCurrentPassword(): void
    {
        $this->loggedIn()->post('/settings/password', [
            'current_password' => 'wrong', 'new_password' => 'NewPass123!', 'confirm_password' => 'NewPass123!',
        ])->assertRedirectTo('/settings');

        $row = $this->db->table('users')->where('id', 1)->get()->getRowArray();
        $this->assertTrue(password_verify('OldPass123!', $row['password_hash']));
    }

    public function testChangePasswordRejectsMismatchedConfirmation(): void
    {
        $this->loggedIn()->post('/settings/password', [
            'current_password' => 'OldPass123!', 'new_password' => 'NewPass123!', 'confirm_password' => 'Different123!',
        ])->assertRedirectTo('/settings');
        $row = $this->db->table('users')->where('id', 1)->get()->getRowArray();
        $this->assertTrue(password_verify('OldPass123!', $row['password_hash']));
    }

    public function testPasswordCanBeChanged(): void
    {
        $this->loggedIn()->post('/settings/password', [
            'current_password' => 'OldPass123!', 'new_password' => 'NewPass123!', 'confirm_password' => 'NewPass123!',
        ])->assertRedirectTo('/settings');
        $row = $this->db->table('users')->where('id', 1)->get()->getRowArray();
        $this->assertTrue(password_verify('NewPass123!', $row['password_hash']));
        $this->seeInDatabase('activity_logs', ['user_id' => 1, 'action' => 'user.password_changed']);
    }
}
