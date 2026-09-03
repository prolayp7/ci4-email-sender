<?php

namespace App\Controllers;

use App\Services\AuthService;
use CodeIgniter\Controller;

class AuthController extends Controller
{
    public function showLogin()
    {
        if (session()->get('isLoggedIn')) {
            return redirect()->to('/dashboard');
        }
        return view('auth/login');
    }

    public function login()
    {
        $rules = [
            'email'    => 'required|valid_email',
            'password' => 'required|min_length[8]',
        ];

        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('error', 'Please enter a valid email and password.');
        }

        $service = new AuthService();
        $result = $service->attempt(
            $this->request->getPost('email'),
            $this->request->getPost('password'),
            $this->request->getIPAddress()
        );

        if (! $result['success']) {
            $message = $result['reason'] === 'rate_limited'
                ? 'Too many login attempts. Please wait a minute and try again.'
                : 'Invalid email or password.';

            log_message('notice', 'Failed login attempt for {email} from {ip}', [
                'email' => $this->request->getPost('email'),
                'ip'    => $this->request->getIPAddress(),
            ]);

            return redirect()->back()->withInput()->with('error', $message);
        }

        return redirect()->to('/dashboard');
    }

    public function logout()
    {
        (new AuthService())->logout();
        return redirect()->to('/login');
    }
}
