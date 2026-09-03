<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddProviderToSmtpSettings extends Migration
{
    public function up()
    {
        $this->forge->addColumn('smtp_settings', [
            'provider' => [
                'type'       => 'ENUM',
                'constraint' => ['gmail', 'microsoft365', 'custom'],
                'default'    => 'custom',
                'null'       => false,
                'after'      => 'label',
            ],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('smtp_settings', 'provider');
    }
}
