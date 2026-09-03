<?php

namespace App\Database\Seeds;

use CodeIgniter\CLI\CLI;
use CodeIgniter\Database\Seeder;

class EmailTemplateSeeder extends Seeder
{
    public function run()
    {
        $templates = [
            [
                'name'      => 'Welcome Email',
                'subject'   => 'Welcome, {{name}}!',
                'html_body' => "<p>Hi {{name}},</p>"
                    . "<p>Welcome aboard! We're glad to have {{company}} as part of our community.</p>"
                    . "<p>If you have any questions, just reply to this email — we're happy to help.</p>"
                    . "<p>Best regards,<br>The Team</p>",
                'text_body' => "Hi {{name}},\n\nWelcome aboard! We're glad to have {{company}} as part of our community.\n\nIf you have any questions, just reply to this email — we're happy to help.\n\nBest regards,\nThe Team",
            ],
            [
                'name'      => 'Follow-up',
                'subject'   => 'Following up, {{name}}',
                'html_body' => "<p>Hi {{name}},</p>"
                    . "<p>I wanted to follow up and see if you had a chance to look over our previous conversation.</p>"
                    . "<p>Let me know if there's anything I can help clarify for you or the team at {{company}}.</p>"
                    . "<p>Best,<br>The Team</p>",
                'text_body' => "Hi {{name}},\n\nI wanted to follow up and see if you had a chance to look over our previous conversation.\n\nLet me know if there's anything I can help clarify for you or the team at {{company}}.\n\nBest,\nThe Team",
            ],
            [
                'name'      => 'Product Announcement',
                'subject'   => "We've got something new for {{company}}",
                'html_body' => "<p>Hi {{name}},</p>"
                    . "<p>We just shipped something we think {{company}} will find useful. Reply to this email if you'd like a quick walkthrough.</p>"
                    . "<p>Thanks,<br>The Team</p>",
                'text_body' => "Hi {{name}},\n\nWe just shipped something we think {{company}} will find useful. Reply to this email if you'd like a quick walkthrough.\n\nThanks,\nThe Team",
            ],
        ];

        $inserted = 0;
        foreach ($templates as $template) {
            $exists = $this->db->table('email_templates')->where('name', $template['name'])->countAllResults() > 0;
            if ($exists) {
                continue;
            }

            $this->db->table('email_templates')->insert(array_merge($template, [
                'status'     => 'active',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]));
            $inserted++;
        }

        CLI::write("Seeded {$inserted} email template(s) (skipped any already present by name).", 'green');
    }
}
