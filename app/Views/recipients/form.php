<?= $this->extend('layout/main') ?>

<?= $this->section('styles') ?>
<link href="/assets/css/pages/recipients.css?v=<?= @filemtime(FCPATH . 'assets/css/pages/recipients.css') ?>" rel="stylesheet">
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<?php $recipientId = $recipient['id'] ?? null; ?>

<div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-4">
    <div>
        <h1 class="recipients-page-title"><?= $recipientId ? 'Edit Recipient' : 'Add Recipient' ?></h1>
        <p class="recipients-page-sub"><?= $recipientId ? "Update this contact's details." : 'Add a new contact to your recipient list.' ?></p>
    </div>
    <a href="/recipients" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Back to Recipients</a>
</div>

<div class="recipients-form-panel">
    <?php if ($recipientId) : ?>
        <div class="recipients-view-header">
            <span class="avatar recipients-av-<?= (int) $recipientId % 8 ?>"><?= esc(strtoupper(substr((string) ($recipient['name'] ?? ''), 0, 1)) ?: '?') ?></span>
            <div class="flex-grow-1 min-w-0">
                <h5><?= esc($recipient['name'] ?? '') ?></h5>
                <p><?= esc($recipient['email'] ?? '') ?></p>
            </div>
        </div>
    <?php endif ?>

    <?php if (! empty($errors)) : ?>
        <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $e) : ?><li><?= esc($e) ?></li><?php endforeach ?></ul></div>
    <?php endif ?>

    <form method="post" action="<?= $recipientId ? '/recipients/edit/' . $recipientId : '/recipients/create' ?>">
        <?= csrf_field() ?>
        <?= $this->include('recipients/_fields') ?>
        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Save</button>
            <a href="/recipients" class="btn btn-outline-secondary">Cancel</a>
        </div>
    </form>
</div>

<?= $this->endSection() ?>
