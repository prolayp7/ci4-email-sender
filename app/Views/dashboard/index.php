<?= $this->extend('layout/main') ?>

<?= $this->section('styles') ?>
<link href="/assets/css/pages/dashboard.css?v=<?= @filemtime(FCPATH . 'assets/css/pages/dashboard.css') ?>" rel="stylesheet">
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<div class="row g-3 g-xl-4 mb-4">
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card orchid-card h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="text-body-secondary mb-1 small">Total Recipients</p>
                        <h3 class="mb-0 fw-bold"><?= esc((string) $totalRecipients) ?></h3>
                    </div>
                    <span class="orchid-stat-card__icon orchid-kpi-icon--indigo"><i class="bi bi-people"></i></span>
                </div>
            </div>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card orchid-card h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="text-body-secondary mb-1 small">Emails Sent</p>
                        <h3 class="mb-0 fw-bold"><?= esc((string) $sent) ?></h3>
                    </div>
                    <span class="orchid-stat-card__icon orchid-kpi-icon--emerald"><i class="bi bi-check-circle"></i></span>
                </div>
            </div>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card orchid-card h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="text-body-secondary mb-1 small">Emails Failed</p>
                        <h3 class="mb-0 fw-bold"><?= esc((string) $failed) ?></h3>
                    </div>
                    <span class="orchid-stat-card__icon orchid-kpi-icon--rose"><i class="bi bi-x-circle"></i></span>
                </div>
            </div>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card orchid-card h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="text-body-secondary mb-1 small">Success Rate</p>
                        <h3 class="mb-0 fw-bold"><?= esc((string) $successRate) ?>%</h3>
                    </div>
                    <span class="orchid-stat-card__icon orchid-kpi-icon--sky"><i class="bi bi-graph-up-arrow"></i></span>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 g-xl-4 mb-4">
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card orchid-card h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="text-body-secondary mb-1 small" title="Accepted by the receiving mail server at send time. This app has no post-delivery confirmation (e.g. a bounce webhook), so this matches Emails Sent.">Delivered</p>
                        <h3 class="mb-0 fw-bold"><?= esc((string) $sent) ?></h3>
                    </div>
                    <span class="orchid-stat-card__icon orchid-kpi-icon--emerald"><i class="bi bi-send-check"></i></span>
                </div>
            </div>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card orchid-card h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="text-body-secondary mb-1 small">Opened</p>
                        <h3 class="mb-0 fw-bold"><?= esc((string) $opened) ?></h3>
                    </div>
                    <span class="orchid-stat-card__icon orchid-kpi-icon--sky"><i class="bi bi-envelope-open"></i></span>
                </div>
            </div>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card orchid-card h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="text-body-secondary mb-1 small">Clicked</p>
                        <h3 class="mb-0 fw-bold"><?= esc((string) $clicked) ?></h3>
                    </div>
                    <span class="orchid-stat-card__icon orchid-kpi-icon--indigo"><i class="bi bi-cursor"></i></span>
                </div>
            </div>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card orchid-card h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="text-body-secondary mb-1 small">Bounced</p>
                        <h3 class="mb-0 fw-bold"><?= esc((string) $bounced) ?></h3>
                    </div>
                    <span class="orchid-stat-card__icon orchid-kpi-icon--amber"><i class="bi bi-exclamation-triangle"></i></span>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 g-xl-4 mb-4">
    <div class="col-lg-7">
        <div class="card orchid-card h-100">
            <div class="card-body">
                <h6 class="mb-3">Email Performance <span class="text-body-secondary small fw-normal">— last 14 days</span></h6>
                <div class="dashboard-chart-wrap">
                    <canvas id="performanceChart"></canvas>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card orchid-card h-100">
            <div class="card-body">
                <h6 class="mb-3">Campaign Performance</h6>
                <?php if (empty($campaigns)) : ?>
                    <p class="text-body-secondary small mb-0">No campaigns sent yet.</p>
                <?php else : ?>
                    <div class="table-responsive">
                        <table class="table table-sm table-hover align-middle mb-0">
                            <thead>
                                <tr class="text-body-secondary small">
                                    <th>Subject</th>
                                    <th>Sent</th>
                                    <th>Opened</th>
                                    <th>Clicked</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($campaigns as $c) : ?>
                                <tr>
                                    <td class="text-truncate" style="max-width:160px;"><?= esc($c['subject']) ?></td>
                                    <td><?= (int) $c['sent_count'] ?></td>
                                    <td><?= (int) $c['opened_count'] ?></td>
                                    <td><?= (int) $c['clicked_count'] ?></td>
                                    <td><span class="badge text-bg-light border"><?= esc(ucfirst($c['status'])) ?></span></td>
                                </tr>
                            <?php endforeach ?>
                            </tbody>
                        </table>
                    </div>
                    <p class="text-body-secondary small mb-0 mt-2">Reply tracking isn't shown here — it needs inbox access this app doesn't have.</p>
                <?php endif ?>
            </div>
        </div>
    </div>
</div>

<?php
// Partial mask for the compact dashboard feed -- full IPs are still stored
// and visible to anyone with DB access; this just keeps them off a screen
// that might be shared/screenshotted casually.
$maskIp = static function (string $ip): string {
    $parts = explode('.', $ip);
    return count($parts) === 4 ? $parts[0] . '.' . $parts[1] . '.•.•' : '•••';
};
// Icon + color per activity_logs.action, mirroring Orchid's login-history
// status treatment (colored badge per event kind) but mapped to the action
// types this app actually logs — no fake device/browser/MFA columns.
$activityStyle = static function (string $action): array {
    return match (true) {
        $action === 'login'                  => ['bi-box-arrow-in-right', 'emerald'],
        $action === 'logout'                 => ['bi-box-arrow-right', 'slate'],
        $action === 'user.password_changed'  => ['bi-key', 'amber'],
        $action === 'recipient.created'      => ['bi-person-plus', 'indigo'],
        $action === 'recipient.updated'      => ['bi-pencil', 'sky'],
        str_starts_with($action, 'recipient.') && str_contains($action, 'delet') => ['bi-person-dash', 'rose'],
        $action === 'recipients.imported'    => ['bi-upload', 'indigo'],
        $action === 'template.created'       => ['bi-file-earmark-plus', 'indigo'],
        $action === 'template.updated'       => ['bi-file-earmark-text', 'sky'],
        $action === 'template.deleted'       => ['bi-file-earmark-x', 'rose'],
        $action === 'smtp.updated'           => ['bi-server', 'sky'],
        $action === 'email.sent'             => ['bi-send-check', 'emerald'],
        $action === 'email.failed'           => ['bi-exclamation-triangle', 'rose'],
        $action === 'email.draft_saved'      => ['bi-file-earmark', 'slate'],
        $action === 'email.retried'          => ['bi-arrow-repeat', 'amber'],
        $action === 'email.deleted'          => ['bi-trash', 'rose'],
        $action === 'email.restored'         => ['bi-arrow-counterclockwise', 'emerald'],
        $action === 'email.destroyed'        => ['bi-trash-fill', 'rose'],
        $action === 'email.draft_updated'    => ['bi-pencil-square', 'slate'],
        $action === 'email.batch_sent'       => ['bi-send-plus', 'indigo'],
        $action === 'email.batch_scheduled'  => ['bi-calendar-event', 'indigo'],
        $action === 'email.batch_cancelled'  => ['bi-calendar-x', 'rose'],
        $action === 'recipient.bulk_deleted' => ['bi-person-dash', 'rose'],
        default                              => ['bi-activity', 'slate'],
    };
};
?>
<div class="row g-3 g-xl-4">
    <div class="col-lg-7">
        <div class="card orchid-card h-100">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <h6 class="mb-0">Recent Activity</h6>
                    <?php if (in_array(session()->get('user_role'), ['owner', 'admin'], true)) : ?>
                        <a href="/activity" class="small">View full audit log</a>
                    <?php endif ?>
                </div>
                <?php if (empty($recent)) : ?>
                    <p class="text-body-secondary small mb-0">No activity yet.</p>
                <?php else : ?>
                    <?php $last = array_key_last($recent); ?>
                    <?php foreach ($recent as $i => $item) : ?>
                        <?php [$icon, $color] = $activityStyle($item['action']); ?>
                        <div class="d-flex align-items-center gap-3 py-2 <?= $i === $last ? '' : 'border-bottom' ?>">
                            <span class="avatar avatar-sm orchid-kpi-icon--<?= $color ?> flex-shrink-0"><i class="bi <?= $icon ?>"></i></span>
                            <p class="mb-0 small text-truncate flex-grow-1 min-w-0"><?= esc($item['description']) ?></p>
                            <p class="mb-0 text-body-secondary text-end flex-shrink-0" style="font-size:.75rem;">
                                <?= esc(date('M j, g:i A', strtotime($item['created_at']))) ?>
                                <?php if (! empty($item['ip_address'])) : ?>
                                    &middot; <?= esc($maskIp($item['ip_address'])) ?>
                                <?php endif ?>
                            </p>
                        </div>
                    <?php endforeach ?>
                <?php endif ?>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card orchid-card h-100">
            <div class="card-body">
                <h6 class="mb-3">Quick Actions</h6>
                <div class="dashboard-quick-links">
                    <a href="/recipients/create"><i class="bi bi-person-plus"></i>Add Recipient</a>
                    <a href="/recipients"><i class="bi bi-upload"></i>Import Recipients</a>
                    <a href="/compose"><i class="bi bi-send"></i>Compose Email</a>
                    <a href="/templates/create"><i class="bi bi-file-earmark-plus"></i>Create Template</a>
                    <a href="/smtp"><i class="bi bi-server"></i>SMTP Settings</a>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.0/chart.umd.min.js"
        integrity="sha512-SIMGYRUjwY8+gKg7nn9EItdD8LCADSDfJNutF9TPrvEo86sQmFMh6MyralfIyhADlajSxqc7G0gs7+MwWF/ogQ=="
        crossorigin="anonymous"></script>
<script>
new Chart(document.getElementById('performanceChart'), {
    type: 'line',
    data: {
        labels: <?= json_encode($trend['labels']) ?>,
        datasets: [
            { label: 'Sent', data: <?= json_encode($trend['sent']) ?>, borderColor: '#4f46e5', backgroundColor: 'rgba(79,70,229,.08)', tension: .3, fill: true },
            { label: 'Opened', data: <?= json_encode($trend['opened']) ?>, borderColor: '#0284c7', backgroundColor: 'rgba(2,132,199,.08)', tension: .3, fill: true },
            { label: 'Clicked', data: <?= json_encode($trend['clicked']) ?>, borderColor: '#16a34a', backgroundColor: 'rgba(22,163,74,.08)', tension: .3, fill: true },
        ],
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        scales: { y: { beginAtZero: true, ticks: { precision: 0 } } },
    },
});
</script>

<?= $this->endSection() ?>
