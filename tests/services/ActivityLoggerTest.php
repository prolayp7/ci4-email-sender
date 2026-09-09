<?php

namespace Tests\Services;

use App\Services\ActivityLogger;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

final class ActivityLoggerTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $refresh = true;
    protected $namespace = null;

    public function testLogInsertsRow(): void
    {
        $this->db->table('users')->insert([
            'id' => 1, 'name' => 'Admin', 'email' => 'admin@test.com',
            'password_hash' => password_hash('x', PASSWORD_DEFAULT), 'role' => 'owner', 'status' => 'active',
            'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);

        ActivityLogger::log(1, 'login', 'Admin logged in', '127.0.0.1');

        $this->seeInDatabase('activity_logs', [
            'user_id' => 1,
            'action'  => 'login',
        ]);
    }

    public function testLogRedactsPasswordLikeContent(): void
    {
        ActivityLogger::log(null, 'smtp.updated', 'SMTP saved password=Secret123!');

        $row = $this->db->table('activity_logs')->where('action', 'smtp.updated')->get()->getRowArray();
        $this->assertStringNotContainsString('Secret123!', $row['description']);
    }

    public function testLogRedactsQuotedSecretsContainingSpaces(): void
    {
        ActivityLogger::log(null, 'smtp.updated', 'Config saved token="a secret with spaces" ok');

        $row = $this->db->table('activity_logs')->where('action', 'smtp.updated')->get()->getRowArray();
        $this->assertStringNotContainsString('a secret with spaces', $row['description']);
    }

    public function testLogStripsUnrenderedTemplatePlaceholders(): void
    {
        ActivityLogger::log(null, 'email.batch_sent', 'Bulk send: Hi {{name}}, welcome to {{company}} — 2/2 sent');

        $row = $this->db->table('activity_logs')->where('action', 'email.batch_sent')->get()->getRowArray();
        $this->assertStringNotContainsString('{{name}}', $row['description']);
        $this->assertStringNotContainsString('{{company}}', $row['description']);
        $this->assertStringContainsString('Bulk send: Hi , welcome to', $row['description']);
    }

    public function testLogTruncatesDescriptionsLongerThanTheColumnLimit(): void
    {
        ActivityLogger::log(null, 'email.batch_sent', str_repeat('a', 400));

        $row = $this->db->table('activity_logs')->where('action', 'email.batch_sent')->get()->getRowArray();
        $this->assertLessThanOrEqual(255, strlen($row['description']));
        $this->assertStringEndsWith('...', $row['description']);
    }
}
