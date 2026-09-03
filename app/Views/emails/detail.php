<?= $this->extend('layout/main') ?>
<?= $this->section('content') ?>
<?php $badge = ['sent' => 'badge-soft-success', 'failed' => 'badge-soft-danger', 'pending' => 'badge-soft-warning', 'draft' => 'badge-soft-secondary'][$email['status']] ?? 'badge-soft-secondary'; ?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <a href="/emails" class="btn btn-sm btn-outline-secondary"><i data-lucide="arrow-left" width="16" height="16"></i> Back to history</a>
    <?php if ($email['status'] === 'failed') : ?>
        <form method="post" action="/emails/retry/<?= (int) $email['id'] ?>">
            <?= csrf_field() ?>
            <button class="btn btn-sm btn-warning">Retry delivery</button>
        </form>
    <?php elseif ($email['status'] === 'draft') : ?>
        <form method="post" action="/emails/send-draft/<?= (int) $email['id'] ?>">
            <?= csrf_field() ?>
            <button class="btn btn-sm btn-primary">Send email</button>
        </form>
    <?php endif ?>
</div>
<div class="card mb-4">
    <div class="card-body p-4">
        <div class="d-flex justify-content-between gap-3 flex-wrap mb-4">
            <div><span class="badge <?= $badge ?> text-capitalize mb-2"><?= esc($email['status']) ?></span><h2 class="h5 mb-0"><?= esc($email['subject']) ?></h2></div>
            <span class="text-muted small">Record #<?= (int) $email['id'] ?></span>
        </div>
        <dl class="row mb-0">
            <dt class="col-sm-3">Recipient</dt><dd class="col-sm-9"><?= esc($email['recipient_name']) ?> <span class="text-muted">&lt;<?= esc($email['recipient_email']) ?>&gt;</span></dd>
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
<section aria-labelledby="messagePreviewHeading">
    <h2 id="messagePreviewHeading" class="h6 mb-3">Message preview</h2>
    <iframe title="Email message preview" sandbox class="w-100 bg-white border rounded" style="min-height:360px"
            srcdoc="<?= esc($email['body_html'], 'attr') ?>"></iframe>
</section>
<?= $this->endSection() ?>
