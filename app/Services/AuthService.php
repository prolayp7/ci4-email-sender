<?php

namespace App\Services;

use CodeIgniter\Throttle\Throttler;

class AuthService
{
    /**
     * A precomputed bcrypt hash of an unguessable value, used so a lookup
     * against a non-existent email still pays the same password_verify()
     * cost as a real one — otherwise the response-time gap tells an
     * attacker which emails have accounts (timing-based user enumeration).
     */
    private const DUMMY_HASH = '$2y$12$e.qaxyhlyv11CzUFm96hzO/SBf0i4uGij3wkjOR.krV6MStxAHzVm';

    public function attempt(string $email, string $password, string $ip): array
    {
        /** @var Throttler $throttler */
        $throttler = service('throttler');

        // Two independent limits: a broad per-IP cap (stops credential
        // stuffing across many accounts from one source) and a tighter
        // per-account cap (stops distributed attempts against one account
        // from many IPs). Either tripping blocks the attempt.
        $ipOk = $throttler->check('login-ip-' . md5($ip), 20, MINUTE);
        $emailOk = $throttler->check('login-email-' . md5(strtolower($email)), 5, MINUTE);

        if ($ipOk === false || $emailOk === false) {
            return ['success' => false, 'user' => null, 'reason' => 'rate_limited'];
        }

        $user = db_connect()->table('users')
            ->where('email', $email)
            ->get()
            ->getRowArray();

        $hashToVerify = $user['password_hash'] ?? self::DUMMY_HASH;
        $passwordValid = password_verify($password, $hashToVerify);

        if (! $user || ! $passwordValid) {
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
