<?php

namespace App\Services;

class BounceClassifier
{
    /**
     * Best-effort read of whether an SMTP send failure was a permanent,
     * recipient-specific rejection (e.g. "550 5.1.1 No such user") rather
     * than a transient/connection/auth problem -- used to auto-flip a
     * recipient to 'bounced' so future campaigns skip them.
     *
     * This only sees failures that happen inside our own SMTP session (the
     * server rejecting RCPT TO/DATA while we're sending). It cannot detect a
     * real bounce that arrives later via the postmaster, since this app has
     * no inbound mailbox or provider bounce-webhook integration.
     *
     * ponytail: keyword/regex heuristic over CI4's debug transcript, not a
     * parsed SMTP response code -- upgrade to a provider webhook (SES,
     * Postmark, etc.) if real post-acceptance bounce tracking is needed.
     */
    public function isPermanentRecipientFailure(string $smtpDebug): bool
    {
        if (! preg_match('/\b(550|551|553|554)\b/', $smtpDebug)) {
            return false;
        }

        return (bool) preg_match(
            '/no such user|user unknown|mailbox (unavailable|not found)|does not exist|invalid recipient|recipient (rejected|not found|address rejected)|address rejected/i',
            $smtpDebug
        );
    }
}
