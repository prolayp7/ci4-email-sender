<?php

namespace Tests\Filters;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;

final class SecurityHeadersTest extends CIUnitTestCase
{
    use FeatureTestTrait;

    public function testSecurityHeadersArePresent(): void
    {
        $result = $this->get('/login');

        $result->assertHeader('X-Content-Type-Options', 'nosniff');
        $result->assertHeader('X-Frame-Options', 'DENY');
        $result->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $this->assertNotEmpty($result->response()->getHeaderLine('Content-Security-Policy'));
    }
}
