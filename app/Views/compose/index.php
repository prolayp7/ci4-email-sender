<?= $this->extend('layout/main') ?>

<?= $this->section('styles') ?>
<link href="/assets/css/pages/compose.css?v=<?= @filemtime(FCPATH . 'assets/css/pages/compose.css') ?>" rel="stylesheet">
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<?php
$draft ??= null;
$draftAttachments ??= [];
?>

<div class="mb-4">
    <h1 class="compose-page-title"><?= $draft ? 'Edit Draft' : 'Compose Email' ?></h1>
    <p class="compose-page-sub"><?= $draft ? 'Update this draft, then send it or save your changes.' : 'Send a one-off email to a recipient, optionally starting from a template.' ?></p>
</div>

<div class="row g-4">
    <div class="col-lg-7">
        <div class="compose-panel">
            <form id="composeForm" enctype="multipart/form-data">
                <?= csrf_field() ?>
                <div class="compose-field">
                    <label for="recipientSelect">Recipient</label>
                    <select name="recipient_id" id="recipientSelect" class="form-select" required>
                        <option value="">Select recipient...</option>
                        <?php foreach ($recipients as $recipient) : ?>
                            <option value="<?= (int) $recipient['id'] ?>"
                                    data-name="<?= esc($recipient['name'], 'attr') ?>"
                                    data-email="<?= esc($recipient['email'], 'attr') ?>"
                                    data-company="<?= esc($recipient['company'] ?? '', 'attr') ?>">
                                <?= esc($recipient['name']) ?> (<?= esc($recipient['email']) ?>)
                            </option>
                        <?php endforeach ?>
                    </select>
                    <?php if ($recipients === []) : ?>
                        <div class="form-text">No active recipients are available. <a href="/recipients/create">Add one first</a>.</div>
                    <?php endif ?>
                </div>
                <div class="compose-field">
                    <label for="templateSelect">Template</label>
                    <select id="templateSelect" class="form-select">
                        <option value="">Blank email</option>
                        <?php foreach ($templates as $template) : ?>
                            <option value="<?= (int) $template['id'] ?>"><?= esc($template['name']) ?></option>
                        <?php endforeach ?>
                    </select>
                    <input type="hidden" name="template_id" id="templateIdInput">
                </div>
                <div class="compose-field">
                    <label for="subjectInput">Subject</label>
                    <input type="text" name="subject" id="subjectInput" class="form-control" required maxlength="255">
                </div>
                <div class="compose-field">
                    <label>Message</label>
                    <div class="compose-editor-wrap">
                        <div id="composeEditor" style="height:220px;"></div>
                    </div>
                    <input type="hidden" name="body_html" id="bodyHtmlInput">
                    <div class="form-text">Available placeholders: <code>{{name}}</code>, <code>{{email}}</code>, <code>{{company}}</code>.</div>
                </div>
                <div class="compose-field">
                    <label for="attachmentsInput">Attachments</label>
                    <input type="file" name="attachments[]" id="attachmentsInput" class="form-control"
                           accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.jpg,.jpeg,.png,.gif,.txt,.csv,.zip"
                           aria-describedby="attachmentsHint attachmentSelectionStatus" multiple>
                    <div class="form-text" id="attachmentsHint">Up to 5 files, 10MB each. PDF, Office documents, images, text, CSV, or ZIP.</div>
                    <p class="visually-hidden" id="attachmentSelectionStatus" aria-live="polite"></p>
                    <ul class="compose-attachment-list mt-2" id="attachmentPreviewList" aria-label="Selected attachments"></ul>
                    <?php if ($draft && $draftAttachments !== []) : ?>
                        <ul class="compose-attachment-list mt-2">
                            <?php foreach ($draftAttachments as $existing) : ?>
                                <li class="compose-attachment-list__item">
                                    <label class="d-flex align-items-center gap-2 mb-0">
                                        <input type="checkbox" name="remove_attachments[]" value="<?= (int) $existing['id'] ?>">
                                        <span><?= esc($existing['original_filename']) ?> <span class="text-body-secondary">(remove)</span></span>
                                    </label>
                                </li>
                            <?php endforeach ?>
                        </ul>
                    <?php endif ?>
                </div>
                <div class="compose-actions">
                    <button type="button" id="sendButton" class="btn btn-primary"><i class="bi bi-send me-1"></i><?= $draft ? 'Send' : 'Send Email' ?></button>
                    <button type="button" id="draftButton" class="btn btn-outline-secondary"><i class="bi bi-save me-1"></i><?= $draft ? 'Save Changes' : 'Save Draft' ?></button>
                    <button type="reset" class="btn btn-outline-secondary">Clear</button>
                </div>
            </form>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="compose-panel compose-panel--flush compose-preview">
            <div class="compose-preview__head">Preview</div>
            <iframe id="previewPane" title="Email preview" sandbox
                    class="w-100 border-0"
                    srcdoc="<!doctype html><html><body><p style='color:#6c757d;font-family:sans-serif'>Write a message to preview it here.</p></body></html>"></iframe>
        </div>
    </div>
</div>
<link href="https://cdnjs.cloudflare.com/ajax/libs/quill/1.3.7/quill.snow.min.css" rel="stylesheet"
      integrity="sha384-Cr4NirNGPwhXoUPml2HA5PmMExeUuxM/oxUMDhMdSzUi9udHL+hdgDZZpq/2rOrp" crossorigin="anonymous">
<script src="https://cdnjs.cloudflare.com/ajax/libs/quill/1.3.7/quill.min.js"
        integrity="sha384-QUJ+ckWz1M+a7w0UfG1sEn4pPrbQwSxGm/1TIPyioqXBrwuT9l4f9gdHWLDLbVWI" crossorigin="anonymous"></script>
<link href="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.bootstrap5.min.css" rel="stylesheet"
      integrity="sha384-piG3EtH1fBnPi68q4spy+Qgpb0dHK1D1dwk0GaHwFkvmUxYi526bBlk3xJcjEBsD" crossorigin="anonymous">
<script src="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/js/tom-select.complete.min.js"
        integrity="sha384-cnROoUgVILyibe3J0zhzWoJ9p2WmdnK7j/BOTSWqVDbC1pVw2d+i6Q/1ESKJKCYf" crossorigin="anonymous"></script>
<script>
window.composeBootstrap = {
    templates: <?= json_encode(array_column($templates, null, 'id'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
    csrfTokenName: <?= json_encode(csrf_token()) ?>,
    csrfHash: <?= json_encode(csrf_hash()) ?>,
    draft: <?= $draft ? json_encode([
        'id' => (int) $draft['id'],
        'recipient_id' => (int) $draft['recipient_id'],
        'template_id' => $draft['template_id'] !== null ? (int) $draft['template_id'] : null,
        'subject' => $draft['subject'],
        'body_html' => $draft['body_html'],
    ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) : 'null' ?>,
};
</script>
<script src="/assets/js/pages/compose.js?v=<?= @filemtime(FCPATH . 'assets/js/pages/compose.js') ?>" defer></script>
<?= $this->endSection() ?>
