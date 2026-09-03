<?= $this->extend('layout/main') ?>
<?= $this->section('content') ?>
<div class="row g-4">
    <div class="col-lg-4">
        <section class="card border-0 shadow-sm h-100" aria-labelledby="accountHeading">
            <div class="card-body p-4">
                <h2 id="accountHeading" class="h6 mb-4">Account</h2>
                <div class="d-flex align-items-center gap-3 mb-4">
                    <div class="rounded-circle bg-primary-subtle text-primary d-flex align-items-center justify-content-center fw-bold" style="width:48px;height:48px" aria-hidden="true">
                        <?= esc(mb_strtoupper(mb_substr($user['name'], 0, 1))) ?>
                    </div>
                    <div><div class="fw-semibold"><?= esc($user['name']) ?></div><div class="text-muted small"><?= esc($user['email']) ?></div></div>
                </div>
                <dl class="row small mb-0">
                    <dt class="col-5 text-muted fw-normal">Role</dt><dd class="col-7 text-capitalize"><?= esc($user['role']) ?></dd>
                    <dt class="col-5 text-muted fw-normal">Status</dt><dd class="col-7 text-capitalize"><?= esc($user['status']) ?></dd>
                    <dt class="col-5 text-muted fw-normal">Last login</dt><dd class="col-7"><?= esc($user['last_login_at'] ?? 'Not recorded') ?></dd>
                </dl>
            </div>
        </section>
    </div>
    <div class="col-lg-8">
        <section class="card border-0 shadow-sm" aria-labelledby="passwordHeading">
            <div class="card-body p-4">
                <h2 id="passwordHeading" class="h6 mb-1">Change password</h2>
                <p class="text-muted small mb-4">Use at least 8 characters and avoid reusing your current password.</p>
                <form method="post" action="/settings/password" style="max-width:520px">
                    <?= csrf_field() ?>
                    <div class="mb-3"><label for="currentPassword" class="form-label">Current password</label><input type="password" id="currentPassword" name="current_password" class="form-control" required autocomplete="current-password"></div>
                    <div class="mb-3"><label for="newPassword" class="form-label">New password</label><input type="password" id="newPassword" name="new_password" class="form-control" required minlength="8" maxlength="255" autocomplete="new-password"></div>
                    <div class="mb-4"><label for="confirmPassword" class="form-label">Confirm new password</label><input type="password" id="confirmPassword" name="confirm_password" class="form-control" required minlength="8" maxlength="255" autocomplete="new-password"></div>
                    <button type="submit" class="btn btn-primary">Update password</button>
                </form>
            </div>
        </section>
    </div>
</div>
<?= $this->endSection() ?>
