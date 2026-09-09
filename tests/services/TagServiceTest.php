<?php

namespace Tests\Services;

use App\Services\TagService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

final class TagServiceTest extends CIUnitTestCase
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
    }

    public function testSyncCreatesNewTagsAndAssignsThem(): void
    {
        (new TagService())->syncForRecipient(1, 'WordPress, Developer, Hot Lead');

        $this->assertSame(['Developer', 'Hot Lead', 'WordPress'], (new TagService())->namesForRecipient(1));
        $this->assertSame(3, $this->db->table('tags')->countAllResults());
    }

    public function testSyncReusesAnExistingTagAcrossRecipients(): void
    {
        $this->db->table('recipients')->insert([
            'id' => 2, 'name' => 'John', 'email' => 'john@example.com', 'status' => 'active',
            'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);

        (new TagService())->syncForRecipient(1, 'Developer');
        (new TagService())->syncForRecipient(2, 'Developer');

        $this->assertSame(1, $this->db->table('tags')->where('name', 'Developer')->countAllResults());
    }

    public function testSyncRemovesTagsNoLongerInTheList(): void
    {
        (new TagService())->syncForRecipient(1, 'WordPress, Developer');
        (new TagService())->syncForRecipient(1, 'Developer');

        $this->assertSame(['Developer'], (new TagService())->namesForRecipient(1));
    }

    public function testSyncWithEmptyStringClearsAllTags(): void
    {
        (new TagService())->syncForRecipient(1, 'WordPress, Developer');
        (new TagService())->syncForRecipient(1, '');

        $this->assertSame([], (new TagService())->namesForRecipient(1));
    }

    public function testSyncTrimsAndIgnoresDuplicateOrBlankEntries(): void
    {
        (new TagService())->syncForRecipient(1, ' WordPress ,, WordPress ,  ');

        $this->assertSame(['WordPress'], (new TagService())->namesForRecipient(1));
    }

    public function testAllReturnsTagsAlphabetically(): void
    {
        (new TagService())->syncForRecipient(1, 'Zeta, Alpha');

        $names = array_column((new TagService())->all(), 'name');
        $this->assertSame(['Alpha', 'Zeta'], $names);
    }
}
