<?php

namespace App\Services;

use Config\Services as CoreServices;

class EmailSenderService
{
    /**
     * @param list<array{path:string, original_filename:string}> $attachments Files already stored on
     *   disk under a randomized name (path) plus the name to show the recipient (original_filename);
     *   the caller owns cleanup (deleting the files) after this returns.
     */
    public function send(int $recipientId, string $subject, string $bodyHtml, ?int $templateId, int $userId, array $attachments = []): array
    {
        $db = db_connect();
        $recipient = $db->table('recipients')->where('id', $recipientId)->get()->getRowArray();

        if (! $recipient) {
            return ['email_id' => 0, 'status' => 'failed', 'error' => 'Recipient not found.'];
        }

        if ($recipient['status'] !== 'active') {
            return ['email_id' => 0, 'status' => 'failed', 'error' => 'Recipient has unsubscribed.'];
        }

        $config = (new SmtpConfigService())->getActive();

        $db->table('emails')->insert([
            'recipient_id'  => $recipientId,
            'template_id'   => $templateId,
            'user_id'       => $userId,
            'subject'       => $subject,
            'body_html'     => $bodyHtml,
            'status'        => 'pending',
            'attempt_count' => 1,
            'created_at'    => date('Y-m-d H:i:s'),
            'updated_at'    => date('Y-m-d H:i:s'),
        ]);
        $emailId = (int) $db->insertID();

        if (! $config) {
            $this->markFailed($emailId, 'SMTP is not configured. Please configure SMTP settings first.');
            return ['email_id' => $emailId, 'status' => 'failed', 'error' => 'SMTP is not configured. Please configure SMTP settings first.'];
        }

        $renderer = new TemplateRenderer();
        $rendered = $renderer->render($bodyHtml, $recipient);
        $renderedSubject = $renderer->render($subject, $recipient);

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
        $email->setTo($recipient['email']);
        $email->setSubject($renderedSubject);
        $email->setMessage($rendered);

        foreach ($attachments as $attachment) {
            // Files are stored on disk under a randomized name (collision-proofing,
            // see storeAttachments()) -- without the explicit $newname here, CI4's
            // Email::attach() falls back to basename($path), so the recipient would
            // see that random name instead of the file they were actually sent.
            $email->attach($attachment['path'], '', $attachment['original_filename']);
        }

        $sent = $email->send();

        if (! $sent) {
            $debug = $email->printDebugger(['headers']);
            log_message('error', 'Email send failed for recipient {id}: {debug}', ['id' => $recipientId, 'debug' => $debug]);
            $this->markFailed($emailId, 'Unable to connect to the SMTP server. Please check your SMTP configuration.');
            return ['email_id' => $emailId, 'status' => 'failed', 'error' => 'Unable to connect to the SMTP server. Please check your SMTP configuration.'];
        }

        $db->table('emails')->where('id', $emailId)->update([
            'status'     => 'sent',
            'sent_at'    => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return ['email_id' => $emailId, 'status' => 'sent', 'error' => null];
    }

    private function markFailed(int $emailId, string $message): void
    {
        if ($emailId === 0) {
            return;
        }
        db_connect()->table('emails')->where('id', $emailId)->update([
            'status'        => 'failed',
            'error_message' => $message,
            'updated_at'    => date('Y-m-d H:i:s'),
        ]);
    }
}
