<?= $this->extend('layout/main') ?>

<?= $this->section('styles') ?>
<link href="/assets/css/pages/emails.css?v=<?= @filemtime(FCPATH . 'assets/css/pages/emails.css') ?>" rel="stylesheet">
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<?php
$sortUrl = static function (string $field) use ($sort, $dir, $status, $recipient, $date) {
    $params = array_filter([
        'status'    => $status,
        'recipient' => $recipient,
        'date'      => $date,
        'sort'      => $field,
        'dir'       => ($sort === $field && $dir === 'asc') ? 'desc' : 'asc',
    ], static fn ($v) => $v !== null && $v !== '');
    return '/emails?' . http_build_query($params);
};
$hasFilters = $status || $recipient || $date;
$canManageEmails = in_array(session()->get('user_role'), ['owner', 'admin', 'operator'], true);
?>

<div class="d-flex flex-wrap align-items-start justify-content-between gap-3 mb-4">
    <div>
        <h1 class="emails-page-title">Email History</h1>
        <p class="emails-page-sub">Every email your account has sent, failed, or drafted</p>
    </div>
</div>

<?php if (session()->getFlashdata('success')) : ?>
    <div class="alert alert-success"><?= esc(session()->getFlashdata('success')) ?></div>
<?php endif ?>
<?php if (session()->getFlashdata('error')) : ?>
    <div class="alert alert-danger"><?= esc(session()->getFlashdata('error')) ?></div>
<?php endif ?>

<!-- Stats strip -->
<div class="row g-3 mb-3">
    <div class="col-6 col-xl-3">
        <div class="emails-stat emails-stat--green">
            <span class="emails-stat__icon"><i class="bi bi-check-circle-fill"></i></span>
            <div>
                <p class="emails-stat__label">Sent</p>
                <p class="emails-stat__value"><?= (int) $stats['sent'] ?></p>
            </div>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="emails-stat emails-stat--red">
            <span class="emails-stat__icon"><i class="bi bi-x-circle-fill"></i></span>
            <div>
                <p class="emails-stat__label">Failed</p>
                <p class="emails-stat__value"><?= (int) $stats['failed'] ?></p>
            </div>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="emails-stat emails-stat--amber">
            <span class="emails-stat__icon"><i class="bi bi-hourglass-split"></i></span>
            <div>
                <p class="emails-stat__label">Pending</p>
                <p class="emails-stat__value"><?= (int) $stats['pending'] ?></p>
            </div>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="emails-stat emails-stat--slate">
            <span class="emails-stat__icon"><i class="bi bi-file-earmark"></i></span>
            <div>
                <p class="emails-stat__label">Draft</p>
                <p class="emails-stat__value"><?= (int) $stats['draft'] ?></p>
            </div>
        </div>
    </div>
</div>

<!-- Toolbar -->
<form method="get" action="/emails" class="emails-toolbar" aria-label="Filter email history">
    <div class="emails-toolbar__field">
        <label for="statusFilter">Status</label>
        <select name="status" id="statusFilter" class="form-select form-select-sm">
            <option value="">All deliveries</option>
            <?php foreach (['sent' => 'Sent', 'failed' => 'Failed', 'pending' => 'Pending', 'draft' => 'Draft'] as $value => $label) : ?>
                <option value="<?= $value ?>" <?= $status === $value ? 'selected' : '' ?>><?= $label ?></option>
            <?php endforeach ?>
        </select>
    </div>
    <div class="emails-toolbar__field">
        <label for="recipientFilter">Recipient</label>
        <input type="search" name="recipient" id="recipientFilter" class="form-control form-control-sm"
               value="<?= esc($recipient) ?>" placeholder="Name or email address">
    </div>
    <div class="emails-toolbar__field">
        <label for="dateFilter">Created date</label>
        <input type="date" name="date" id="dateFilter" class="form-control form-control-sm" value="<?= esc($date) ?>">
    </div>
    <div class="emails-toolbar__actions">
        <button class="btn btn-primary btn-sm">Apply filters</button>
        <?php if ($hasFilters) : ?>
            <a href="/emails" class="btn btn-outline-secondary btn-sm">Reset</a>
        <?php endif ?>
    </div>
</form>

<div class="emails-card">
    <?php if ($emails === []) : ?>
        <div class="emails-empty">
            <div class="emails-empty__illus"><i class="bi bi-envelope"></i></div>
            <h6>No email records found</h6>
            <p><?= $hasFilters ? 'Try changing the filters or compose your first email.' : 'Compose your first email to see it here.' ?></p>
            <a href="/compose" class="btn btn-primary btn-sm">Compose email</a>
        </div>
    <?php else : ?>
        <div class="emails-table-wrap">
            <table class="table emails-table align-middle mb-0" aria-label="Email history list">
                <thead>
                    <tr>
                        <th class="emails-th-sort <?= $sort === 'recipient' ? 'is-active' : '' ?>">
                            <a href="<?= $sortUrl('recipient') ?>">Recipient <span class="emails-sort-icon"><i class="bi bi-arrow-down-up"></i></span></a>
                        </th>
                        <th class="emails-th-sort <?= $sort === 'subject' ? 'is-active' : '' ?>">
                            <a href="<?= $sortUrl('subject') ?>">Subject <span class="emails-sort-icon"><i class="bi bi-arrow-down-up"></i></span></a>
                        </th>
                        <th class="emails-th-sort <?= $sort === 'status' ? 'is-active' : '' ?>">
                            <a href="<?= $sortUrl('status') ?>">Status <span class="emails-sort-icon"><i class="bi bi-arrow-down-up"></i></span></a>
                        </th>
                        <th class="emails-th-sort <?= $sort === 'sent' ? 'is-active' : '' ?>">
                            <a href="<?= $sortUrl('sent') ?>">Sent <span class="emails-sort-icon"><i class="bi bi-arrow-down-up"></i></span></a>
                        </th>
                        <th>User</th>
                        <th class="emails-th-actions">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($emails as $email) : ?>
                    <tr>
                        <td>
                            <p class="emails-cell-name mb-0"><?= esc($email['recipient_name']) ?></p>
                            <p class="emails-cell-email mb-0"><?= esc($email['recipient_email']) ?></p>
                        </td>
                        <td class="emails-meta"><?= esc($email['subject']) ?></td>
                        <td><span class="emails-status emails-status--<?= esc($email['status']) ?>"><?= esc($email['status']) ?></span></td>
                        <td class="emails-meta"><?= esc($email['sent_at'] ?? '—') ?></td>
                        <td class="emails-meta"><?= esc($email['user_name']) ?></td>
                        <td class="emails-td-actions">
                            <a href="/emails/<?= (int) $email['id'] ?>" class="emails-row-action">View</a>
                            <?php if ($email['status'] === 'failed') : ?>
                                <form method="post" action="/emails/retry/<?= (int) $email['id'] ?>" class="d-inline">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="emails-row-action emails-row-action--warn">Retry</button>
                                </form>
                            <?php elseif ($email['status'] === 'draft') : ?>
                                <form method="post" action="/emails/send-draft/<?= (int) $email['id'] ?>" class="d-inline">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="emails-row-action emails-row-action--primary">Send</button>
                                </form>
                            <?php endif ?>
                            <?php if ($canManageEmails) : ?>
                                <form method="post" action="/emails/delete/<?= (int) $email['id'] ?>" class="d-inline">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="emails-row-action emails-row-action--danger"
                                            aria-label="Move <?= esc($email['subject'], 'attr') ?> to trash">Trash</button>
                                </form>
                            <?php endif ?>
                        </td>
                    </tr>
                <?php endforeach ?>
                </tbody>
            </table>
        </div>
        <?php if ($pager->getPageCount('emails') > 1) : ?>
            <div class="emails-footer"><?= $pager->links('emails') ?></div>
        <?php endif ?>
    <?php endif ?>
</div>

<?= $this->endSection() ?>
