<?php

namespace Tests\Services;

use App\Services\RecipientImportService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

final class RecipientImportServiceTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $refresh = true;
    protected $namespace = null;

    private function writeCsv(string $content): string
    {
        $path = WRITEPATH . 'uploads/test_' . uniqid() . '.csv';
        @mkdir(dirname($path), 0755, true);
        file_put_contents($path, $content);
        return $path;
    }

    public function testImportsValidRows(): void
    {
        $csv = "Name,Email,Company,Phone\nJane Doe,jane@example.com,Acme,555-1234\nJohn Roe,john@example.com,Acme,555-5678\n";
        $result = (new RecipientImportService())->import($this->writeCsv($csv));

        $this->assertSame(2, $result['imported']);
        $this->assertSame(0, $result['invalid']);
        $this->assertSame(0, $result['duplicates']);
        $this->seeInDatabase('recipients', ['email' => 'jane@example.com']);
    }

    public function testSkipsInvalidEmails(): void
    {
        $csv = "Name,Email,Company,Phone\nBad Row,not-an-email,Acme,\n";
        $result = (new RecipientImportService())->import($this->writeCsv($csv));

        $this->assertSame(0, $result['imported']);
        $this->assertSame(1, $result['invalid']);
    }

    public function testDetectsDuplicatesAgainstDbAndWithinFile(): void
    {
        $this->db->table('recipients')->insert([
            'name' => 'Existing', 'email' => 'jane@example.com', 'status' => 'active',
            'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $csv = "Name,Email,Company,Phone\nJane Dup,jane@example.com,Acme,\nJohn New,john@example.com,Acme,\nJohn Again,john@example.com,Acme,\n";
        $result = (new RecipientImportService())->import($this->writeCsv($csv));

        $this->assertSame(1, $result['imported']);
        $this->assertSame(2, $result['duplicates']);
    }
}
