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
        $wantsJson = $this->request->getHeaderLine('Accept') === 'application/json';

        $rules = [
            'email'    => 'required|valid_email',
            'password' => 'required|min_length[8]',
        ];

        if (! $this->validate($rules)) {
            if ($wantsJson) {
                return $this->jsonError($this->validator->getErrors(), null, 422);
            }
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

            if ($wantsJson) {
                return $this->jsonError(null, $message, 401);
            }
            return redirect()->back()->withInput()->with('error', $message);
        }

        if ($wantsJson) {
            return $this->response->setJSON(['success' => true, 'redirect' => '/dashboard']);
        }
        return redirect()->to('/dashboard');
    }

    /**
     * The CSRF token rotates on every request (Security::regenerate = true),
     * so a JSON error response must hand back the new token/hash or the
     * form's stale hidden field will fail CSRF on the next AJAX submit.
     */
    private function jsonError(?array $errors, ?string $message, int $status)
    {
        return $this->response->setStatusCode($status)->setJSON(array_filter([
            'success'  => false,
            'errors'   => $errors,
            'message'  => $message,
            'csrfName' => csrf_token(),
            'csrfHash' => csrf_hash(),
        ], static fn ($v) => $v !== null));
    }

    public function logout()
    {
        (new AuthService())->logout();
        return redirect()->to('/login');
    }
}
