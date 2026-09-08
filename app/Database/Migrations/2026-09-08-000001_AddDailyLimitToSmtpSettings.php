<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddDailyLimitToSmtpSettings extends Migration
{
    public function up()
    {
        $this->forge->addColumn('smtp_settings', [
            'daily_limit' => ['type' => 'INT', 'unsigned' => true, 'null' => true, 'after' => 'from_name'],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('smtp_settings', 'daily_limit');
    }
}
