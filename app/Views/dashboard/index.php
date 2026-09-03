<?= $this->extend('layout/main') ?>
<?= $this->section('content') ?>

<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card border-0 shadow-sm"><div class="card-body">
            <div class="text-muted small">Total Recipients</div>
            <div class="fs-3 fw-semibold"><?= esc((string) $totalRecipients) ?></div>
        </div></div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm"><div class="card-body">
            <div class="text-muted small">Emails Sent</div>
            <div class="fs-3 fw-semibold text-success"><?= esc((string) $sent) ?></div>
        </div></div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm"><div class="card-body">
            <div class="text-muted small">Emails Failed</div>
            <div class="fs-3 fw-semibold text-danger"><?= esc((string) $failed) ?></div>
        </div></div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm"><div class="card-body">
            <div class="text-muted small">Success Rate</div>
            <div class="fs-3 fw-semibold"><?= esc((string) $successRate) ?>%</div>
        </div></div>
    </div>
</div>

<div class="row g-3">
    <div class="col-md-7">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-medium">Recent Activity</div>
            <div class="list-group list-group-flush">
                <?php if (empty($recent)) : ?>
                    <div class="list-group-item text-muted">No activity yet.</div>
                <?php endif ?>
                <?php foreach ($recent as $item) : ?>
                    <div class="list-group-item small"><?= esc($item['description']) ?>
                        <span class="text-muted float-end"><?= esc($item['created_at']) ?></span>
                    </div>
                <?php endforeach ?>
            </div>
        </div>
    </div>
    <div class="col-md-5">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-medium">Quick Actions</div>
            <div class="card-body d-grid gap-2">
                <a href="/recipients/create" class="btn btn-outline-primary text-start">Add Recipient</a>
                <a href="/recipients" class="btn btn-outline-primary text-start">Import Recipients</a>
                <a href="/compose" class="btn btn-outline-primary text-start">Compose Email</a>
                <a href="/templates/create" class="btn btn-outline-primary text-start">Create Template</a>
                <a href="/smtp" class="btn btn-outline-primary text-start">SMTP Settings</a>
            </div>
        </div>
    </div>
</div>

<?= $this->endSection() ?>
