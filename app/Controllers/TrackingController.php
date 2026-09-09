<?php

namespace App\Controllers;

use CodeIgniter\Controller;

/**
 * Unauthenticated by design -- recipients hit these routes from their own
 * mail client, not logged into this app. Keep both actions fast and silent:
 * a missing/invalid token should never surface an error to the recipient,
 * just do nothing observable.
 */
class TrackingController extends Controller
{
    // 1x1 transparent GIF, served for every open regardless of whether the
    // token matched -- so a recipient's mail client never sees a broken image.
    private const PIXEL_GIF = "GIF89a\x01\x00\x01\x00\x80\x00\x00\x00\x00\x00\xFF\xFF\xFF\x21\xF9\x04\x01\x00\x00\x00\x00\x2C\x00\x00\x00\x00\x01\x00\x01\x00\x00\x02\x02\x44\x01\x00\x3B";

    public function open($token)
    {
        $email = $this->findByToken($token);
        if ($email) {
            $this->logEvent((int) $email['id'], 'open', null);
        }

        return $this->response
            ->setHeader('Content-Type', 'image/gif')
            ->setHeader('Cache-Control', 'no-store, no-cache, must-revalidate')
            ->setBody(self::PIXEL_GIF);
    }

    public function click($token)
    {
        $target = (string) $this->request->getGet('u');
        $email = $this->findByToken($token);
        if ($email && $target !== '') {
            $this->logEvent((int) $email['id'], 'click', $target);
        }

        return redirect()->to($this->safeRedirectTarget($target));
    }

    private function findByToken(string $token): ?array
    {
        if ($token === '' || ! preg_match('/^[a-f0-9]{40}$/', $token)) {
            return null;
        }

        return db_connect()->table('emails')->where('tracking_token', $token)->get()->getRowArray();
    }

    private function logEvent(int $emailId, string $type, ?string $url): void
    {
        db_connect()->table('email_events')->insert([
            'email_id'   => $emailId,
            'type'       => $type,
            'url'        => $url,
            'ip_address' => $this->request->getIPAddress(),
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Only ever redirects to an absolute http(s) URL -- an unauthenticated
     * redirect endpoint that echoed back an arbitrary "u" value unchecked
     * would be an open redirect (e.g. javascript: or a scheme-relative URL
     * used for phishing). Falls back to the app home for anything else.
     */
    private function safeRedirectTarget(string $url): string
    {
        $parts = parse_url($url);
        if (! $parts || ! isset($parts['scheme'], $parts['host']) || ! in_array(strtolower($parts['scheme']), ['http', 'https'], true)) {
            return base_url();
        }

        return $url;
    }
}
