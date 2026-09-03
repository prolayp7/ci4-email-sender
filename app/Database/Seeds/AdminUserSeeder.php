<?php

namespace App\Database\Seeds;

use CodeIgniter\CLI\CLI;
use CodeIgniter\Database\Seeder;

class AdminUserSeeder extends Seeder
{
    public function run()
    {
        $email = getenv('ADMIN_SEED_EMAIL') ?: 'admin@example.com';
        $password = getenv('ADMIN_SEED_PASSWORD') ?: bin2hex(random_bytes(8));

        $this->db->table('users')->insert([
            'name'          => 'Administrator',
            'email'         => $email,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'role'          => 'owner',
            'status'        => 'active',
            'created_at'    => date('Y-m-d H:i:s'),
            'updated_at'    => date('Y-m-d H:i:s'),
        ]);

        if (! getenv('ADMIN_SEED_PASSWORD')) {
            CLI::write("Seeded admin {$email} with generated password: {$password}", 'yellow');
        }
    }
}
