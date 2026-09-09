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

        $this->autoLinkifyBareUrls($doc);

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

    /**
     * The compose editor (Quill) only turns text into a real <a> when the
     * sender explicitly uses its link toolbar button -- a bare URL typed or
     * pasted as plain text stays inert, unclickable, and therefore never
     * generates a click event no matter how correct the rest of this
     * pipeline is. Wraps any such bare http(s) URL in a real <a> first, so
     * every link in the email is clickable and trackable regardless of how
     * it was authored.
     */
    private function autoLinkifyBareUrls(DOMDocument $doc): void
    {
        $xpath = new \DOMXPath($doc);
        $textNodes = iterator_to_array($xpath->query('//text()[not(ancestor::a)]'));

        foreach ($textNodes as $textNode) {
            $text = $textNode->nodeValue;
            if (! preg_match('/https?:\/\/[^\s<>"]+/', $text)) {
                continue;
            }

            preg_match_all('/https?:\/\/[^\s<>"]+/', $text, $matches, PREG_OFFSET_CAPTURE);

            $fragment = $doc->createDocumentFragment();
            $lastEnd = 0;
            foreach ($matches[0] as [$url, $offset]) {
                // Strip trailing punctuation that's almost certainly sentence
                // formatting, not part of the URL (e.g. "...example.com.").
                $trimmed = rtrim($url, '.,;:!?)]}\'"');
                $url = substr($url, 0, strlen($trimmed));

                $fragment->appendChild($doc->createTextNode(substr($text, $lastEnd, $offset - $lastEnd)));
                $anchor = $doc->createElement('a');
                $anchor->setAttribute('href', $url);
                $anchor->appendChild($doc->createTextNode($url));
                $fragment->appendChild($anchor);
                $lastEnd = $offset + strlen($url);
            }
            $fragment->appendChild($doc->createTextNode(substr($text, $lastEnd)));

            $textNode->parentNode->replaceChild($fragment, $textNode);
        }
    }
}
