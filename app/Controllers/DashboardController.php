<?php

namespace App\Controllers;

use CodeIgniter\Controller;

class DashboardController extends Controller
{
    public function index()
    {
        $db = db_connect();

        $totalRecipients = $db->table('recipients')->countAllResults();
        $sent = $db->table('emails')->where('status', 'sent')->countAllResults();
        $failed = $db->table('emails')->where('status', 'failed')->countAllResults();
        $pending = $db->table('emails')->where('status', 'pending')->countAllResults();
        $totalEmails = $sent + $failed + $pending;
        $successRate = $totalEmails > 0 ? round(($sent / $totalEmails) * 100, 1) : 0;

        $recent = $db->table('activity_logs')
            ->orderBy('created_at', 'DESC')
            ->limit(8)
            ->get()
            ->getResultArray();

        return view('dashboard/index', [
            'title'           => 'Dashboard',
            'totalRecipients' => $totalRecipients,
            'sent'            => $sent,
            'failed'          => $failed,
            'pending'         => $pending,
            'successRate'     => $successRate,
            'recent'          => $recent,
        ]);
    }
}
