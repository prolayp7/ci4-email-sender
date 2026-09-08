<?php

namespace Tests\Services;

use App\Services\BounceClassifier;
use CodeIgniter\Test\CIUnitTestCase;

final class BounceClassifierTest extends CIUnitTestCase
{
    public function testDetectsAPermanentRcptRejection(): void
    {
        $debug = '<pre>to: 550 5.1.1 The email account that you tried to reach does not exist.</pre>';

        $this->assertTrue((new BounceClassifier())->isPermanentRecipientFailure($debug));
    }

    public function testDetectsNoSuchUserWording(): void
    {
        $debug = '<pre>to: 550 No such user here</pre>';

        $this->assertTrue((new BounceClassifier())->isPermanentRecipientFailure($debug));
    }

    public function testIgnoresTransientConnectionFailures(): void
    {
        $debug = 'Failed to connect to smtp.example.com:587';

        $this->assertFalse((new BounceClassifier())->isPermanentRecipientFailure($debug));
    }

    public function testIgnoresAuthFailuresWithoutRecipientRejectionWording(): void
    {
        $debug = '<pre>auth: 535 5.7.8 Authentication failed</pre>';

        $this->assertFalse((new BounceClassifier())->isPermanentRecipientFailure($debug));
    }

    public function testIgnoresTemporary4xxDeferrals(): void
    {
        $debug = '<pre>to: 450 4.2.1 Mailbox temporarily unavailable, greylisted</pre>';

        $this->assertFalse((new BounceClassifier())->isPermanentRecipientFailure($debug));
    }
}
