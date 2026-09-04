<?php

namespace App\Controllers;

use App\Services\ActivityLogger;
use App\Services\AttachmentService;
use App\Services\EmailSenderService;
use CodeIgniter\Controller;
use CodeIgniter\Exceptions\PageNotFoundException;

class EmailController extends Controller
{
    private const STATUSES = ['sent', 'failed', 'pending', 'draft'];

    public function index()
    {
        // Fresh builders, unfiltered, so the stats strip always reflects the
        // whole table regardless of the status/recipient/date filters below.
        $stats = [];
        foreach (self::STATUSES as $s) {
            $stats[$s] = db_connect()->table('emails')->where('status', $s)->countAllResults();
        }

        $status = (string) $this->request->getGet('status');
        $recipient = trim((string) $this->request->getGet('recipient'));
        $date = (string) $this->request->getGet('date');
        $status = in_array($status, self::STATUSES, true) ? $status : '';
        $date = preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) === 1 ? $date : '';

        $sortable = ['recipient' => 'r.name', 'subject' => 'e.subject', 'status' => 'e.status', 'sent' => 'e.sent_at'];
        $sortKey = (string) $this->request->getGet('sort');
        $sort = array_key_exists($sortKey, $sortable) ? $sortKey : '';
        $dir = strtolower((string) $this->request->getGet('dir')) === 'asc' ? 'asc' : 'desc';

        $builder = db_connect()->table('emails e')
            ->select('e.*, r.name AS recipient_name, r.email AS recipient_email, u.name AS user_name')
            ->join('recipients r', 'r.id = e.recipient_id')
            ->join('users u', 'u.id = e.user_id');

        $this->applyFilters($builder, $status, $recipient, $date);
        $total = $builder->countAllResults(false);
        $perPage = 20;
        $page = max(1, (int) $this->request->getGet('page_emails'));
        $emails = $builder->orderBy($sort !== '' ? $sortable[$sort] : 'e.created_at', $dir)
            ->get($perPage, ($page - 1) * $perPage)
            ->getResultArray();

        $pager = service('pager');
        $pager->makeLinks($page, $perPage, $total, 'default_full', 0, 'emails');

        return view('emails/index', [
            'title'     => 'Email History',
            'emails'    => $emails,
            'pager'     => $pager,
            'status'    => $status,
            'recipient' => $recipient,
            'date'      => $date,
            'sort'      => $sort,
            'dir'       => $dir,
            'stats'     => $stats,
        ]);
    }

    public function show($id)
    {
        $email = db_connect()->table('emails e')
            ->select('e.*, r.name AS recipient_name, r.email AS recipient_email, u.name AS user_name')
            ->join('recipients r', 'r.id = e.recipient_id')
            ->join('users u', 'u.id = e.user_id')
            ->where('e.id', (int) $id)
            ->get()->getRowArray();

        if (! $email) {
            return redirect()->to('/emails')->with('error', 'Email record not found.');
        }

        return view('emails/detail', [
            'title'       => 'Email Detail',
            'breadcrumb'  => 'Email History / Detail',
            'email'       => $email,
            'attachments' => (new AttachmentService())->listFor((int) $id),
        ]);
    }

    public function retry($id)
    {
        $db = db_connect();
        $email = $db->table('emails')->where('id', (int) $id)->get()->getRowArray();

        if (! $email || $email['status'] !== 'failed') {
            return redirect()->to('/emails')->with('error', 'Only failed emails can be retried.');
        }

        $result = $this->resendEmailRow($email);

        ActivityLogger::log(
            (int) session()->get('user_id'),
            'email.retried',
            'Retried email #' . (int) $id . ', result: ' . $result['status']
        );

        $message = $result['status'] === 'sent'
            ? 'Email resent successfully.'
            : 'Retry failed: ' . ($result['error'] ?? 'Email delivery failed.');

        return redirect()->to('/emails')->with($result['status'] === 'sent' ? 'success' : 'error', $message);
    }

    public function sendDraft($id)
    {
        $db = db_connect();
        $email = $db->table('emails')->where('id', (int) $id)->get()->getRowArray();

        if (! $email || $email['status'] !== 'draft') {
            return redirect()->to('/emails')->with('error', 'Only drafts can be sent this way.');
        }

        $result = $this->resendEmailRow($email);

        ActivityLogger::log(
            (int) session()->get('user_id'),
            $result['status'] === 'sent' ? 'email.sent' : 'email.failed',
            'Sent draft email #' . (int) $id . ', result: ' . $result['status']
        );

        $message = $result['status'] === 'sent'
            ? 'Draft sent successfully.'
            : 'Send failed: ' . ($result['error'] ?? 'Email delivery failed.');

        return redirect()->to('/emails?status=draft')->with($result['status'] === 'sent' ? 'success' : 'error', $message);
    }

    /**
     * Shared by retry() and sendDraft(): both take an existing emails row
     * (a failed send or a draft), send it fresh via EmailSenderService (which
     * always inserts a brand-new row), then fold that new row's result back
     * onto the ORIGINAL row's id and delete the temporary new one -- so the
     * record a user has open (and any link to it) keeps pointing at the same
     * id instead of silently becoming stale.
     */
    private function resendEmailRow(array $email): array
    {
        $db = db_connect();
        $result = (new EmailSenderService())->send(
            (int) $email['recipient_id'],
            $email['subject'],
            $email['body_html'],
            $email['template_id'] === null ? null : (int) $email['template_id'],
            (int) session()->get('user_id')
        );

        if ($result['email_id'] > 0) {
            $newRecord = $db->table('emails')->where('id', $result['email_id'])->get()->getRowArray();
            if ($newRecord) {
                $db->transStart();
                $db->table('emails')->where('id', (int) $email['id'])->update([
                    'user_id'       => (int) session()->get('user_id'),
                    'status'        => $newRecord['status'],
                    'error_message' => $newRecord['error_message'],
                    'message_id'    => $newRecord['message_id'],
                    'attempt_count' => (int) $email['attempt_count'] + 1,
                    'sent_at'       => $newRecord['sent_at'],
                    'updated_at'    => date('Y-m-d H:i:s'),
                ]);
                $db->table('emails')->where('id', $result['email_id'])->delete();
                $db->transComplete();
            }
        } else {
            // Validation failures (for example, a recipient who unsubscribed
            // since the draft was written) do not create a second send record.
            // The attempt must still be visible on the original audit record.
            $db->table('emails')->where('id', (int) $email['id'])->update([
                'status'        => 'failed',
                'error_message' => $result['error'] ?? 'Email delivery failed.',
                'attempt_count' => (int) $email['attempt_count'] + 1,
                'updated_at'    => date('Y-m-d H:i:s'),
            ]);
        }

        return $result;
    }

    private function applyFilters($builder, string $status, string $recipient, string $date): void
    {
        if ($status !== '') {
            $builder->where('e.status', $status);
        } else {
            $builder->where('e.status !=', 'draft');
        }
        if ($recipient !== '') {
            $builder->groupStart()
                ->like('r.email', mb_substr($recipient, 0, 191))
                ->orLike('r.name', mb_substr($recipient, 0, 150))
                ->groupEnd();
        }
        if ($date !== '') {
            $builder->where('e.created_at >=', $date . ' 00:00:00')
                ->where('e.created_at <', date('Y-m-d 00:00:00', strtotime($date . ' +1 day')));
        }
    }

    public function attachment($emailId, $attachmentId)
    {
        $attachment = (new AttachmentService())->find((int) $emailId, (int) $attachmentId);
        if (! $attachment) {
            throw PageNotFoundException::forPageNotFound();
        }

        $path = WRITEPATH . 'uploads/' . $attachment['stored_filename'];
        if (! is_file($path)) {
            throw PageNotFoundException::forPageNotFound();
        }

        $fileContents = file_get_contents($path);
        // Escape filename per RFC 5987 (matches DownloadResponse::getContentDisposition())
        $filename = addslashes($attachment['original_filename']);
        return $this->response
            ->setHeader('Content-Disposition', 'attachment; filename="' . $filename . '"')
            ->setBody($fileContents);
    }
}
