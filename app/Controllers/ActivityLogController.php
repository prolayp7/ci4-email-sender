<?php

namespace App\Controllers;

use CodeIgniter\Controller;

class ActivityLogController extends Controller
{
    private const PER_PAGE = 50;

    public function index()
    {
        $db = db_connect();
        $total = $db->table('activity_logs')->countAllResults();

        $page = max(1, (int) $this->request->getGet('page'));
        $logs = $db->table('activity_logs al')
            ->select('al.*, u.name AS user_name')
            ->join('users u', 'u.id = al.user_id', 'left')
            ->orderBy('al.created_at', 'DESC')
            ->get(self::PER_PAGE, ($page - 1) * self::PER_PAGE)
            ->getResultArray();

        $pager = service('pager');
        $pager->makeLinks($page, self::PER_PAGE, $total, 'default_full');

        return view('activity/index', [
            'title' => 'Activity Log',
            'logs'  => $logs,
            'pager' => $pager,
        ]);
    }
}
