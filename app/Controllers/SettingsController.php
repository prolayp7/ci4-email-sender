<?php

namespace App\Controllers;

use App\Services\ActivityLogger;
use CodeIgniter\Controller;

class SettingsController extends Controller
{
    public function index()
    {
        $user = db_connect()->table('users')
            ->select('id, name, email, role, status, last_login_at')
            ->where('id', (int) session()->get('user_id'))
            ->get()->getRowArray();

        if (! $user) {
            session()->destroy();
            return redirect()->to('/login');
        }

        return view('settings/index', ['title' => 'Settings', 'user' => $user]);
    }

    public function updatePassword()
    {
        $rules = [
            'current_password' => 'required',
            'new_password'     => 'required|min_length[8]|max_length[255]',
            'confirm_password' => 'required|matches[new_password]',
        ];

        if (! $this->validate($rules)) {
            return redirect()->to('/settings')->with('error', 'Enter matching passwords with at least 8 characters.');
        }

        $db = db_connect();
        $user = $db->table('users')->where('id', (int) session()->get('user_id'))->get()->getRowArray();
        if (! $user || ! password_verify((string) $this->request->getPost('current_password'), $user['password_hash'])) {
            return redirect()->to('/settings')->with('error', 'Current password is incorrect.');
        }

        $newPassword = (string) $this->request->getPost('new_password');
        if (password_verify($newPassword, $user['password_hash'])) {
            return redirect()->to('/settings')->with('error', 'Choose a password different from your current password.');
        }

        $db->table('users')->where('id', $user['id'])->update([
            'password_hash' => password_hash($newPassword, PASSWORD_DEFAULT),
            'updated_at'    => date('Y-m-d H:i:s'),
        ]);

        ActivityLogger::log((int) $user['id'], 'user.password_changed', 'Password changed');
        return redirect()->to('/settings')->with('success', 'Password updated successfully.');
    }
}
