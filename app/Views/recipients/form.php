<?= $this->extend('layout/main') ?>
<?= $this->section('content') ?>
<div class="card" style="max-width:600px;">
    <div class="card-body">
        <?php if (! empty($errors)) : ?>
            <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $e) : ?><li><?= esc($e) ?></li><?php endforeach ?></ul></div>
        <?php endif ?>
        <form method="post" action="<?= ($recipient['id'] ?? null) ? '/recipients/edit/' . $recipient['id'] : '/recipients/create' ?>">
            <?= csrf_field() ?>
            <?= $this->include('recipients/_fields') ?>
            <button type="submit" class="btn btn-primary">Save</button>
            <a href="/recipients" class="btn btn-outline-secondary">Cancel</a>
        </form>
    </div>
</div>
<?= $this->endSection() ?>
