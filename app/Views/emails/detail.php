<?= $this->extend('layout/main') ?>

<?= $this->section('styles') ?>
<link href="/assets/css/pages/emails.css?v=<?= @filemtime(FCPATH . 'assets/css/pages/emails.css') ?>" rel="stylesheet">
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <a href="/emails" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back to history</a>
    <div class="d-flex flex-wrap justify-content-end gap-2">
        <?php if ($email['status'] === 'failed') : ?>
            <form method="post" action="/emails/retry/<?= (int) $email['id'] ?>">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-sm btn-warning">Retry delivery</button>
            </form>
        <?php elseif ($email['status'] === 'draft') : ?>
            <form method="post" action="/emails/send-draft/<?= (int) $email['id'] ?>">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-sm btn-primary">Send email</button>
            </form>
        <?php endif ?>
        <?php if (in_array(session()->get('user_role'), ['owner', 'admin', 'operator'], true)) : ?>
            <form method="post" action="/emails/delete/<?= (int) $email['id'] ?>">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash3 me-1"></i>Move to trash</button>
            </form>
        <?php endif ?>
    </div>
</div>

<div class="emails-card mb-4">
    <div class="p-4">
        <div class="d-flex justify-content-between gap-3 flex-wrap mb-3">
            <div>
                <span class="emails-status emails-status--<?= esc($email['status']) ?> mb-2 d-inline-block"><?= esc($email['status']) ?></span>
                <h2 class="h5 mb-0"><?= esc($email['subject']) ?></h2>
            </div>
            <span class="emails-meta">Record #<?= (int) $email['id'] ?></span>
        </div>
        <dl class="row mb-0">
            <dt class="col-sm-3">Recipient</dt><dd class="col-sm-9"><?= esc($email['recipient_name']) ?> <span class="emails-meta">&lt;<?= esc($email['recipient_email']) ?>&gt;</span></dd>
            <dt class="col-sm-3">Created</dt><dd class="col-sm-9"><?= esc($email['created_at'] ?? '—') ?></dd>
            <dt class="col-sm-3">Sent</dt><dd class="col-sm-9"><?= esc($email['sent_at'] ?? '—') ?></dd>
            <dt class="col-sm-3">Handled by</dt><dd class="col-sm-9"><?= esc($email['user_name']) ?></dd>
            <dt class="col-sm-3">Attempts</dt><dd class="col-sm-9"><?= (int) $email['attempt_count'] ?></dd>
            <?php if ($email['error_message']) : ?>
                <dt class="col-sm-3">Last error</dt><dd class="col-sm-9 text-danger"><?= esc($email['error_message']) ?></dd>
            <?php endif ?>
        </dl>
    </div>
</div>

<?php if (! empty($attachments)) : ?>
<div class="emails-card mb-4">
    <div class="p-4">
        <h2 class="h6 mb-3">Attachments</h2>
        <ul class="list-unstyled mb-0">
            <?php foreach ($attachments as $attachment) : ?>
                <li class="d-flex justify-content-between align-items-center py-2 border-bottom">
                    <span><i class="bi bi-paperclip me-2"></i><?= esc($attachment['original_filename']) ?>
                        <span class="emails-meta">(<?= number_format(((int) $attachment['size_bytes']) / 1024, 1) ?> KB)</span>
                    </span>
                    <a href="/emails/<?= (int) $email['id'] ?>/attachments/<?= (int) $attachment['id'] ?>" class="emails-row-action">Download</a>
                </li>
            <?php endforeach ?>
        </ul>
    </div>
</div>
<?php endif ?>

<section aria-labelledby="messagePreviewHeading" class="emails-card">
    <div class="p-4">
        <h2 id="messagePreviewHeading" class="h6 mb-3">Message preview</h2>
        <iframe title="Email message preview" sandbox class="w-100 border-0 rounded" style="min-height:360px"
                srcdoc="<?= esc($email['body_html'], 'attr') ?>"></iframe>
    </div>
</section>

<?= $this->endSection() ?>
