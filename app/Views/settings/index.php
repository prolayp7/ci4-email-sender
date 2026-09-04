<?= $this->extend('layout/main') ?>

<?= $this->section('styles') ?>
<link href="/assets/css/pages/settings.css?v=<?= @filemtime(FCPATH . 'assets/css/pages/settings.css') ?>" rel="stylesheet">
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<div class="mb-4">
    <h1 class="settings-page-title">Settings</h1>
    <p class="settings-page-sub">Manage your account details and security preferences.</p>
</div>

<?php if (session()->getFlashdata('success')) : ?><div class="alert alert-success"><?= esc(session()->getFlashdata('success')) ?></div><?php endif ?>
<?php if (session()->getFlashdata('error')) : ?><div class="alert alert-danger"><?= esc(session()->getFlashdata('error')) ?></div><?php endif ?>

<div class="row g-4">
    <div class="col-lg-4">
        <section class="settings-panel" aria-labelledby="accountHeading">
            <div class="settings-account">
                <span class="avatar bg-primary-subtle text-primary fw-semibold" aria-hidden="true"><?= esc(mb_strtoupper(mb_substr($user['name'], 0, 1))) ?></span>
                <div>
                    <div class="settings-account__name" id="accountHeading"><?= esc($user['name']) ?></div>
                    <div class="settings-account__email"><?= esc($user['email']) ?></div>
                </div>
            </div>
            <div class="settings-meta">
                <div class="settings-meta__row">
                    <span class="settings-meta__label">Role</span>
                    <span class="settings-meta__value text-capitalize"><?= esc($user['role']) ?></span>
                </div>
                <div class="settings-meta__row">
                    <span class="settings-meta__label">Status</span>
                    <span class="settings-status settings-status--<?= esc($user['status']) ?>"><?= esc($user['status']) ?></span>
                </div>
                <div class="settings-meta__row">
                    <span class="settings-meta__label">Last login</span>
                    <span class="settings-meta__value"><?= esc($user['last_login_at'] ?? 'Not recorded') ?></span>
                </div>
            </div>
        </section>
    </div>
    <div class="col-lg-8">
        <section class="settings-panel" aria-labelledby="passwordHeading">
            <div class="settings-panel__head">
                <h2 id="passwordHeading">Change password</h2>
                <p>Use at least 8 characters and avoid reusing your current password.</p>
            </div>
            <form method="post" action="/settings/password">
                <?= csrf_field() ?>
                <div class="settings-field">
                    <label for="currentPassword">Current password</label>
                    <input type="password" id="currentPassword" name="current_password" class="form-control" required autocomplete="current-password">
                </div>
                <div class="settings-field">
                    <label for="newPassword">New password</label>
                    <input type="password" id="newPassword" name="new_password" class="form-control" required minlength="8" maxlength="255" autocomplete="new-password">
                </div>
                <div class="settings-field">
                    <label for="confirmPassword">Confirm new password</label>
                    <input type="password" id="confirmPassword" name="confirm_password" class="form-control" required minlength="8" maxlength="255" autocomplete="new-password">
                </div>
                <button type="submit" class="btn btn-primary"><i class="bi bi-check2 me-1"></i>Update password</button>
            </form>
        </section>
    </div>
</div>

<?= $this->endSection() ?>
