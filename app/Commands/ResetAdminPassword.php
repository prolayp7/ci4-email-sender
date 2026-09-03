<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class ResetAdminPassword extends BaseCommand
{
    protected $group = 'Users';
    protected $name = 'user:reset-password';
    protected $description = 'Generate a temporary password for an existing user.';
    protected $usage = 'user:reset-password <email>';
    protected $arguments = ['email' => 'Email address of the user to reset.'];

    public function run(array $params)
    {
        $email = $params[0] ?? '';
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            CLI::error('Provide a valid user email address.');
            return EXIT_ERROR;
        }

        $db = db_connect();
        $user = $db->table('users')->select('id')->where('email', $email)->get()->getRowArray();
        if (! $user) {
            CLI::error('No user exists with that email address.');
            return EXIT_ERROR;
        }

        $password = bin2hex(random_bytes(10));
        $db->table('users')->where('id', $user['id'])->update([
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'updated_at'    => date('Y-m-d H:i:s'),
        ]);

        CLI::write('Password reset successfully.', 'green');
        CLI::write('Email: ' . $email);
        CLI::write('Temporary password: ' . $password, 'yellow');
        CLI::write('Change this password immediately after signing in.');

        return EXIT_SUCCESS;
    }
}
