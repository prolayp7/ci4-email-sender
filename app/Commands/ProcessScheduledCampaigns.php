<?php

namespace App\Commands;

use App\Services\AttachmentService;
use App\Services\EmailSenderService;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use CodeIgniter\Database\BaseConnection;

/**
 * Meant to run every minute via a real OS cron job (this app has no queue/
 * worker process of its own):
 *
 *   * * * * * cd /path/to/app && php spark campaigns:process >> writable/logs/campaigns.log 2>&1
 */
class ProcessScheduledCampaigns extends BaseCommand
{
    protected $group = 'Campaigns';
    protected $name = 'campaigns:process';
    protected $description = "Sends due scheduled campaigns, respecting each batch's throttle.";

    public function run(array $params)
    {
        $db = db_connect();
        $due = $db->table('email_batches')
            ->whereIn('status', ['scheduled', 'sending'])
            ->where('scheduled_at <=', date('Y-m-d H:i:s'))
            ->get()->getResultArray();

        if ($due === []) {
            CLI::write('No due campaigns.', 'yellow');
            return EXIT_SUCCESS;
        }

        foreach ($due as $batch) {
            $this->processBatch($db, $batch);
        }

        return EXIT_SUCCESS;
    }

    private function processBatch(BaseConnection $db, array $batch): void
    {
        $batchId = (int) $batch['id'];

        if ($batch['status'] === 'scheduled') {
            $db->table('email_batches')->where('id', $batchId)->update(['status' => 'sending']);
        }

        // ponytail: per-run cap derived from a 1-minute cron tick, not real
        // elapsed-time pacing -- throttles below 60/hour still send one per
        // tick (~60/hour effective floor). Fine for "don't blast them all at
        // once"; for accurate sub-60/hour rates, gate on time since this
        // batch's last send instead (needs a last_sent_at column).
        $perRun = $batch['throttle_per_hour'] !== null
            ? max(1, (int) floor((int) $batch['throttle_per_hour'] / 60))
            : null;

        $query = $db->table('email_batch_recipients')->where('batch_id', $batchId)->where('status', 'pending');
        if ($perRun !== null) {
            $query->limit($perRun);
        }
        $pending = $query->get()->getResultArray();

        if ($pending !== []) {
            $attachments = $this->batchAttachments($db, $batchId);
            $sender = new EmailSenderService();
            $attachmentService = new AttachmentService();

            foreach ($pending as $row) {
                $result = $sender->send(
                    (int) $row['recipient_id'],
                    $batch['subject'],
                    $batch['body_html'],
                    $batch['template_id'] !== null ? (int) $batch['template_id'] : null,
                    (int) $batch['user_id'],
                    $attachments
                );

                $db->table('email_batch_recipients')->where('id', $row['id'])
                    ->update(['status' => $result['status'] === 'sent' ? 'sent' : 'failed']);

                if ($result['email_id'] > 0) {
                    $db->table('emails')->where('id', $result['email_id'])->update(['batch_id' => $batchId]);
                    $attachmentService->copyBatchAttachments($batchId, $result['email_id']);
                }

                CLI::write(
                    'Batch #' . $batchId . ' -> recipient #' . $row['recipient_id'] . ': ' . $result['status'],
                    $result['status'] === 'sent' ? 'green' : 'red'
                );
            }
        }

        $remaining = $db->table('email_batch_recipients')->where('batch_id', $batchId)->where('status', 'pending')->countAllResults();
        if ($remaining === 0) {
            $db->table('email_batches')->where('id', $batchId)->update(['status' => 'completed']);
        }
    }

    /** @return list<array{path:string, original_filename:string}> */
    private function batchAttachments(BaseConnection $db, int $batchId): array
    {
        $files = $db->table('email_batch_attachments')->where('batch_id', $batchId)->get()->getResultArray();

        return array_map(static fn (array $f) => [
            'path'              => WRITEPATH . 'uploads/' . $f['stored_filename'],
            'original_filename' => $f['original_filename'],
        ], $files);
    }
}
