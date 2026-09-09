<?php

namespace Tests\Controllers;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

final class GroupControllerTest extends CIUnitTestCase
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

    public function testCreateGroup(): void
    {
        $result = $this->loggedIn()->post('/groups', ['name' => 'Ontario']);

        $result->assertRedirect();
        $this->seeInDatabase('groups', ['name' => 'Ontario']);
    }

    public function testCreateRejectsBlankName(): void
    {
        $result = $this->loggedIn()->post('/groups', ['name' => '  ']);

        $result->assertRedirect();
        $this->assertSame(0, $this->db->table('groups')->countAllResults());
    }

    public function testIndexShowsGroupCounts(): void
    {
        $session = $this->loggedIn();
        $this->db->table('recipients')->insert([
            'id' => 1, 'name' => 'Jane', 'email' => 'jane@example.com', 'status' => 'active',
            'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $this->db->table('groups')->insert(['id' => 1, 'name' => 'Ontario', 'created_at' => date('Y-m-d H:i:s')]);
        $this->db->table('recipient_groups')->insert(['recipient_id' => 1, 'group_id' => 1]);

        $result = $session->get('/groups');

        $result->assertStatus(200);
        $result->assertSee('Ontario');
    }

    public function testDeleteGroupRemovesItButKeepsRecipients(): void
    {
        $session = $this->loggedIn();
        $this->db->table('recipients')->insert([
            'id' => 1, 'name' => 'Jane', 'email' => 'jane@example.com', 'status' => 'active',
            'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $this->db->table('groups')->insert(['id' => 1, 'name' => 'Ontario', 'created_at' => date('Y-m-d H:i:s')]);
        $this->db->table('recipient_groups')->insert(['recipient_id' => 1, 'group_id' => 1]);

        $result = $session->post('/groups/delete/1');

        $result->assertRedirect();
        $this->dontSeeInDatabase('groups', ['id' => 1]);
        $this->seeInDatabase('recipients', ['id' => 1]);
    }

    public function testAddRecipientsCreatesGroupWhenNameIsNew(): void
    {
        $session = $this->loggedIn();
        $this->db->table('recipients')->insert([
            'id' => 1, 'name' => 'Jane', 'email' => 'jane@example.com', 'status' => 'active',
            'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $result = $session->post('/groups/add-recipients', ['group_name' => 'Ontario', 'ids' => [1]]);

        $result->assertRedirect();
        $this->seeInDatabase('groups', ['name' => 'Ontario']);
        $groupId = $this->db->table('groups')->where('name', 'Ontario')->get()->getRowArray()['id'];
        $this->seeInDatabase('recipient_groups', ['group_id' => $groupId, 'recipient_id' => 1]);
    }

    public function testAddRecipientsReusesAnExistingGroupByName(): void
    {
        $session = $this->loggedIn();
        $this->db->table('recipients')->insert([
            'id' => 1, 'name' => 'Jane', 'email' => 'jane@example.com', 'status' => 'active',
            'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $this->db->table('groups')->insert(['id' => 1, 'name' => 'Ontario', 'created_at' => date('Y-m-d H:i:s')]);

        $session->post('/groups/add-recipients', ['group_name' => 'Ontario', 'ids' => [1]]);

        $this->assertSame(1, $this->db->table('groups')->countAllResults());
        $this->seeInDatabase('recipient_groups', ['group_id' => 1, 'recipient_id' => 1]);
    }

    public function testRemoveRecipientFromGroup(): void
    {
        $session = $this->loggedIn();
        $this->db->table('recipients')->insert([
            'id' => 1, 'name' => 'Jane', 'email' => 'jane@example.com', 'status' => 'active',
            'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $this->db->table('groups')->insert(['id' => 1, 'name' => 'Ontario', 'created_at' => date('Y-m-d H:i:s')]);
        $this->db->table('recipient_groups')->insert(['recipient_id' => 1, 'group_id' => 1]);

        $result = $session->post('/groups/remove-recipient/1/1');

        $result->assertRedirect();
        $this->dontSeeInDatabase('recipient_groups', ['group_id' => 1, 'recipient_id' => 1]);
        $this->seeInDatabase('recipients', ['id' => 1]);
    }

    public function testViewShowsMembersAndAvailableRecipientsToAdd(): void
    {
        $session = $this->loggedIn();
        $this->db->table('recipients')->insert([
            'id' => 1, 'name' => 'Jane Member', 'email' => 'jane@example.com', 'status' => 'active',
            'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $this->db->table('recipients')->insert([
            'id' => 2, 'name' => 'Bob Available', 'email' => 'bob@example.com', 'status' => 'active',
            'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $this->db->table('groups')->insert(['id' => 1, 'name' => 'Ontario', 'created_at' => date('Y-m-d H:i:s')]);
        $this->db->table('recipient_groups')->insert(['recipient_id' => 1, 'group_id' => 1]);

        $result = $session->get('/groups/view/1');

        $result->assertStatus(200);
        $result->assertSee('Jane Member');
        $result->assertSee('Bob Available');
    }

    public function testOperatorRoleCannotCreateOrDeleteGroups(): void
    {
        $this->db->table('users')->insert([
            'id' => 2, 'name' => 'Viewer', 'email' => 'viewer@test.com',
            'password_hash' => password_hash('x', PASSWORD_DEFAULT), 'role' => 'viewer', 'status' => 'active',
            'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $session = $this->withSession(['isLoggedIn' => true, 'user_id' => 2, 'user_role' => 'viewer', 'user_name' => 'Viewer']);

        $result = $session->post('/groups', ['name' => 'Ontario']);

        $result->assertStatus(403);
    }
}
