<?php

namespace Tests\Services;

use App\Services\EmailTrackingService;
use CodeIgniter\Test\CIUnitTestCase;

final class EmailTrackingServiceTest extends CIUnitTestCase
{
    public function testGenerateTokenIsUniqueAndHexEncoded(): void
    {
        $service = new EmailTrackingService();
        $a = $service->generateToken();
        $b = $service->generateToken();

        $this->assertMatchesRegularExpression('/^[a-f0-9]{40}$/', $a);
        $this->assertNotSame($a, $b);
    }

    public function testInstrumentRewritesLinksThroughTheClickTracker(): void
    {
        $html = '<p>Hi</p><a href="https://example.com/offer">Shop now</a>';

        $result = (new EmailTrackingService())->instrument($html, 'abc123');

        $this->assertStringContainsString('/t/c/abc123?u=' . urlencode('https://example.com/offer'), $result);
        $this->assertStringContainsString('Shop now', $result);
    }

    public function testInstrumentAppendsATrackingPixel(): void
    {
        $result = (new EmailTrackingService())->instrument('<p>Hi</p>', 'abc123');

        $this->assertStringContainsString('/t/o/abc123.gif', $result);
        $this->assertStringContainsString('width="1" height="1"', $result);
    }

    public function testInstrumentLeavesAnchorMailtoAndFragmentLinksAlone(): void
    {
        $html = '<a href="mailto:me@example.com">Email</a><a href="#section">Jump</a>';

        $result = (new EmailTrackingService())->instrument($html, 'abc123');

        $this->assertStringContainsString('href="mailto:me@example.com"', $result);
        $this->assertStringContainsString('href="#section"', $result);
        $this->assertStringNotContainsString('/t/c/', $result);
    }

    public function testInstrumentReturnsEmptyStringUnchanged(): void
    {
        $this->assertSame('', (new EmailTrackingService())->instrument('', 'abc123'));
    }
}
