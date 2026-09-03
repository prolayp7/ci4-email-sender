<?php

namespace App\Controllers;

use App\Models\EmailTemplateModel;
use App\Models\RecipientModel;
use App\Services\ActivityLogger;
use App\Services\EmailSenderService;
use CodeIgniter\Controller;

class ComposeController extends Controller
{
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
            return $this->validationError();
        }

        $recipientId = (int) $this->request->getPost('recipient_id');
        if (! $this->activeRecipientExists($recipientId)) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Please select an active recipient.',
            ]);
        }

        $templateId = $this->validTemplateId();
        if ($templateId === false) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'The selected template is not available.',
            ]);
        }

        $result = (new EmailSenderService())->send(
            $recipientId,
            (string) $this->request->getPost('subject'),
            (string) $this->request->getPost('body_html'),
            $templateId,
            (int) session()->get('user_id')
        );

        ActivityLogger::log(
            (int) session()->get('user_id'),
            $result['status'] === 'sent' ? 'email.sent' : 'email.failed',
            'Email ' . $result['status'] . ' (recipient #' . $recipientId . ')'
        );

        return $this->response->setJSON([
            'success' => $result['status'] === 'sent',
            'message' => $result['status'] === 'sent'
                ? 'Email sent successfully.'
                : ($result['error'] ?? 'Email delivery failed.'),
        ]);
    }

    public function saveDraft()
    {
        if (! $this->validateMessage()) {
            return $this->validationError();
        }

        $recipientId = (int) $this->request->getPost('recipient_id');
        if (! $this->activeRecipientExists($recipientId)) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Please select an active recipient.',
            ]);
        }

        $templateId = $this->validTemplateId();
        if ($templateId === false) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'The selected template is not available.',
            ]);
        }

        db_connect()->table('emails')->insert([
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

        ActivityLogger::log(
            (int) session()->get('user_id'),
            'email.draft_saved',
            'Draft saved (recipient #' . $recipientId . ')'
        );

        return $this->response->setJSON(['success' => true, 'message' => 'Draft saved.']);
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

    private function validationError()
    {
        return $this->response->setJSON([
            'success' => false,
            'message' => 'Please fill in all required fields.',
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
}
