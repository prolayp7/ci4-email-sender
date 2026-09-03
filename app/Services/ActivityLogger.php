<?php

namespace App\Services;

class ActivityLogger
{
    public static function log(?int $userId, string $action, string $description, string $ip = ''): void
    {
        // Defense in depth: callers should never pass secrets into $description,
        // but redact anything secret-shaped anyway (quoted values with spaces
        // included, not just single "word" tokens).
        $description = preg_replace(
            '/(password|passwd|pass|secret|token|api[_-]?key)\s*[:=]\s*("[^"]*"|\'[^\']*\'|\S+)/i',
            '$1=[redacted]',
            $description
        );

        db_connect()->table('activity_logs')->insert([
            'user_id'     => $userId,
            'action'      => $action,
            'description' => $description,
            'ip_address'  => $ip ?: (service('request')->getIPAddress() ?? ''),
            'created_at'  => date('Y-m-d H:i:s'),
        ]);
    }
}
