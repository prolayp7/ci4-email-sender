<?= $this->extend('layout/main') ?>
<?= $this->section('content') ?>
<form method="get" class="row g-2 align-items-end mb-4" aria-label="Filter email history">
    <div class="col-sm-6 col-lg-2">
        <label for="statusFilter" class="form-label small">Status</label>
        <select name="status" id="statusFilter" class="form-select">
            <option value="">All deliveries</option>
            <?php foreach (['sent' => 'Sent', 'failed' => 'Failed', 'pending' => 'Pending', 'draft' => 'Draft'] as $value => $label) : ?>
                <option value="<?= $value ?>" <?= $status === $value ? 'selected' : '' ?>><?= $label ?></option>
            <?php endforeach ?>
        </select>
    </div>
    <div class="col-sm-6 col-lg-4">
        <label for="recipientFilter" class="form-label small">Recipient</label>
        <input type="search" name="recipient" id="recipientFilter" class="form-control"
               value="<?= esc($recipient) ?>" placeholder="Name or email address">
    </div>
    <div class="col-sm-6 col-lg-3">
        <label for="dateFilter" class="form-label small">Created date</label>
        <input type="date" name="date" id="dateFilter" class="form-control" value="<?= esc($date) ?>">
    </div>
    <div class="col-sm-6 col-lg-3 d-flex gap-2">
        <button class="btn btn-primary">Apply filters</button>
        <a href="/emails" class="btn btn-outline-secondary">Reset</a>
    </div>
</form>

<div class="card overflow-hidden">
    <?php if ($emails === []) : ?>
        <div class="card-body text-center py-5">
            <i data-lucide="mail-search" width="32" height="32" class="text-secondary mb-3"></i>
            <h2 class="h6">No email records found</h2>
            <p class="text-muted mb-3">Try changing the filters or compose your first email.</p>
            <a href="/compose" class="btn btn-sm btn-primary">Compose email</a>
        </div>
    <?php else : ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light"><tr><th>Recipient</th><th>Subject</th><th>Status</th><th>Sent</th><th>User</th><th><span class="visually-hidden">Actions</span></th></tr></thead>
                <tbody>
                <?php foreach ($emails as $email) : ?>
                    <?php $badge = ['sent' => 'badge-soft-success', 'failed' => 'badge-soft-danger', 'pending' => 'badge-soft-warning', 'draft' => 'badge-soft-secondary'][$email['status']] ?? 'badge-soft-secondary'; ?>
                    <tr>
                        <td><span class="d-block fw-medium"><?= esc($email['recipient_name']) ?></span><span class="small text-muted"><?= esc($email['recipient_email']) ?></span></td>
                        <td><?= esc($email['subject']) ?></td>
                        <td><span class="badge <?= $badge ?> text-capitalize"><?= esc($email['status']) ?></span></td>
                        <td class="text-nowrap"><?= esc($email['sent_at'] ?? '—') ?></td>
                        <td><?= esc($email['user_name']) ?></td>
                        <td class="text-end text-nowrap">
                            <a href="/emails/<?= (int) $email['id'] ?>" class="btn btn-sm btn-outline-secondary">View</a>
                            <?php if ($email['status'] === 'failed') : ?>
                                <form method="post" action="/emails/retry/<?= (int) $email['id'] ?>" class="d-inline">
                                    <?= csrf_field() ?>
                                    <button class="btn btn-sm btn-outline-warning">Retry</button>
                                </form>
                            <?php endif ?>
                        </td>
                    </tr>
                <?php endforeach ?>
                </tbody>
            </table>
        </div>
        <?php if ($pager->getPageCount('emails') > 1) : ?>
            <div class="card-footer bg-white py-3"><?= $pager->links('emails') ?></div>
        <?php endif ?>
    <?php endif ?>
</div>
<?= $this->endSection() ?>
