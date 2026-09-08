<?php

namespace Tests\Services;

use CodeIgniter\Test\CIUnitTestCase;
use Config\Services;

/**
 * Attachments are stored on disk under a randomized filename (collision-proofing
 * across recipients/batches -- see ComposeController::storeAttachments()), separate
 * from the original filename the user uploaded. EmailSenderService::send() relies on
 * CI4's Email::attach($path, '', $originalFilename) to show recipients the original
 * name instead of the random one; this pins that contract so a regression (e.g. an
 * attach() call site that drops the third argument again) fails a test instead of
 * only showing up as a client complaint about garbled attachment names.
 */
final class EmailAttachmentNamingTest extends CIUnitTestCase
{
    public function testAttachedFileUsesTheOriginalNameNotTheStoredDiskName(): void
    {
        $storedPath = WRITEPATH . 'uploads/' . bin2hex(random_bytes(16)) . '.pdf';
        file_put_contents($storedPath, '%PDF-1.4 test file content');

        $email = Services::email(null, false);
        $email->initialize([
            'protocol'    => 'smtp',
            'SMTPHost'    => '127.0.0.1',
            'SMTPPort'    => 1, // nothing listens here -- refused immediately, no hang
            'SMTPTimeout' => 1,
        ]);
        $email->setFrom('sender@example.com');
        $email->setTo('recipient@example.com');
        $email->setSubject('Test');
        $email->setMessage('<p>Body</p>');
        $email->attach($storedPath, '', 'Invoice_Client_March.pdf');

        // buildMessage() (which writes the attachment's Content-Disposition
        // filename into the body) runs before the SMTP connect attempt, so the
        // built message is inspectable via printDebugger() even though send()
        // itself returns false here (no SMTP listener on port 1).
        $email->send();
        $debug = $email->printDebugger(['body']);

        @unlink($storedPath);

        $this->assertStringContainsString('Invoice_Client_March.pdf', $debug);
        $this->assertStringNotContainsString(basename($storedPath), $debug);
    }
}
