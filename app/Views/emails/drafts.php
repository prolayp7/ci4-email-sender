<?= $this->extend('layout/main') ?>

<?= $this->section('styles') ?>
<link href="/assets/css/pages/emails.css?v=<?= @filemtime(FCPATH . 'assets/css/pages/emails.css') ?>" rel="stylesheet">
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<?php $canManageEmails = in_array(session()->get('user_role'), ['owner', 'admin', 'operator'], true); ?>

<div class="d-flex flex-wrap align-items-start justify-content-between gap-3 mb-4">
    <div>
        <h1 class="emails-page-title">Drafts</h1>
        <p class="emails-page-sub">Unsent messages you've saved to finish later.</p>
    </div>
    <a href="/compose" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>New Email</a>
</div>

<?php if (session()->getFlashdata('success')) : ?>
    <div class="alert alert-success"><?= esc(session()->getFlashdata('success')) ?></div>
<?php endif ?>
<?php if (session()->getFlashdata('error')) : ?>
    <div class="alert alert-danger"><?= esc(session()->getFlashdata('error')) ?></div>
<?php endif ?>

<div class="emails-card">
    <?php if ($emails === []) : ?>
        <div class="emails-empty">
            <div class="emails-empty__illus"><i class="bi bi-file-earmark"></i></div>
            <h6>No drafts</h6>
            <p>Save a draft from Compose to resume it later.</p>
            <a href="/compose" class="btn btn-primary btn-sm">Compose email</a>
        </div>
    <?php else : ?>
        <div class="emails-table-wrap">
            <table class="table emails-table align-middle mb-0" aria-label="Drafts">
                <thead>
                    <tr>
                        <th>Recipient</th>
                        <th>Subject</th>
                        <th>Last saved</th>
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
                        <td class="emails-meta"><?= esc($email['subject'] ?: '(no subject)') ?></td>
                        <td class="emails-meta"><?= esc($email['updated_at']) ?></td>
                        <td class="emails-td-actions">
                            <a href="/compose/edit/<?= (int) $email['id'] ?>" class="emails-row-action">Edit</a>
                            <?php if ($canManageEmails) : ?>
                                <form method="post" action="/emails/send-draft/<?= (int) $email['id'] ?>" class="d-inline">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="emails-row-action emails-row-action--primary">Send</button>
                                </form>
                                <button type="button" class="emails-row-action" onclick="deleteEmail(<?= (int) $email['id'] ?>)">Delete</button>
                            <?php else : ?>
                                <span class="emails-meta">View only</span>
                            <?php endif ?>
                        </td>
                    </tr>
                <?php endforeach ?>
                </tbody>
            </table>
        </div>
    <?php endif ?>
</div>

<form id="deleteEmailForm" method="post" style="display:none;"><?= csrf_field() ?></form>
<script>
function deleteEmail(id) {
    confirmAction('Move this draft to trash?', function () {
        const form = document.getElementById('deleteEmailForm');
        form.action = '/emails/delete/' + id;
        form.submit();
    });
}
</script>

<?= $this->endSection() ?>
