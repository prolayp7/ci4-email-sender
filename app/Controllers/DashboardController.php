<?php

namespace App\Controllers;

use CodeIgniter\Controller;

class DashboardController extends Controller
{
    private const TREND_DAYS = 14;
    private const RECENT_BATCH_LIMIT = 10;

    public function index()
    {
        $db = db_connect();

        $totalRecipients = $db->table('recipients')->countAllResults();
        $sent = $db->table('emails')->where('status', 'sent')->countAllResults();
        $failed = $db->table('emails')->where('status', 'failed')->countAllResults();
        $bounced = $db->table('emails')->where('status', 'bounced')->countAllResults();
        $pending = $db->table('emails')->where('status', 'pending')->countAllResults();
        $totalEmails = $sent + $failed + $bounced + $pending;
        $successRate = $totalEmails > 0 ? round(($sent / $totalEmails) * 100, 1) : 0;

        // "Opened"/"Clicked" count unique emails that had at least one such
        // event, not raw pixel/link hits (a recipient re-opening the same
        // email shouldn't inflate the number).
        $opened = (int) ($db->table('email_events')->where('type', 'open')
            ->select('COUNT(DISTINCT email_id) AS c')->get()->getRow('c') ?? 0);
        $clicked = (int) ($db->table('email_events')->where('type', 'click')
            ->select('COUNT(DISTINCT email_id) AS c')->get()->getRow('c') ?? 0);

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
            'bounced'         => $bounced,
            'opened'          => $opened,
            'clicked'         => $clicked,
            'successRate'     => $successRate,
            'recent'          => $recent,
            'trend'           => $this->buildTrend($db),
            'campaigns'       => $this->buildCampaignPerformance($db),
        ]);
    }

    /** @return array{labels: list<string>, sent: list<int>, opened: list<int>, clicked: list<int>} */
    private function buildTrend($db): array
    {
        $since = date('Y-m-d', strtotime('-' . (self::TREND_DAYS - 1) . ' days'));

        $sentByDay = array_column(
            $db->table('emails')->select('DATE(sent_at) AS d, COUNT(*) AS c')
                ->where('status', 'sent')->where('sent_at >=', $since . ' 00:00:00')
                ->groupBy('d')->get()->getResultArray(),
            'c', 'd'
        );
        $openedByDay = array_column(
            $db->table('email_events')->select('DATE(created_at) AS d, COUNT(DISTINCT email_id) AS c')
                ->where('type', 'open')->where('created_at >=', $since . ' 00:00:00')
                ->groupBy('d')->get()->getResultArray(),
            'c', 'd'
        );
        $clickedByDay = array_column(
            $db->table('email_events')->select('DATE(created_at) AS d, COUNT(DISTINCT email_id) AS c')
                ->where('type', 'click')->where('created_at >=', $since . ' 00:00:00')
                ->groupBy('d')->get()->getResultArray(),
            'c', 'd'
        );

        $labels = $sentSeries = $openedSeries = $clickedSeries = [];
        for ($i = self::TREND_DAYS - 1; $i >= 0; $i--) {
            $d = date('Y-m-d', strtotime("-{$i} days"));
            $labels[] = date('M j', strtotime($d));
            $sentSeries[] = (int) ($sentByDay[$d] ?? 0);
            $openedSeries[] = (int) ($openedByDay[$d] ?? 0);
            $clickedSeries[] = (int) ($clickedByDay[$d] ?? 0);
        }

        return ['labels' => $labels, 'sent' => $sentSeries, 'opened' => $openedSeries, 'clicked' => $clickedSeries];
    }

    /** @return list<array<string,mixed>> */
    private function buildCampaignPerformance($db): array
    {
        $batches = $db->table('email_batches')->orderBy('created_at', 'DESC')->limit(self::RECENT_BATCH_LIMIT)->get()->getResultArray();
        if ($batches === []) {
            return [];
        }

        $batchIds = array_column($batches, 'id');
        $sentByBatch = array_column(
            $db->table('emails')->select('batch_id, COUNT(*) AS c')
                ->whereIn('batch_id', $batchIds)->where('status', 'sent')
                ->groupBy('batch_id')->get()->getResultArray(),
            'c', 'batch_id'
        );
        $openedByBatch = array_column(
            $db->table('email_events ee')->select('e.batch_id AS batch_id, COUNT(DISTINCT ee.email_id) AS c')
                ->join('emails e', 'e.id = ee.email_id')->whereIn('e.batch_id', $batchIds)->where('ee.type', 'open')
                ->groupBy('e.batch_id')->get()->getResultArray(),
            'c', 'batch_id'
        );
        $clickedByBatch = array_column(
            $db->table('email_events ee')->select('e.batch_id AS batch_id, COUNT(DISTINCT ee.email_id) AS c')
                ->join('emails e', 'e.id = ee.email_id')->whereIn('e.batch_id', $batchIds)->where('ee.type', 'click')
                ->groupBy('e.batch_id')->get()->getResultArray(),
            'c', 'batch_id'
        );

        foreach ($batches as &$batch) {
            $batch['sent_count'] = (int) ($sentByBatch[$batch['id']] ?? 0);
            $batch['opened_count'] = (int) ($openedByBatch[$batch['id']] ?? 0);
            $batch['clicked_count'] = (int) ($clickedByBatch[$batch['id']] ?? 0);
        }

        return $batches;
    }
}
