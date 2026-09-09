<?php

namespace Tests\Services;

use App\Services\GroupService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

final class GroupServiceTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $refresh = true;
    protected $namespace = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->db->table('recipients')->insert([
            'id' => 1, 'name' => 'Jane', 'email' => 'jane@example.com', 'status' => 'active',
            'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $this->db->table('recipients')->insert([
            'id' => 2, 'name' => 'Bob', 'email' => 'bob@example.com', 'status' => 'unsubscribed',
            'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function testFindOrCreateCreatesANewGroupOnce(): void
    {
        $service = new GroupService();
        $id1 = $service->findOrCreate('Ontario');
        $id2 = $service->findOrCreate('Ontario');

        $this->assertSame($id1, $id2);
        $this->assertSame(1, $this->db->table('groups')->where('name', 'Ontario')->countAllResults());
    }

    public function testAddRecipientsDoesNotDuplicateExistingMembership(): void
    {
        $service = new GroupService();
        $groupId = $service->findOrCreate('Ontario');

        $service->addRecipients($groupId, [1, 2]);
        $service->addRecipients($groupId, [1]); // re-adding an existing member should be a no-op

        $this->assertSame(2, $this->db->table('recipient_groups')->where('group_id', $groupId)->countAllResults());
    }

    public function testAddRecipientsNeverRemovesMembershipInOtherGroups(): void
    {
        $service = new GroupService();
        $groupA = $service->findOrCreate('Ontario');
        $groupB = $service->findOrCreate('VIP');

        $service->addRecipients($groupA, [1]);
        $service->addRecipients($groupB, [1]);

        $this->assertSame(['Ontario', 'VIP'], $service->namesForRecipient(1));
    }

    public function testAllWithCountsReportsTotalAndSendableSeparately(): void
    {
        $service = new GroupService();
        $groupId = $service->findOrCreate('Ontario');
        $service->addRecipients($groupId, [1, 2]); // 1 active, 1 unsubscribed

        $groups = $service->allWithCounts();

        $this->assertSame(2, $groups[0]['total']);
        $this->assertSame(1, $groups[0]['sendable']);
    }

    public function testSendableRecipientIdsExcludesInactiveRecipients(): void
    {
        $service = new GroupService();
        $groupId = $service->findOrCreate('Ontario');
        $service->addRecipients($groupId, [1, 2]);

        $this->assertSame([1], $service->sendableRecipientIds($groupId));
    }

    public function testRemoveRecipientOnlyAffectsThatGroup(): void
    {
        $service = new GroupService();
        $groupA = $service->findOrCreate('Ontario');
        $groupB = $service->findOrCreate('VIP');
        $service->addRecipients($groupA, [1]);
        $service->addRecipients($groupB, [1]);

        $service->removeRecipient($groupA, 1);

        $this->assertSame(['VIP'], $service->namesForRecipient(1));
    }

    public function testDeleteGroupCascadesMembershipButNotRecipients(): void
    {
        $service = new GroupService();
        $groupId = $service->findOrCreate('Ontario');
        $service->addRecipients($groupId, [1]);

        $service->delete($groupId);

        $this->assertSame(0, $this->db->table('groups')->where('id', $groupId)->countAllResults());
        $this->assertSame(0, $this->db->table('recipient_groups')->where('group_id', $groupId)->countAllResults());
        $this->assertSame(1, $this->db->table('recipients')->where('id', 1)->countAllResults());
    }
}
