<?php

namespace App\Controllers;

use App\Models\EmailTemplateModel;
use App\Models\RecipientModel;
use App\Services\ActivityLogger;
use App\Services\AttachmentService;
use App\Services\EmailSenderService;
use App\Services\SmtpConfigService;
use App\Services\TemplateRenderer;
use CodeIgniter\Controller;
use Config\Services as CoreServices;

class ComposeController extends Controller
{
    private const ALLOWED_ATTACHMENT_EXTENSIONS = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'csv', 'png', 'jpg', 'jpeg', 'gif', 'zip'];
    private const MAX_ATTACHMENT_SIZE_BYTES     = 10 * 1024 * 1024;
    private const MAX_ATTACHMENTS               = 5;

    private string $attachmentError = '';

    public function index()
    {
        return view('compose/index', [
            'title'          => 'Compose Email',
            'recipients'     => (new RecipientModel())->where('status', 'active')->orderBy('name')->findAll(),
            'templates'      => (new EmailTemplateModel())->where('status', 'active')->orderBy('name')->findAll(),
            'locationGroups' => $this->locationGroups(),
        ]);
    }

    /**
     * One row per distinct location, so bulk mode can offer "send to this
     * group" as a shortcut instead of searching/selecting recipients one at
     * a time. "Sendable" (active) is a subset of "total" (any status) --
     * the group option only ever needs to select the sendable ones, since
     * that's exactly what populates the recipient <select> already.
     *
     * @return list<array{location: string, total: int, sendable: int}>
     */
    private function locationGroups(): array
    {
        return db_connect()->table('recipients')
            ->select("location, COUNT(*) AS total, SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) AS sendable")
            ->where('location IS NOT NULL')
            ->where('location !=', '')
            ->groupBy('location')
            ->orderBy('location', 'asc')
            ->get()->getResultArray();
    }

    public function send()
    {
        if (! $this->validateMessage()) {
            return $this->jsonResponse(false, 'Please fill in all required fields.');
        }

        $recipientId = (int) $this->request->getPost('recipient_id');
        if (! $this->activeRecipientExists($recipientId)) {
            return $this->jsonResponse(false, 'Please select an active recipient.');
        }

        $templateId = $this->validTemplateId();
        if ($templateId === false) {
            return $this->jsonResponse(false, 'The selected template is not available.');
        }

        $stored = $this->storeAttachments();
        if ($stored === false) {
            return $this->jsonResponse(false, $this->attachmentError);
        }

        $result = (new EmailSenderService())->send(
            $recipientId,
            (string) $this->request->getPost('subject'),
            (string) $this->request->getPost('body_html'),
            $templateId,
            (int) session()->get('user_id'),
            array_map(static fn (array $f) => ['path' => $f['path'], 'original_filename' => $f['original_filename']], $stored)
        );

        if ($result['email_id'] > 0 && $stored !== []) {
            (new AttachmentService())->persist($result['email_id'], $stored);
        }

        ActivityLogger::log(
            (int) session()->get('user_id'),
            $result['status'] === 'sent' ? 'email.sent' : 'email.failed',
            'Email ' . $result['status'] . ' (recipient #' . $recipientId . ')'
        );

        return $this->jsonResponse(
            $result['status'] === 'sent',
            $result['status'] === 'sent' ? 'Email sent successfully.' : ($result['error'] ?? 'Email delivery failed.')
        );
    }

    public function saveDraft()
    {
        if (! $this->validateMessage()) {
            return $this->jsonResponse(false, 'Please fill in all required fields.');
        }

        $recipientId = (int) $this->request->getPost('recipient_id');
        if (! $this->activeRecipientExists($recipientId)) {
            return $this->jsonResponse(false, 'Please select an active recipient.');
        }

        $templateId = $this->validTemplateId();
        if ($templateId === false) {
            return $this->jsonResponse(false, 'The selected template is not available.');
        }

        $stored = $this->storeAttachments();
        if ($stored === false) {
            return $this->jsonResponse(false, $this->attachmentError);
        }

        $db = db_connect();
        $db->table('emails')->insert([
            'recipient_id'  => $recipientId,
            'template_id'   => $templateId,
            'user_id'       => (int) session()->get('user_id'),
            'subject'       => (string) $this->request->getPost('subject'),
            'body_html'     => (string) $this->request->getPost('body_html'),
            'status'        => 'draft',
            'attempt_count' => 0,
            'created_at'    => date('Y-m-d H:i:s'),
            'updated_at'    => date('Y-m-d H:i:s'),
        ]);
        $emailId = (int) $db->insertID();

        if ($stored !== []) {
            (new AttachmentService())->persist($emailId, $stored);
        }

        ActivityLogger::log(
            (int) session()->get('user_id'),
            'email.draft_saved',
            'Draft saved (recipient #' . $recipientId . ')'
        );

        return $this->jsonResponse(true, 'Draft saved.');
    }

    public function edit($id)
    {
        $email = db_connect()->table('emails')
            ->where('id', (int) $id)
            ->where('status', 'draft')
            ->where('deleted_at', null)
            ->get()->getRowArray();

        if (! $email) {
            return redirect()->to('/emails/drafts')->with('error', 'Draft not found.');
        }

        return view('compose/index', [
            'title'            => 'Edit Draft',
            'recipients'       => (new RecipientModel())->where('status', 'active')->orderBy('name')->findAll(),
            'templates'        => (new EmailTemplateModel())->where('status', 'active')->orderBy('name')->findAll(),
            'draft'            => $email,
            'draftAttachments' => (new AttachmentService())->listFor((int) $id),
        ]);
    }

    public function update($id)
    {
        $email = db_connect()->table('emails')
            ->where('id', (int) $id)
            ->where('status', 'draft')
            ->where('deleted_at', null)
            ->get()->getRowArray();

        if (! $email) {
            return $this->jsonResponse(false, 'This draft is no longer available.');
        }

        if (! $this->validateMessage()) {
            return $this->jsonResponse(false, 'Please fill in all required fields.');
        }

        $recipientId = (int) $this->request->getPost('recipient_id');
        if (! $this->activeRecipientExists($recipientId)) {
            return $this->jsonResponse(false, 'Please select an active recipient.');
        }

        $templateId = $this->validTemplateId();
        if ($templateId === false) {
            return $this->jsonResponse(false, 'The selected template is not available.');
        }

        $stored = $this->storeAttachments();
        if ($stored === false) {
            return $this->jsonResponse(false, $this->attachmentError);
        }

        $attachmentService = new AttachmentService();
        $removeIds = array_filter(array_map('intval', $this->request->getPost('remove_attachments') ?? []));
        $validRemoveIds = array_filter($removeIds, static fn (int $removeId) => $attachmentService->find((int) $id, $removeId) !== null);

        // storeAttachments() only knows about files uploaded in THIS request --
        // it has no notion of a draft's existing attachments, so the 5-file cap
        // has to be enforced here too, against the combined total, or a draft
        // could be topped up past the cap a few files at a time across saves.
        $existingCount = count($attachmentService->listFor((int) $id));
        $totalAfterUpdate = $existingCount - count($validRemoveIds) + count($stored);
        if ($totalAfterUpdate > self::MAX_ATTACHMENTS) {
            return $this->jsonResponse(false, 'You can attach at most ' . self::MAX_ATTACHMENTS . ' files per draft.');
        }

        foreach ($validRemoveIds as $removeId) {
            $attachmentService->deleteOne($removeId);
        }

        db_connect()->table('emails')->where('id', (int) $id)->update([
            'recipient_id' => $recipientId,
            'template_id'  => $templateId,
            'subject'      => (string) $this->request->getPost('subject'),
            'body_html'    => (string) $this->request->getPost('body_html'),
            'updated_at'   => date('Y-m-d H:i:s'),
        ]);

        if ($stored !== []) {
            $attachmentService->persist((int) $id, $stored);
        }

        ActivityLogger::log(
            (int) session()->get('user_id'),
            'email.draft_updated',
            'Draft #' . (int) $id . ' updated'
        );

        return $this->jsonResponse(true, 'Draft updated.');
    }

    public function bulkStart()
    {
        $rules = [
            'subject'   => 'required|max_length[255]',
            'body_html' => 'required',
        ];
        if (! $this->validate($rules)) {
            return $this->jsonResponse(false, 'Please fill in the subject and message.');
        }

        $templateId = $this->validTemplateId();
        if ($templateId === false) {
            return $this->jsonResponse(false, 'The selected template is not available.');
        }

        $stored = $this->storeAttachments();
        if ($stored === false) {
            return $this->jsonResponse(false, $this->attachmentError);
        }

        // Subject and body are needed on every subsequent send-one call, so
        // they're stored on the batch itself rather than round-tripped from
        // the client on each request.
        $db = db_connect();
        $db->table('email_batches')->insert([
            'subject'         => (string) $this->request->getPost('subject'),
            'body_html'       => (string) $this->request->getPost('body_html'),
            'template_id'     => $templateId,
            'user_id'         => (int) session()->get('user_id'),
            'recipient_count' => (int) $this->request->getPost('recipient_count'),
            'created_at'      => date('Y-m-d H:i:s'),
        ]);
        $batchId = (int) $db->insertID();
        $this->persistBatchAttachments($batchId, $stored);

        return $this->jsonResponse(true, 'Batch started.', ['batch_id' => $batchId]);
    }

    /**
     * Schedules a bulk send for later instead of sending it now: the full
     * recipient list is persisted up front (unlike an immediate bulk send,
     * whose recipient list only ever lives in the browser's send-one loop),
     * since a scheduled batch is worked off later by a CLI command with no
     * browser involved. See app/Commands/ProcessScheduledCampaigns.php.
     */
    public function bulkSchedule()
    {
        $rules = ['subject' => 'required|max_length[255]', 'body_html' => 'required'];
        if (! $this->validate($rules)) {
            return $this->jsonResponse(false, 'Please fill in the subject and message.');
        }

        $scheduledAtRaw = (string) $this->request->getPost('scheduled_at');
        $scheduledAtTs = strtotime($scheduledAtRaw);
        if ($scheduledAtTs === false || $scheduledAtTs <= time()) {
            return $this->jsonResponse(false, 'Choose a send time in the future.');
        }

        $templateId = $this->validTemplateId();
        if ($templateId === false) {
            return $this->jsonResponse(false, 'The selected template is not available.');
        }

        $recipientIds = array_unique(array_filter(array_map('intval', $this->request->getPost('recipient_ids') ?? [])));
        $activeRecipientIds = $recipientIds === [] ? [] : (new RecipientModel())
            ->whereIn('id', $recipientIds)->where('status', 'active')->findColumn('id');
        $activeRecipientIds = $activeRecipientIds ?: [];
        if ($activeRecipientIds === []) {
            return $this->jsonResponse(false, 'Select at least one active recipient.');
        }

        $throttleRaw = $this->request->getPost('throttle_per_hour');
        $throttle = ($throttleRaw === null || $throttleRaw === '') ? null : max(1, (int) $throttleRaw);

        $stored = $this->storeAttachments();
        if ($stored === false) {
            return $this->jsonResponse(false, $this->attachmentError);
        }

        $db = db_connect();
        $db->table('email_batches')->insert([
            'subject'           => (string) $this->request->getPost('subject'),
            'body_html'         => (string) $this->request->getPost('body_html'),
            'template_id'       => $templateId,
            'user_id'           => (int) session()->get('user_id'),
            'recipient_count'   => count($activeRecipientIds),
            'status'            => 'scheduled',
            'scheduled_at'      => date('Y-m-d H:i:s', $scheduledAtTs),
            'throttle_per_hour' => $throttle,
            'created_at'        => date('Y-m-d H:i:s'),
        ]);
        $batchId = (int) $db->insertID();
        $this->persistBatchAttachments($batchId, $stored);

        $now = date('Y-m-d H:i:s');
        $db->table('email_batch_recipients')->insertBatch(array_map(
            static fn (int $recipientId) => ['batch_id' => $batchId, 'recipient_id' => $recipientId, 'status' => 'pending', 'created_at' => $now],
            $activeRecipientIds
        ));

        ActivityLogger::log(
            (int) session()->get('user_id'),
            'email.batch_scheduled',
            'Scheduled "' . (string) $this->request->getPost('subject') . '" for ' . count($activeRecipientIds) . ' recipient(s) at ' . date('Y-m-d H:i:s', $scheduledAtTs)
        );

        return $this->jsonResponse(true, 'Campaign scheduled.', ['batch_id' => $batchId]);
    }

    /**
     * Sends the current subject/body to one address for a quick real-world
     * check, rendered against sample placeholder data (matching
     * TemplateController::preview()) -- not tied to any recipient or batch,
     * so nothing is written to emails/recipients for it.
     */
    public function sendTest()
    {
        $rules = ['subject' => 'required|max_length[255]', 'body_html' => 'required', 'test_email' => 'required|valid_email'];
        if (! $this->validate($rules)) {
            return $this->jsonResponse(false, 'Enter a subject, message, and a valid test email address.');
        }

        $templateId = $this->validTemplateId();
        if ($templateId === false) {
            return $this->jsonResponse(false, 'The selected template is not available.');
        }

        $config = (new SmtpConfigService())->getActive();
        if (! $config) {
            return $this->jsonResponse(false, 'SMTP is not configured. Please configure SMTP settings first.');
        }

        $testEmail = (string) $this->request->getPost('test_email');
        $sample = ['name' => 'Sample Name', 'email' => $testEmail, 'company' => 'Sample Co', 'location' => 'Sample City'];
        $renderer = new TemplateRenderer();

        $email = CoreServices::email(null, false);
        $email->initialize([
            'protocol'   => 'smtp',
            'SMTPHost'   => $config['host'],
            'SMTPPort'   => $config['port'],
            'SMTPCrypto' => $config['encryption'],
            'SMTPUser'   => $config['username'],
            'SMTPPass'   => $config['password'],
            'mailType'   => 'html',
        ]);
        $email->setFrom($config['from_email'], $config['from_name']);
        $email->setTo($testEmail);
        $email->setSubject('[TEST] ' . $renderer->render((string) $this->request->getPost('subject'), $sample));
        $email->setMessage($renderer->render((string) $this->request->getPost('body_html'), $sample));

        if (! $email->send()) {
            log_message('error', 'Test send failed: {debug}', ['debug' => $email->printDebugger(['headers'])]);
            return $this->jsonResponse(false, 'Unable to send the test email. Check your SMTP configuration.');
        }

        return $this->jsonResponse(true, 'Test email sent to ' . $testEmail . '.');
    }

    private function persistBatchAttachments(int $batchId, array $stored): void
    {
        if ($stored === []) {
            return;
        }

        $rows = array_map(static fn (array $f) => [
            'batch_id'          => $batchId,
            'original_filename' => $f['original_filename'],
            'stored_filename'   => $f['stored_filename'],
            'mime_type'         => $f['mime_type'],
            'size_bytes'        => $f['size_bytes'],
            'created_at'        => date('Y-m-d H:i:s'),
        ], $stored);
        db_connect()->table('email_batch_attachments')->insertBatch($rows);
    }

    public function bulkSendOne()
    {
        $batchId = (int) $this->request->getPost('batch_id');
        $recipientId = (int) $this->request->getPost('recipient_id');

        $batch = db_connect()->table('email_batches')->where('id', $batchId)->get()->getRowArray();
        if (! $batch) {
            return $this->jsonResponse(false, 'Unknown batch.', ['recipient_id' => $recipientId]);
        }

        if (! $this->activeRecipientExists($recipientId)) {
            return $this->jsonResponse(false, 'Recipient is not active.', ['recipient_id' => $recipientId]);
        }

        $result = (new EmailSenderService())->send(
            $recipientId,
            $batch['subject'],
            $batch['body_html'],
            $batch['template_id'] !== null ? (int) $batch['template_id'] : null,
            (int) session()->get('user_id'),
            $this->batchAttachments($batchId)
        );

        if ($result['email_id'] > 0) {
            db_connect()->table('emails')->where('id', $result['email_id'])->update(['batch_id' => $batchId]);
            (new AttachmentService())->copyBatchAttachments($batchId, $result['email_id']);
        }

        return $this->jsonResponse(
            $result['status'] === 'sent',
            $result['status'] === 'sent' ? 'Sent.' : ($result['error'] ?? 'Send failed.'),
            ['recipient_id' => $recipientId, 'status' => $result['status']]
        );
    }

    /**
     * A batch's staged attachments, in the shape EmailSenderService::send()
     * expects -- so bulkSendOne() actually attaches them to the outgoing
     * message (copyBatchAttachments() below only records the
     * email_attachments DB rows used for the History/detail download links;
     * it doesn't affect what gets attached to the sent email itself).
     *
     * @return list<array{path:string, original_filename:string}>
     */
    private function batchAttachments(int $batchId): array
    {
        $batchFiles = db_connect()->table('email_batch_attachments')->where('batch_id', $batchId)->get()->getResultArray();

        return array_map(static fn (array $f) => [
            'path'              => WRITEPATH . 'uploads/' . $f['stored_filename'],
            'original_filename' => $f['original_filename'],
        ], $batchFiles);
    }

    public function bulkLogSummary()
    {
        $batchId = (int) $this->request->getPost('batch_id');
        $sent = (int) $this->request->getPost('sent');
        $failed = (int) $this->request->getPost('failed');

        $batch = db_connect()->table('email_batches')->where('id', $batchId)->get()->getRowArray();
        $subject = $batch['subject'] ?? 'Unknown';

        ActivityLogger::log(
            (int) session()->get('user_id'),
            'email.batch_sent',
            'Bulk send: ' . $subject . ' — ' . $sent . '/' . ($sent + $failed) . ' sent'
        );

        return $this->response->setJSON(['success' => true]);
    }

    /**
     * Every AJAX response on this page carries the current CSRF hash: CI4
     * regenerates the token after each request (Config\Security::$regenerate),
     * but Compose can submit repeatedly (Send, then Send again, or Save Draft)
     * without a page reload, so the hidden field from page-load would go
     * stale after the first submission and every one after it would 403.
     * The client-side JS reads this back and updates the hidden field.
     */
    private function jsonResponse(bool $success, string $message, array $extra = [])
    {
        return $this->response->setJSON(array_merge([
            'success'   => $success,
            'message'   => $message,
            'csrf_hash' => csrf_hash(),
        ], $extra));
    }

    private function validateMessage(): bool
    {
        return $this->validate([
            'recipient_id' => 'required|is_natural_no_zero',
            'subject'      => 'required|max_length[255]',
            'body_html'    => 'required',
            'template_id'  => 'permit_empty|is_natural_no_zero',
        ]);
    }

    private function activeRecipientExists(int $recipientId): bool
    {
        return (new RecipientModel())
            ->where('id', $recipientId)
            ->where('status', 'active')
            ->countAllResults() === 1;
    }

    /** @return int|null|false */
    private function validTemplateId()
    {
        $value = $this->request->getPost('template_id');
        if ($value === null || $value === '') {
            return null;
        }

        $templateId = (int) $value;
        $exists = (new EmailTemplateModel())
            ->where('id', $templateId)
            ->where('status', 'active')
            ->countAllResults() === 1;

        return $exists ? $templateId : false;
    }

    /**
     * Validates and moves any uploaded attachments into writable/uploads under
     * a random filename. Returns metadata for each stored file (the caller
     * persists it via AttachmentService once it knows the email's id), or
     * false (with $this->attachmentError set) if any file fails validation --
     * the whole send is rejected rather than silently dropping a bad attachment.
     *
     * @return list<array{original_filename:string, stored_filename:string, mime_type:string, size_bytes:int, path:string}>|false
     */
    private function storeAttachments()
    {
        $files = $this->request->getFileMultiple('attachments') ?? [];
        if (count($files) > self::MAX_ATTACHMENTS) {
            $this->attachmentError = 'You can attach at most ' . self::MAX_ATTACHMENTS . ' files.';
            return false;
        }

        $stored = [];
        foreach ($files as $file) {
            if ($file === null || $file->getError() === UPLOAD_ERR_NO_FILE) {
                continue;
            }

            if (! $file->isValid()) {
                $this->attachmentError = 'One of the attached files could not be uploaded.';
                return false;
            }

            if ($file->getSize() > self::MAX_ATTACHMENT_SIZE_BYTES) {
                $this->attachmentError = 'Each attachment must be smaller than 10MB.';
                return false;
            }

            $extension = strtolower($file->getExtension());
            if (! in_array($extension, self::ALLOWED_ATTACHMENT_EXTENSIONS, true)) {
                $this->attachmentError = 'Attachment type not allowed: .' . $extension;
                return false;
            }

            $originalName = $file->getClientName();
            $mimeType     = $file->getClientMimeType();
            $size         = $file->getSize();
            $newName      = $file->getRandomName();
            $file->move(WRITEPATH . 'uploads', $newName);

            $stored[] = [
                'original_filename' => $originalName,
                'stored_filename'   => $newName,
                'mime_type'         => $mimeType,
                'size_bytes'        => $size,
                'path'              => WRITEPATH . 'uploads/' . $newName,
            ];
        }

        return $stored;
    }
}
