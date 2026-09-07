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

    <div id="recipientFormAlert" class="alert alert-danger py-2 d-none"></div>
    <?php if (! empty($errors)) : ?>
        <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $e) : ?><li><?= esc($e) ?></li><?php endforeach ?></ul></div>
    <?php endif ?>

    <form method="post" action="<?= $recipientId ? '/recipients/edit/' . $recipientId : '/recipients/create' ?>" id="recipientForm" novalidate>
        <input type="hidden" id="recipientCsrfField" name="<?= csrf_token() ?>" value="<?= csrf_hash() ?>">
        <?= $this->include('recipients/_fields') ?>
        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary" id="recipientSaveBtn">
                <span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
                <span><i class="bi bi-check-lg me-1"></i>Save</span>
            </button>
            <a href="/recipients" class="btn btn-outline-secondary">Cancel</a>
        </div>
    </form>
</div>

<script>
(function () {
    const form = document.getElementById('recipientForm');
    const alertBox = document.getElementById('recipientFormAlert');
    const submitBtn = document.getElementById('recipientSaveBtn');

    const clearFieldErrors = () => {
        form.querySelectorAll('[data-field]').forEach((input) => input.classList.remove('is-invalid'));
        form.querySelectorAll('[data-field-error]').forEach((el) => { el.textContent = ''; });
    };
    const showFieldErrors = (errors) => {
        Object.entries(errors).forEach(([field, message]) => {
            const input = form.querySelector('[data-field="' + field + '"]');
            const feedback = form.querySelector('[data-field-error="' + field + '"]');
            if (input) input.classList.add('is-invalid');
            if (feedback) feedback.textContent = message;
        });
    };
    const setLoading = (isLoading) => {
        submitBtn.disabled = isLoading;
        submitBtn.querySelector('.spinner-border').classList.toggle('d-none', !isLoading);
    };

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        alertBox.classList.add('d-none');
        clearFieldErrors();
        setLoading(true);

        try {
            const response = await fetch(form.action, {
                method: 'POST',
                headers: { Accept: 'application/json' },
                body: new FormData(form),
            });
            const data = await response.json();

            if (data.csrfName && data.csrfHash) {
                const csrfField = document.getElementById('recipientCsrfField');
                csrfField.name = data.csrfName;
                csrfField.value = data.csrfHash;
            }

            if (data.success) {
                window.location.href = '/recipients';
                return;
            }

            if (data.errors) {
                showFieldErrors(data.errors);
            } else {
                alertBox.textContent = 'Something went wrong. Please try again.';
                alertBox.classList.remove('d-none');
            }
        } catch (err) {
            alertBox.textContent = 'Something went wrong. Please check your connection and try again.';
            alertBox.classList.remove('d-none');
        } finally {
            setLoading(false);
        }
    });
})();
</script>

<?= $this->endSection() ?>
