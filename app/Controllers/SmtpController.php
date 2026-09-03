<?php

namespace App\Controllers;

use App\Services\ActivityLogger;
use App\Services\SmtpConfigService;
use CodeIgniter\Controller;
use Config\Services as CoreServices;

class SmtpController extends Controller
{
    public function index()
    {
        $service = new SmtpConfigService();
        return view('smtp/index', ['title' => 'SMTP Settings', 'config' => $service->getActiveMasked()]);
    }

    public function save()
    {
        $rules = [
            'label'      => 'required|max_length[100]',
            'host'       => 'required|max_length[191]',
            'port'       => 'required|integer',
            'encryption' => 'required|in_list[tls,ssl]',
            'username'   => 'required|max_length[191]',
            'password'   => 'required|max_length[255]',
            'from_email' => 'required|valid_email',
            'from_name'  => 'required|max_length[150]',
        ];

        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('error', 'Please correct the errors below.');
        }

        (new SmtpConfigService())->save($this->request->getPost([
            'label', 'host', 'port', 'encryption', 'username', 'password', 'from_email', 'from_name',
        ]));

        ActivityLogger::log(session()->get('user_id'), 'smtp.updated', 'SMTP configuration updated (host: ' . $this->request->getPost('host') . ')');
        session()->setFlashdata('success', 'SMTP configuration saved.');
        return redirect()->to('/smtp');
    }

    public function test()
    {
        $testEmail = $this->request->getPost('test_email');
        if (! $testEmail || ! filter_var($testEmail, FILTER_VALIDATE_EMAIL)) {
            return $this->response->setJSON(['success' => false, 'message' => 'Enter a valid email address to send the test to.']);
        }

        $config = (new SmtpConfigService())->getActive();
        if (! $config) {
            return $this->response->setJSON(['success' => false, 'message' => 'SMTP is not configured yet.']);
        }

        $email = CoreServices::email(null, false);
        $email->setFrom($config['from_email'], $config['from_name']);
        $email->setTo($testEmail);
        $email->setSubject('SMTP Test — Email Manager');
        $email->setMessage('This is a test email confirming your SMTP configuration works.');

        $email->initialize([
            'protocol'   => 'smtp',
            'SMTPHost'   => $config['host'],
            'SMTPPort'   => $config['port'],
            'SMTPCrypto' => $config['encryption'],
            'SMTPUser'   => $config['username'],
            'SMTPPass'   => $config['password'],
        ]);

        $sent = $email->send();

        if (! $sent) {
            log_message('error', 'SMTP test failed: {debug}', ['debug' => $email->printDebugger(['headers'])]);
            return $this->response->setJSON(['success' => false, 'message' => 'Unable to connect to the SMTP server. Please check your SMTP configuration.']);
        }

        return $this->response->setJSON(['success' => true, 'message' => 'Test email sent successfully.']);
    }
}
