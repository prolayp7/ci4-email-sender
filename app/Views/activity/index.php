<?= $this->extend('layout/main') ?>

<?= $this->section('styles') ?>
<link href="/assets/css/pages/emails.css?v=<?= @filemtime(FCPATH . 'assets/css/pages/emails.css') ?>" rel="stylesheet">
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<div class="d-flex flex-wrap align-items-start justify-content-between gap-3 mb-4">
    <div>
        <h1 class="emails-page-title">Activity Log</h1>
        <p class="emails-page-sub">Full audit trail, including unmasked IP addresses — owner/admin only</p>
    </div>
    <a href="/dashboard" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Dashboard</a>
</div>

<div class="emails-card">
    <?php if (empty($logs)) : ?>
        <div class="emails-empty">
            <div class="emails-empty__illus"><i class="bi bi-clock-history"></i></div>
            <h6>No activity yet</h6>
            <p>Actions taken in this app will appear here.</p>
        </div>
    <?php else : ?>
        <div class="emails-table-wrap">
            <table class="table table-hover emails-table align-middle mb-0" aria-label="Activity log">
                <thead>
                    <tr>
                        <th>Time</th>
                        <th>User</th>
                        <th>Action</th>
                        <th>Description</th>
                        <th>IP address</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($logs as $log) : ?>
                    <tr>
                        <td class="emails-meta text-nowrap"><?= esc($log['created_at']) ?></td>
                        <td class="emails-meta"><?= esc($log['user_name'] ?? 'System') ?></td>
                        <td class="emails-meta"><code><?= esc($log['action']) ?></code></td>
                        <td class="emails-meta"><?= esc($log['description']) ?></td>
                        <td class="emails-meta text-nowrap"><?= esc($log['ip_address'] ?: '—') ?></td>
                    </tr>
                <?php endforeach ?>
                </tbody>
            </table>
        </div>
        <?php if ($pager->getPageCount() > 1) : ?>
            <div class="emails-footer"><?= $pager->links() ?></div>
        <?php endif ?>
    <?php endif ?>
</div>

<?= $this->endSection() ?>
