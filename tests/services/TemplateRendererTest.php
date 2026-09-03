<?php

namespace Tests\Services;

use App\Services\TemplateRenderer;
use CodeIgniter\Test\CIUnitTestCase;

final class TemplateRendererTest extends CIUnitTestCase
{
    public function testReplacesKnownPlaceholders(): void
    {
        $out = (new TemplateRenderer())->render('Hi {{name}}, from {{company}} ({{email}})', [
            'name' => 'Jane', 'email' => 'jane@example.com', 'company' => 'Acme',
        ]);

        $this->assertSame('Hi Jane, from Acme (jane@example.com)', $out);
    }

    public function testLeavesUnknownTokensUntouched(): void
    {
        $out = (new TemplateRenderer())->render('Hi {{name}}, code {{php_eval}}', ['name' => 'Jane', 'email' => '', 'company' => '']);
        $this->assertStringContainsString('{{php_eval}}', $out);
    }
}
