<?= $this->extend('layout/main') ?>
<?= $this->section('content') ?>
<div class="card" style="max-width:600px;">
    <div class="card-body">
        <?php if (! empty($errors)) : ?>
            <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $e) : ?><li><?= esc($e) ?></li><?php endforeach ?></ul></div>
        <?php endif ?>
        <form method="post" action="<?= ($recipient['id'] ?? null) ? '/recipients/edit/' . $recipient['id'] : '/recipients/create' ?>">
            <?= csrf_field() ?>
            <div class="mb-3">
                <label class="form-label">Name</label>
                <input type="text" name="name" class="form-control" value="<?= esc($recipient['name'] ?? old('name') ?? '') ?>" required maxlength="150">
            </div>
            <div class="mb-3">
                <label class="form-label">Email</label>
                <input type="email" name="email" class="form-control" value="<?= esc($recipient['email'] ?? old('email') ?? '') ?>" required maxlength="191">
            </div>
            <div class="mb-3">
                <label class="form-label">Company</label>
                <input type="text" name="company" class="form-control" value="<?= esc($recipient['company'] ?? old('company') ?? '') ?>" maxlength="150">
            </div>
            <div class="mb-3">
                <label class="form-label">Phone</label>
                <input type="text" name="phone" class="form-control" value="<?= esc($recipient['phone'] ?? old('phone') ?? '') ?>" maxlength="30">
            </div>
            <div class="mb-3">
                <label class="form-label">Notes</label>
                <textarea name="notes" class="form-control" rows="3" maxlength="2000"><?= esc($recipient['notes'] ?? old('notes') ?? '') ?></textarea>
            </div>
            <button type="submit" class="btn btn-primary">Save</button>
            <a href="/recipients" class="btn btn-outline-secondary">Cancel</a>
        </form>
    </div>
</div>
<?= $this->endSection() ?>
