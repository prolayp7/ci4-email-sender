<?php

namespace App\Services;

class ActivityLogger
{
    public static function log(?int $userId, string $action, string $description, string $ip = ''): void
    {
        $description = preg_replace('/(password|pass|secret|token)\s*[:=]\s*\S+/i', '$1=[redacted]', $description);

        db_connect()->table('activity_logs')->insert([
            'user_id'     => $userId,
            'action'      => $action,
            'description' => $description,
            'ip_address'  => $ip ?: (service('request')->getIPAddress() ?? ''),
            'created_at'  => date('Y-m-d H:i:s'),
        ]);
    }
}
