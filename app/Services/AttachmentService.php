<?php

namespace App\Services;

class AttachmentService
{
    /**
     * @param list<array{original_filename:string, stored_filename:string, mime_type:string, size_bytes:int|string}> $files
     */
    public function persist(int $emailId, array $files): void
    {
        if ($files === []) {
            return;
        }

        $now = date('Y-m-d H:i:s');
        $rows = array_map(static fn (array $f) => [
            'email_id'          => $emailId,
            'original_filename' => $f['original_filename'],
            'stored_filename'   => $f['stored_filename'],
            'mime_type'         => $f['mime_type'],
            'size_bytes'        => (int) $f['size_bytes'],
            'created_at'        => $now,
        ], $files);

        db_connect()->table('email_attachments')->insertBatch($rows);
    }

    /** @return list<array<string, mixed>> */
    public function listFor(int $emailId): array
    {
        return db_connect()->table('email_attachments')
            ->where('email_id', $emailId)
            ->orderBy('id', 'ASC')
            ->get()->getResultArray();
    }

    /** @return array<string, mixed>|null */
    public function find(int $emailId, int $attachmentId): ?array
    {
        return db_connect()->table('email_attachments')
            ->where('id', $attachmentId)
            ->where('email_id', $emailId)
            ->get()->getRowArray();
    }

    public function deleteOne(int $attachmentId): void
    {
        $db = db_connect();
        $row = $db->table('email_attachments')->where('id', $attachmentId)->get()->getRowArray();
        if (! $row) {
            return;
        }

        $db->table('email_attachments')->where('id', $attachmentId)->delete();
        $this->unlinkIfOrphaned($row['stored_filename']);
    }

    public function deleteAllFor(int $emailId): void
    {
        $db = db_connect();
        $rows = $db->table('email_attachments')->where('email_id', $emailId)->get()->getResultArray();
        if ($rows === []) {
            return;
        }

        $db->table('email_attachments')->where('email_id', $emailId)->delete();
        foreach ($rows as $row) {
            $this->unlinkIfOrphaned($row['stored_filename']);
        }
    }

    /**
     * Copies every file staged for a bulk batch onto one newly-created email
     * row, all pointing at the same on-disk file -- see spec §6.3.
     */
    public function copyBatchAttachments(int $batchId, int $emailId): void
    {
        $files = db_connect()->table('email_batch_attachments')->where('batch_id', $batchId)->get()->getResultArray();
        $this->persist($emailId, $files);
    }

    /**
     * A bulk send shares one stored file across every recipient's
     * email_attachments row, so deleting one row must not delete the file
     * out from under the others -- only unlink once nothing references it.
     */
    private function unlinkIfOrphaned(string $storedFilename): void
    {
        $stillReferenced = db_connect()->table('email_attachments')
            ->where('stored_filename', $storedFilename)
            ->countAllResults() > 0;

        if (! $stillReferenced) {
            @unlink(WRITEPATH . 'uploads/' . $storedFilename);
        }
    }
}
