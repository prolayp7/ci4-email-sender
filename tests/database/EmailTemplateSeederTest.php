<?php

namespace Tests\Database;

use App\Database\Seeds\EmailTemplateSeeder;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

final class EmailTemplateSeederTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $refresh = true;
    protected $namespace = null;
    protected $seed = EmailTemplateSeeder::class;

    public function testSeederInsertsTemplates(): void
    {
        $this->seeInDatabase('email_templates', ['name' => 'Welcome Email', 'status' => 'active']);
        $this->assertGreaterThanOrEqual(3, $this->db->table('email_templates')->countAllResults());
    }

    public function testSeederIsSafeToRunTwice(): void
    {
        $this->seed(EmailTemplateSeeder::class);

        $count = $this->db->table('email_templates')->where('name', 'Welcome Email')->countAllResults();
        $this->assertSame(1, $count);
    }
}
