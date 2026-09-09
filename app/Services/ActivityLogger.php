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

        // Callers sometimes interpolate a user-authored subject/template body
        // straight into the description (e.g. a bulk-send summary) -- strip
        // any {{placeholder}} tokens so they never show up unsubstituted in
        // the activity feed, and enforce the activity_logs.description column
        // limit here once rather than at every call site.
        $description = preg_replace('/\{\{\s*[\w.]+\s*\}\}/', '', $description);
        $description = trim(preg_replace('/\s+/', ' ', $description));
        if (strlen($description) > 255) {
            $description = substr($description, 0, 252) . '...';
        }

        db_connect()->table('activity_logs')->insert([
            'user_id'     => $userId,
            'action'      => $action,
            'description' => $description,
            'ip_address'  => $ip ?: (service('request')->getIPAddress() ?? ''),
            'created_at'  => date('Y-m-d H:i:s'),
        ]);
    }
}
