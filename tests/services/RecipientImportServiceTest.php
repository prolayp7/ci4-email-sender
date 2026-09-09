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

    /** Mirrors the real upload -> auto-map flow: read headers, use the suggested mapping as-is. */
    private function autoMap(string $path): array
    {
        return (new RecipientImportService())->readHeader($path)['suggestedMapping'];
    }

    public function testReadHeaderSuggestsMappingByExactColumnName(): void
    {
        $path = $this->writeCsv("Name,Email,Company,Phone\nJane Doe,jane@example.com,Acme,555-1234\n");
        $result = (new RecipientImportService())->readHeader($path);

        $this->assertSame(['Name', 'Email', 'Company', 'Phone'], $result['headers']);
        $this->assertSame(['name' => 0, 'email' => 1, 'company' => 2, 'location' => null, 'phone' => 3], $result['suggestedMapping']);
    }

    public function testReadHeaderReportsAnEmptyFile(): void
    {
        $result = (new RecipientImportService())->readHeader($this->writeCsv(''));

        $this->assertArrayHasKey('error', $result);
    }

    public function testImportsValidRowsUsingMapping(): void
    {
        $path = $this->writeCsv("Name,Email,Company,Phone\nJane Doe,jane@example.com,Acme,555-1234\nJohn Roe,john@example.com,Acme,555-5678\n");
        $result = (new RecipientImportService())->import($path, $this->autoMap($path));

        $this->assertSame(2, $result['imported']);
        $this->assertSame(0, $result['invalid']);
        $this->assertSame(0, $result['duplicates']);
        $this->seeInDatabase('recipients', ['email' => 'jane@example.com']);
    }

    public function testMappingWorksRegardlessOfColumnOrder(): void
    {
        // Phone first, Email last -- a real user's file won't match our column order.
        $path = $this->writeCsv("Phone,Name,Email\n555-1234,Jane Doe,jane@example.com\n");
        $result = (new RecipientImportService())->import($path, $this->autoMap($path));

        $this->assertSame(1, $result['imported']);
        $this->seeInDatabase('recipients', ['email' => 'jane@example.com', 'name' => 'Jane Doe', 'phone' => '555-1234']);
    }

    public function testUnmappedFieldIsStoredAsNull(): void
    {
        $path = $this->writeCsv("Name,Email\nJane Doe,jane@example.com\n");
        $mapping = $this->autoMap($path); // company/location/phone all null -- not present in this file

        (new RecipientImportService())->import($path, $mapping);

        $this->seeInDatabase('recipients', ['email' => 'jane@example.com', 'company' => null]);
    }

    public function testRejectsImportWithNoEmailColumnMapped(): void
    {
        $path = $this->writeCsv("Name,Notes\nJane Doe,hello\n");
        $result = (new RecipientImportService())->import($path, ['name' => 0, 'email' => null, 'company' => null, 'location' => null, 'phone' => null]);

        $this->assertSame(0, $result['imported']);
        $this->assertNotEmpty($result['errors']);
    }

    public function testImportsLocationColumnWhenPresent(): void
    {
        $path = $this->writeCsv("Name,Email,Location\nJane Doe,jane@example.com,New York\n");
        $result = (new RecipientImportService())->import($path, $this->autoMap($path));

        $this->assertSame(1, $result['imported']);
        $this->seeInDatabase('recipients', ['email' => 'jane@example.com', 'location' => 'New York']);
    }

    public function testSkipsInvalidEmails(): void
    {
        $path = $this->writeCsv("Name,Email,Company,Phone\nBad Row,not-an-email,Acme,\n");
        $result = (new RecipientImportService())->import($path, $this->autoMap($path));

        $this->assertSame(0, $result['imported']);
        $this->assertSame(1, $result['invalid']);
    }

    public function testDetectsDuplicatesAgainstDbAndWithinFile(): void
    {
        $this->db->table('recipients')->insert([
            'name' => 'Existing', 'email' => 'jane@example.com', 'status' => 'active',
            'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $path = $this->writeCsv("Name,Email,Company,Phone\nJane Dup,jane@example.com,Acme,\nJohn New,john@example.com,Acme,\nJohn Again,john@example.com,Acme,\n");
        $result = (new RecipientImportService())->import($path, $this->autoMap($path));

        $this->assertSame(1, $result['imported']);
        $this->assertSame(2, $result['duplicates']);
        $this->assertSame(0, $result['updated']);
    }

    public function testUpdateModeOverwritesExistingRecipientFields(): void
    {
        $this->db->table('recipients')->insert([
            'name' => 'Old Name', 'email' => 'jane@example.com', 'company' => 'Old Co', 'status' => 'active',
            'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $path = $this->writeCsv("Name,Email,Company\nJane New Name,jane@example.com,New Co\n");
        $result = (new RecipientImportService())->import($path, $this->autoMap($path), 'update');

        $this->assertSame(0, $result['imported']);
        $this->assertSame(1, $result['duplicates']);
        $this->assertSame(1, $result['updated']);
        $this->seeInDatabase('recipients', ['email' => 'jane@example.com', 'name' => 'Jane New Name', 'company' => 'New Co']);
    }

    public function testPreviewClassifiesRowsWithoutWritingAnything(): void
    {
        $this->db->table('recipients')->insert([
            'name' => 'Existing', 'email' => 'jane@example.com', 'status' => 'active',
            'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $path = $this->writeCsv("Name,Email\nJane Dup,jane@example.com\nJohn New,john@example.com\nBad,not-an-email\n");
        $result = (new RecipientImportService())->preview($path, $this->autoMap($path));

        $this->assertSame(1, $result['imported']);
        $this->assertSame(1, $result['duplicates']);
        $this->assertSame(1, $result['invalid']);
        $this->dontSeeInDatabase('recipients', ['email' => 'john@example.com']);
    }

    public function testImportReportsRecipientIdsForBothNewAndExistingRows(): void
    {
        $this->db->table('recipients')->insert([
            'id' => 5, 'name' => 'Existing', 'email' => 'existing@example.com', 'status' => 'active',
            'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $path = $this->writeCsv("Name,Email\nExisting,existing@example.com\nNew Person,new@example.com\n");
        $result = (new RecipientImportService())->import($path, $this->autoMap($path));

        $newId = (int) $this->db->table('recipients')->where('email', 'new@example.com')->get()->getRowArray()['id'];
        $this->assertSame([5, $newId], $result['recipientIds']);
    }
}
