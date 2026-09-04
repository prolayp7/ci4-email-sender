<?= $this->extend('layout/main') ?>

<?= $this->section('styles') ?>
<link href="/assets/css/pages/emails.css?v=<?= @filemtime(FCPATH . 'assets/css/pages/emails.css') ?>" rel="stylesheet">
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<?php $canManageTrash = in_array(session()->get('user_role'), ['owner', 'admin', 'operator'], true); ?>

<div class="d-flex flex-wrap align-items-start justify-content-between gap-3 mb-4">
    <div>
        <h1 class="emails-page-title">Trash</h1>
        <p class="emails-page-sub">Restore deleted emails or remove them permanently</p>
    </div>
    <a href="/emails" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Email history</a>
</div>

<?php if (session()->getFlashdata('success')) : ?>
    <div class="alert alert-success" role="status"><?= esc(session()->getFlashdata('success')) ?></div>
<?php endif ?>
<?php if (session()->getFlashdata('error')) : ?>
    <div class="alert alert-danger" role="alert"><?= esc(session()->getFlashdata('error')) ?></div>
<?php endif ?>

<div class="emails-card">
    <?php if ($emails === []) : ?>
        <div class="emails-empty">
            <div class="emails-empty__illus"><i class="bi bi-trash3"></i></div>
            <h6>Trash is empty</h6>
            <p>Emails you move to trash will appear here.</p>
            <a href="/emails" class="btn btn-primary btn-sm">View email history</a>
        </div>
    <?php else : ?>
        <div class="emails-table-wrap">
            <table class="table emails-table align-middle mb-0" aria-label="Trashed emails">
                <thead><tr><th>Recipient</th><th>Subject</th><th>Status</th><th>Deleted</th><th>User</th><th class="emails-th-actions">Actions</th></tr></thead>
                <tbody>
                <?php foreach ($emails as $email) : ?>
                    <tr>
                        <td><p class="emails-cell-name mb-0"><?= esc($email['recipient_name']) ?></p><p class="emails-cell-email mb-0"><?= esc($email['recipient_email']) ?></p></td>
                        <td class="emails-meta"><?= esc($email['subject']) ?></td>
                        <td><span class="emails-status emails-status--<?= esc($email['status']) ?>"><?= esc($email['status']) ?></span></td>
                        <td class="emails-meta"><?= esc($email['deleted_at']) ?></td>
                        <td class="emails-meta"><?= esc($email['user_name']) ?></td>
                        <td class="emails-td-actions">
                            <?php if ($canManageTrash) : ?>
                                <form method="post" action="/emails/restore/<?= (int) $email['id'] ?>" class="d-inline">
                                    <?= csrf_field() ?><button type="submit" class="emails-row-action emails-row-action--primary">Restore</button>
                                </form>
                                <form method="post" action="/emails/destroy/<?= (int) $email['id'] ?>" class="d-inline" onsubmit="return confirm('Permanently delete this email? This cannot be undone.');">
                                    <?= csrf_field() ?><button type="submit" class="emails-row-action emails-row-action--danger">Delete forever</button>
                                </form>
                            <?php else : ?>
                                <span class="emails-meta">View only</span>
                            <?php endif ?>
                        </td>
                    </tr>
                <?php endforeach ?>
                </tbody>
            </table>
        </div>
        <?php if ($pager->getPageCount('trash') > 1) : ?>
            <div class="emails-footer"><?= $pager->links('trash') ?></div>
        <?php endif ?>
    <?php endif ?>
</div>

<?= $this->endSection() ?>
