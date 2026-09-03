<?php

namespace App\Services;

use CodeIgniter\Throttle\Throttler;

class AuthService
{
    public function attempt(string $email, string $password, string $ip): array
    {
        /** @var Throttler $throttler */
        $throttler = service('throttler');
        $throttleKey = 'login-' . md5($ip . '-' . strtolower($email));

        if ($throttler->check($throttleKey, 5, MINUTE) === false) {
            return ['success' => false, 'user' => null, 'reason' => 'rate_limited'];
        }

        $user = db_connect()->table('users')
            ->where('email', $email)
            ->get()
            ->getRowArray();

        if (! $user || ! password_verify($password, $user['password_hash'])) {
            return ['success' => false, 'user' => null, 'reason' => 'invalid_credentials'];
        }

        if ($user['status'] !== 'active') {
            return ['success' => false, 'user' => null, 'reason' => 'account_disabled'];
        }

        session()->regenerate(true);
        session()->set([
            'user_id'    => $user['id'],
            'user_email' => $user['email'],
            'user_name'  => $user['name'],
            'user_role'  => $user['role'],
            'isLoggedIn' => true,
        ]);

        db_connect()->table('users')->where('id', $user['id'])->update([
            'last_login_at' => date('Y-m-d H:i:s'),
        ]);

        ActivityLogger::log($user['id'], 'login', 'User logged in', $ip);

        unset($user['password_hash']);

        return ['success' => true, 'user' => $user, 'reason' => null];
    }

    public function logout(): void
    {
        ActivityLogger::log(session()->get('user_id'), 'logout', 'User logged out');
        session()->destroy();
    }
}
