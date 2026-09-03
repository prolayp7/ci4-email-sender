<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class SecurityHeaders implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        $response->setHeader('X-Content-Type-Options', 'nosniff');
        $response->setHeader('X-Frame-Options', 'DENY');
        $response->setHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->setHeader('Permissions-Policy', 'geolocation=(), microphone=(), camera=()');

        // A controller may set a stricter, page-specific CSP before this filter
        // runs (e.g. script-src 'none' on the template preview page, which
        // renders admin-authored HTML and must never execute script). Don't
        // clobber that with the app-wide default.
        if (! $response->hasHeader('Content-Security-Policy')) {
            $response->setHeader(
                'Content-Security-Policy',
                "default-src 'self'; "
                . "script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; "
                . "style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; "
                . "font-src 'self' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; "
                . "img-src 'self' data:; "
                . "frame-ancestors 'none'"
            );
        }

        return $response;
    }
}
