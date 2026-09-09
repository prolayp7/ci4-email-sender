<?= $this->extend('layout/main') ?>

<?= $this->section('styles') ?>
<link href="/assets/css/pages/recipients.css?v=<?= @filemtime(FCPATH . 'assets/css/pages/recipients.css') ?>" rel="stylesheet">
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<?php
$initial = static fn (string $name) => esc(strtoupper(substr($name, 0, 1)) ?: '?');
$eventLabel = static fn (string $type) => $type === 'open' ? 'Opened the email' : 'Clicked a link';
$eventIcon = static fn (string $type) => $type === 'open' ? 'bi-eye' : 'bi-cursor';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <a href="/recipients" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back to recipients</a>
    <a href="/recipients/edit/<?= (int) $recipient['id'] ?>" class="btn btn-sm btn-primary"><i class="bi bi-pencil me-1"></i>Edit recipient</a>
</div>

<div class="recipients-card mb-4">
    <div class="p-4">
        <div class="recipients-view-header mb-3">
            <span class="avatar bg-primary-subtle text-primary fw-semibold"><?= $initial($recipient['name']) ?></span>
            <div class="flex-grow-1 min-w-0">
                <h5 class="mb-0"><?= esc($recipient['name']) ?></h5>
                <p class="mb-0"><?= esc($recipient['email']) ?></p>
            </div>
            <span class="recipients-status recipients-status--<?= esc($recipient['status']) ?>">
                <span class="recipients-status__dot"></span><?= esc(ucfirst($recipient['status'])) ?>
            </span>
        </div>

        <?php if (! empty($tags)) : ?>
            <div class="recipients-tag-chips mb-3">
                <?php foreach ($tags as $tagName) : ?>
                    <span class="recipients-tag-chip"><?= esc($tagName) ?></span>
                <?php endforeach ?>
            </div>
        <?php endif ?>

        <dl class="recipients-view-grid mb-0">
            <div><dt>Company</dt><dd><?= esc($recipient['company'] ?? '—') ?></dd></div>
            <div><dt>Location</dt><dd><?= esc($recipient['location'] ?? '—') ?></dd></div>
            <div><dt>Phone</dt><dd><?= esc($recipient['phone'] ?? '—') ?></dd></div>
            <div><dt>Added</dt><dd><?= esc($recipient['created_at'] ?? '—') ?></dd></div>
            <?php if (! empty($recipient['notes'])) : ?>
                <div class="recipients-view-grid__full"><dt>Notes</dt><dd><?= esc($recipient['notes']) ?></dd></div>
            <?php endif ?>
        </dl>
    </div>
</div>

<div class="recipients-card">
    <div class="p-4">
        <h2 class="h6 mb-3">Email activity</h2>

        <?php if (empty($timeline)) : ?>
            <p class="text-body-secondary mb-0">No email activity yet.</p>
        <?php else : ?>
            <ul class="recipients-timeline list-unstyled mb-0">
                <?php foreach ($timeline as $entry) : ?>
                    <?php if ($entry['kind'] === 'email') : $email = $entry['data']; ?>
                        <li class="recipients-timeline__item">
                            <span class="recipients-timeline__icon recipients-timeline__icon--<?= esc($email['status']) ?>">
                                <i class="bi bi-envelope"></i>
                            </span>
                            <div class="recipients-timeline__body">
                                <p class="mb-0">
                                    <a href="/emails/<?= (int) $email['id'] ?>"><?= esc($email['subject']) ?></a>
                                    <span class="recipients-status recipients-status--<?= esc($email['status']) ?> ms-2">
                                        <span class="recipients-status__dot"></span><?= esc(ucfirst($email['status'])) ?>
                                    </span>
                                </p>
                                <p class="recipients-timeline__meta mb-0">
                                    <?= esc($email['template_name'] ?? 'One-off email') ?> &middot; <?= esc($entry['at'] ?? '—') ?>
                                    <?php if ($email['status'] === 'failed' && ! empty($email['error_message'])) : ?>
                                        &middot; <span class="text-danger"><?= esc($email['error_message']) ?></span>
                                    <?php endif ?>
                                </p>
                            </div>
                        </li>
                    <?php else : $event = $entry['data']; ?>
                        <li class="recipients-timeline__item">
                            <span class="recipients-timeline__icon recipients-timeline__icon--event">
                                <i class="bi <?= $eventIcon($event['type']) ?>"></i>
                            </span>
                            <div class="recipients-timeline__body">
                                <p class="mb-0"><?= esc($eventLabel($event['type'])) ?><?= $event['type'] === 'click' && ! empty($event['url']) ? ' — ' . esc($event['url']) : '' ?></p>
                                <p class="recipients-timeline__meta mb-0"><?= esc($entry['email']['subject'] ?? '') ?> &middot; <?= esc($entry['at'] ?? '—') ?></p>
                            </div>
                        </li>
                    <?php endif ?>
                <?php endforeach ?>
            </ul>
        <?php endif ?>
    </div>
</div>

<?= $this->endSection() ?>
