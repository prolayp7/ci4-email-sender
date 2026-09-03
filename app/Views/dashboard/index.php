<?= $this->extend('layout/main') ?>
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

<div class="row g-3 g-xl-4">
    <div class="col-lg-7">
        <div class="card orchid-card h-100">
            <div class="card-body">
                <h6 class="mb-3">Recent Activity</h6>
                <?php if (empty($recent)) : ?>
                    <p class="text-body-secondary small mb-0">No activity yet.</p>
                <?php else : ?>
                    <ul class="list-unstyled mb-0">
                        <?php foreach ($recent as $item) : ?>
                            <li class="d-flex justify-content-between align-items-start py-2 border-bottom small">
                                <span><?= esc($item['description']) ?></span>
                                <span class="text-body-secondary flex-shrink-0 ms-3"><?= esc($item['created_at']) ?></span>
                            </li>
                        <?php endforeach ?>
                    </ul>
                <?php endif ?>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card orchid-card h-100">
            <div class="card-body d-grid gap-2">
                <h6 class="mb-1">Quick Actions</h6>
                <a href="/recipients/create" class="btn btn-outline-primary text-start"><i class="bi bi-person-plus me-2"></i>Add Recipient</a>
                <a href="/recipients" class="btn btn-outline-primary text-start"><i class="bi bi-upload me-2"></i>Import Recipients</a>
                <a href="/compose" class="btn btn-outline-primary text-start"><i class="bi bi-send me-2"></i>Compose Email</a>
                <a href="/templates/create" class="btn btn-outline-primary text-start"><i class="bi bi-file-earmark-plus me-2"></i>Create Template</a>
                <a href="/smtp" class="btn btn-outline-primary text-start"><i class="bi bi-server me-2"></i>SMTP Settings</a>
            </div>
        </div>
    </div>
</div>

<?= $this->endSection() ?>
