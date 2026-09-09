<?php

namespace App\Services;

use DOMDocument;

class EmailTrackingService
{
    public function generateToken(): string
    {
        return bin2hex(random_bytes(20));
    }

    /**
     * Rewrites every <a href="..."> in $html to route through the click
     * tracker, then appends a 1x1 tracking pixel. Uses DOMDocument rather
     * than a regex so attribute order/quoting/malformed markup doesn't
     * silently skip links.
     */
    public function instrument(string $html, string $token): string
    {
        if (trim($html) === '') {
            return $html;
        }

        $doc = new DOMDocument();
        libxml_use_internal_errors(true);
        // Wrapping in a UTF-8 meta tag keeps DOMDocument from mangling
        // multibyte text -- its HTML parser assumes Latin-1 without it.
        $doc->loadHTML('<?xml encoding="UTF-8"?><body>' . $html . '</body>', LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();

        $anchors = iterator_to_array($doc->getElementsByTagName('a'));
        foreach ($anchors as $anchor) {
            $href = $anchor->getAttribute('href');
            if ($href === '' || str_starts_with($href, '#') || str_starts_with($href, 'mailto:') || str_starts_with($href, 'tel:')) {
                continue;
            }
            $anchor->setAttribute('href', rtrim(base_url(), '/') . '/t/c/' . $token . '?u=' . urlencode($href));
        }

        $body = $doc->getElementsByTagName('body')->item(0);
        $rewritten = '';
        if ($body !== null) {
            foreach (iterator_to_array($body->childNodes) as $child) {
                $rewritten .= $doc->saveHTML($child);
            }
        } else {
            $rewritten = $html;
        }

        $pixel = '<img src="' . rtrim(base_url(), '/') . '/t/o/' . $token . '.gif" width="1" height="1" alt="" style="display:none;border:0;">';

        return $rewritten . $pixel;
    }
}
