<?php

namespace Tests\Services;

use App\Services\AttachmentService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

final class AttachmentServiceTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $refresh = true;
    protected $namespace = null;

    private function insertEmail(int $id): void
    {
        $now = date('Y-m-d H:i:s');
        $this->db->table('users')->insert([
            'id' => $id, 'name' => 'Admin', 'email' => "admin{$id}@test.com",
            'password_hash' => password_hash('x', PASSWORD_DEFAULT), 'role' => 'owner', 'status' => 'active',
            'created_at' => $now, 'updated_at' => $now,
        ]);
        $this->db->table('recipients')->insert([
            'id' => $id, 'name' => 'Jane', 'email' => "jane{$id}@example.com", 'status' => 'active',
            'created_at' => $now, 'updated_at' => $now,
        ]);
        $this->db->table('emails')->insert([
            'id' => $id, 'recipient_id' => $id, 'user_id' => $id, 'subject' => 'Hi', 'body_html' => '<p>Hi</p>',
            'status' => 'sent', 'attempt_count' => 1, 'created_at' => $now, 'updated_at' => $now,
        ]);
    }

    private function touchFile(string $storedName): string
    {
        $path = WRITEPATH . 'uploads/' . $storedName;
        @mkdir(dirname($path), 0755, true);
        file_put_contents($path, 'dummy');
        return $path;
    }

    public function testPersistInsertsOneRowPerFile(): void
    {
        $this->insertEmail(1);
        (new AttachmentService())->persist(1, [
            ['original_filename' => 'a.pdf', 'stored_filename' => 'stored-a.pdf', 'mime_type' => 'application/pdf', 'size_bytes' => 100],
            ['original_filename' => 'b.png', 'stored_filename' => 'stored-b.png', 'mime_type' => 'image/png', 'size_bytes' => 200],
        ]);

        $this->seeInDatabase('email_attachments', ['email_id' => 1, 'original_filename' => 'a.pdf']);
        $this->seeInDatabase('email_attachments', ['email_id' => 1, 'original_filename' => 'b.png']);
    }

    public function testPersistWithEmptyListInsertsNothing(): void
    {
        $this->insertEmail(1);
        (new AttachmentService())->persist(1, []);
        $this->assertSame(0, $this->db->table('email_attachments')->countAllResults());
    }

    public function testListForReturnsOnlyThatEmailsAttachments(): void
    {
        $this->insertEmail(1);
        $this->insertEmail(2);
        (new AttachmentService())->persist(1, [
            ['original_filename' => 'a.pdf', 'stored_filename' => 'stored-a.pdf', 'mime_type' => 'application/pdf', 'size_bytes' => 100],
        ]);
        (new AttachmentService())->persist(2, [
            ['original_filename' => 'c.pdf', 'stored_filename' => 'stored-c.pdf', 'mime_type' => 'application/pdf', 'size_bytes' => 100],
        ]);

        $list = (new AttachmentService())->listFor(1);
        $this->assertCount(1, $list);
        $this->assertSame('a.pdf', $list[0]['original_filename']);
    }

    public function testFindRequiresBothEmailIdAndAttachmentIdToMatch(): void
    {
        $this->insertEmail(1);
        (new AttachmentService())->persist(1, [
            ['original_filename' => 'a.pdf', 'stored_filename' => 'stored-a.pdf', 'mime_type' => 'application/pdf', 'size_bytes' => 100],
        ]);
        $attachmentId = (int) $this->db->table('email_attachments')->where('email_id', 1)->get()->getRow()->id;

        $this->assertNotNull((new AttachmentService())->find(1, $attachmentId));
        $this->assertNull((new AttachmentService())->find(999, $attachmentId));
    }

    public function testDeleteOneUnlinksFileWhenNoOtherRowSharesIt(): void
    {
        $this->insertEmail(1);
        $path = $this->touchFile('solo.pdf');
        (new AttachmentService())->persist(1, [
            ['original_filename' => 'a.pdf', 'stored_filename' => 'solo.pdf', 'mime_type' => 'application/pdf', 'size_bytes' => 100],
        ]);
        $attachmentId = (int) $this->db->table('email_attachments')->where('email_id', 1)->get()->getRow()->id;

        (new AttachmentService())->deleteOne($attachmentId);

        $this->dontSeeInDatabase('email_attachments', ['id' => $attachmentId]);
        $this->assertFileDoesNotExist($path);
    }

    public function testDeleteOneKeepsFileWhenAnotherRowStillReferencesIt(): void
    {
        $this->insertEmail(1);
        $this->insertEmail(2);
        $path = $this->touchFile('shared.pdf');
        (new AttachmentService())->persist(1, [
            ['original_filename' => 'a.pdf', 'stored_filename' => 'shared.pdf', 'mime_type' => 'application/pdf', 'size_bytes' => 100],
        ]);
        (new AttachmentService())->persist(2, [
            ['original_filename' => 'a.pdf', 'stored_filename' => 'shared.pdf', 'mime_type' => 'application/pdf', 'size_bytes' => 100],
        ]);
        $firstId = (int) $this->db->table('email_attachments')->where('email_id', 1)->get()->getRow()->id;

        (new AttachmentService())->deleteOne($firstId);

        $this->dontSeeInDatabase('email_attachments', ['id' => $firstId]);
        $this->assertFileExists($path);

        @unlink($path);
    }

    public function testDeleteAllForRemovesEveryRowForThatEmail(): void
    {
        $this->insertEmail(1);
        (new AttachmentService())->persist(1, [
            ['original_filename' => 'a.pdf', 'stored_filename' => 'a1.pdf', 'mime_type' => 'application/pdf', 'size_bytes' => 100],
            ['original_filename' => 'b.pdf', 'stored_filename' => 'b1.pdf', 'mime_type' => 'application/pdf', 'size_bytes' => 100],
        ]);

        (new AttachmentService())->deleteAllFor(1);

        $this->assertSame(0, $this->db->table('email_attachments')->where('email_id', 1)->countAllResults());
    }

    public function testCopyBatchAttachmentsMaterializesBatchFilesOntoOneEmail(): void
    {
        $this->insertEmail(1);
        $now = date('Y-m-d H:i:s');
        $this->db->table('email_batches')->insert([
            'id' => 1, 'subject' => 'Bulk', 'body_html' => '<p>Bulk email</p>', 'user_id' => 1, 'recipient_count' => 2, 'created_at' => $now,
        ]);
        $this->db->table('email_batch_attachments')->insert([
            'batch_id' => 1, 'original_filename' => 'flyer.pdf', 'stored_filename' => 'flyer-x.pdf',
            'mime_type' => 'application/pdf', 'size_bytes' => 500, 'created_at' => $now,
        ]);

        (new AttachmentService())->copyBatchAttachments(1, 1);

        $this->seeInDatabase('email_attachments', ['email_id' => 1, 'stored_filename' => 'flyer-x.pdf']);
    }
}
