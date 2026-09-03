<?php

namespace App\Services;

class TemplateRenderer
{
    public function render(string $body, array $recipient): string
    {
        $replacements = [
            '{{name}}'    => $recipient['name'] ?? '',
            '{{email}}'   => $recipient['email'] ?? '',
            '{{company}}' => $recipient['company'] ?? '',
        ];

        return strtr($body, $replacements);
    }
}
