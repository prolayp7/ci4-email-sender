<?php

namespace App\Controllers;

use App\Models\EmailTemplateModel;
use App\Models\RecipientModel;
use App\Services\ActivityLogger;
use App\Services\AttachmentService;
use App\Services\EmailSenderService;
use CodeIgniter\Controller;

class ComposeController extends Controller
{
    private const ALLOWED_ATTACHMENT_EXTENSIONS = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'csv', 'png', 'jpg', 'jpeg', 'gif', 'zip'];
    private const MAX_ATTACHMENT_SIZE_BYTES     = 10 * 1024 * 1024;
    private const MAX_ATTACHMENTS               = 5;

    private string $attachmentError = '';

    public function index()
    {
        return view('compose/index', [
            'title'      => 'Compose Email',
            'recipients' => (new RecipientModel())->where('status', 'active')->orderBy('name')->findAll(),
            'templates'  => (new EmailTemplateModel())->where('status', 'active')->orderBy('name')->findAll(),
        ]);
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
            array_column($stored, 'path')
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

    /**
     * Every AJAX response on this page carries the current CSRF hash: CI4
     * regenerates the token after each request (Config\Security::$regenerate),
     * but Compose can submit repeatedly (Send, then Send again, or Save Draft)
     * without a page reload, so the hidden field from page-load would go
     * stale after the first submission and every one after it would 403.
     * The client-side JS reads this back and updates the hidden field.
     */
    private function jsonResponse(bool $success, string $message)
    {
        return $this->response->setJSON([
            'success'   => $success,
            'message'   => $message,
            'csrf_hash' => csrf_hash(),
        ]);
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
