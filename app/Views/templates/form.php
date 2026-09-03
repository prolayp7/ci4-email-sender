<?= $this->extend('layout/main') ?>
<?= $this->section('content') ?>
<?php if (! empty($errors)) : ?>
    <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $e) : ?><li><?= esc($e) ?></li><?php endforeach ?></ul></div>
<?php endif ?>
<form method="post" action="<?= ($template['id'] ?? null) ? '/templates/edit/' . $template['id'] : '/templates/create' ?>" id="templateForm">
    <?= csrf_field() ?>
    <div class="row g-3">
        <div class="col-md-8">
            <div class="mb-3">
                <label class="form-label">Template Name</label>
                <input type="text" name="name" class="form-control" value="<?= esc($template['name'] ?? '') ?>" required maxlength="150">
            </div>
            <div class="mb-3">
                <label class="form-label">Subject</label>
                <input type="text" name="subject" class="form-control" value="<?= esc($template['subject'] ?? '') ?>" required maxlength="255">
            </div>
            <div class="mb-3">
                <label class="form-label">HTML Body</label>
                <div id="editor" style="height:250px;background:#fff;"></div>
                <textarea name="html_body" id="htmlBodyInput" style="display:none;"></textarea>
            </div>
            <div class="mb-3">
                <label class="form-label">Plain Text Fallback</label>
                <textarea name="text_body" class="form-control" rows="3"><?= esc($template['text_body'] ?? '') ?></textarea>
            </div>
            <p class="small text-muted">Placeholders: <code>{{name}}</code> <code>{{email}}</code> <code>{{company}}</code></p>
            <button type="submit" class="btn btn-primary">Save Template</button>
            <a href="/templates" class="btn btn-outline-secondary">Cancel</a>
        </div>
    </div>
</form>
<link href="https://cdnjs.cloudflare.com/ajax/libs/quill/1.3.7/quill.snow.min.css" rel="stylesheet"
      integrity="sha384-Cr4NirNGPwhXoUPml2HA5PmMExeUuxM/oxUMDhMdSzUi9udHL+hdgDZZpq/2rOrp" crossorigin="anonymous">
<script src="https://cdnjs.cloudflare.com/ajax/libs/quill/1.3.7/quill.min.js"
        integrity="sha384-QUJ+ckWz1M+a7w0UfG1sEn4pPrbQwSxGm/1TIPyioqXBrwuT9l4f9gdHWLDLbVWI" crossorigin="anonymous"></script>
<script>
const quill = new Quill('#editor', { theme: 'snow' });
quill.root.innerHTML = <?= json_encode($template['html_body'] ?? '', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
document.getElementById('templateForm').addEventListener('submit', function () {
    document.getElementById('htmlBodyInput').value = quill.root.innerHTML;
});
</script>
<?= $this->endSection() ?>
