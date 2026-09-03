<?php

namespace Tests\Controllers;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

final class TemplateControllerTest extends CIUnitTestCase
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

    public function testCreateTemplate(): void
    {
        $result = $this->loggedIn()->post('/templates/create', [
            'name' => 'Welcome', 'subject' => 'Hi {{name}}', 'html_body' => '<p>Hello {{name}}</p>', 'text_body' => 'Hello {{name}}',
        ]);

        $result->assertRedirect();
        $this->seeInDatabase('email_templates', ['name' => 'Welcome']);
    }

    public function testDuplicateTemplate(): void
    {
        $this->db->table('email_templates')->insert([
            'id' => 1, 'name' => 'Welcome', 'subject' => 'Hi', 'html_body' => '<p>Hi</p>', 'status' => 'active',
            'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $result = $this->loggedIn()->post('/templates/duplicate/1');

        $result->assertRedirect();
        $this->assertSame(2, $this->db->table('email_templates')->countAllResults());
    }

    public function testDeleteTemplate(): void
    {
        $this->db->table('email_templates')->insert([
            'id' => 1, 'name' => 'Welcome', 'subject' => 'Hi', 'html_body' => '<p>Hi</p>', 'status' => 'active',
            'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $result = $this->loggedIn()->post('/templates/delete/1');

        $result->assertRedirect();
        $this->dontSeeInDatabase('email_templates', ['id' => 1]);
    }
}
