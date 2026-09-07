<?php

namespace Tests\Services;

use App\Services\TemplateRenderer;
use CodeIgniter\Test\CIUnitTestCase;

final class TemplateRendererTest extends CIUnitTestCase
{
    public function testReplacesKnownPlaceholders(): void
    {
        $out = (new TemplateRenderer())->render('Hi {{name}}, from {{company}} ({{email}}) in {{location}}', [
            'name' => 'Jane', 'email' => 'jane@example.com', 'company' => 'Acme', 'location' => 'NYC',
        ]);

        $this->assertSame('Hi Jane, from Acme (jane@example.com) in NYC', $out);
    }

    public function testLeavesUnknownTokensUntouched(): void
    {
        $out = (new TemplateRenderer())->render('Hi {{name}}, code {{php_eval}}', ['name' => 'Jane', 'email' => '', 'company' => '']);
        $this->assertStringContainsString('{{php_eval}}', $out);
    }
}
