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

<?php
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
        default                              => ['bi-activity', 'slate'],
    };
};
?>
<div class="row g-3 g-xl-4">
    <div class="col-lg-7">
        <div class="card orchid-card h-100">
            <div class="card-body">
                <h6 class="mb-3">Recent Activity</h6>
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
                                    &middot; <?= esc($item['ip_address']) ?>
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

<?= $this->endSection() ?>
