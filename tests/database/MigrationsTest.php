<?php

namespace Tests\Database;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Config\Database;

final class MigrationsTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $refresh = true;
    protected $namespace = null;

    public function testAllTablesExist(): void
    {
        $db = Database::connect();
        $tables = $db->listTables();

        foreach (['users', 'recipients', 'email_templates', 'smtp_settings', 'emails', 'activity_logs', 'email_batches', 'email_batch_attachments', 'email_attachments'] as $table) {
            $this->assertContains($table, $tables, "Missing table: {$table}");
        }
    }

    public function testEmailsForeignKeysAndIndexes(): void
    {
        $db = Database::connect();
        $fields = $db->getFieldNames('emails');

        foreach (['recipient_id', 'template_id', 'user_id', 'status', 'sent_at', 'attempt_count', 'message_id'] as $column) {
            $this->assertContains($column, $fields);
        }
    }

    public function testEmailsHasDeletedAtAndBatchIdColumns(): void
    {
        $db = Database::connect();
        $fields = $db->getFieldNames('emails');

        $this->assertContains('deleted_at', $fields);
        $this->assertContains('batch_id', $fields);
    }
}
