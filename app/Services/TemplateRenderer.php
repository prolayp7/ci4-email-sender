<?php

namespace App\Services;

class TemplateRenderer
{
    public function render(string $body, array $recipient): string
    {
        $replacements = [
            '{{name}}'     => $recipient['name'] ?? '',
            '{{email}}'    => $recipient['email'] ?? '',
            '{{company}}'  => $recipient['company'] ?? '',
            '{{location}}' => $recipient['location'] ?? '',
        ];

        return strtr($body, $replacements);
    }
}
