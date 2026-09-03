<?= $this->extend('layout/main') ?>
<?= $this->section('content') ?>
<div class="row g-4">
    <div class="col-lg-7">
        <div class="card">
            <div class="card-body">
                <form id="composeForm" enctype="multipart/form-data">
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label for="recipientSelect" class="form-label">Recipient</label>
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
                    <div class="mb-3">
                        <label for="templateSelect" class="form-label">Template</label>
                        <select id="templateSelect" class="form-select">
                            <option value="">Blank email</option>
                            <?php foreach ($templates as $template) : ?>
                                <option value="<?= (int) $template['id'] ?>"><?= esc($template['name']) ?></option>
                            <?php endforeach ?>
                        </select>
                        <input type="hidden" name="template_id" id="templateIdInput">
                    </div>
                    <div class="mb-3">
                        <label for="subjectInput" class="form-label">Subject</label>
                        <input type="text" name="subject" id="subjectInput" class="form-control" required maxlength="255">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Message</label>
                        <div id="composeEditor" style="height:220px;background:#fff;"></div>
                        <input type="hidden" name="body_html" id="bodyHtmlInput">
                        <div class="form-text">Available placeholders: <code>{{name}}</code>, <code>{{email}}</code>, <code>{{company}}</code>.</div>
                    </div>
                    <div class="mb-3">
                        <label for="attachmentsInput" class="form-label">Attachments</label>
                        <input type="file" name="attachments[]" id="attachmentsInput" class="form-control" multiple>
                        <div class="form-text">Up to 5 files, 10MB each. PDF, Office documents, images, text, CSV, or ZIP.</div>
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                        <button type="button" id="sendButton" class="btn btn-primary">Send Email</button>
                        <button type="button" id="draftButton" class="btn btn-outline-secondary">Save Draft</button>
                        <button type="reset" class="btn btn-outline-secondary">Clear</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card">
            <div class="card-header bg-white">Preview</div>
            <div class="card-body p-0">
                <iframe id="previewPane" title="Email preview" sandbox
                        class="w-100 border-0" style="min-height:320px;"
                        srcdoc="<!doctype html><html><body><p style='color:#6c757d;font-family:sans-serif'>Write a message to preview it here.</p></body></html>"></iframe>
            </div>
        </div>
    </div>
</div>
<link href="https://cdnjs.cloudflare.com/ajax/libs/quill/1.3.7/quill.snow.min.css" rel="stylesheet"
      integrity="sha384-Cr4NirNGPwhXoUPml2HA5PmMExeUuxM/oxUMDhMdSzUi9udHL+hdgDZZpq/2rOrp" crossorigin="anonymous">
<script src="https://cdnjs.cloudflare.com/ajax/libs/quill/1.3.7/quill.min.js"
        integrity="sha384-QUJ+ckWz1M+a7w0UfG1sEn4pPrbQwSxGm/1TIPyioqXBrwuT9l4f9gdHWLDLbVWI" crossorigin="anonymous"></script>
<script>
const composeTemplates = <?= json_encode(array_column($templates, null, 'id'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
const csrfTokenName = <?= json_encode(csrf_token()) ?>;
const quill = new Quill('#composeEditor', { theme: 'snow' });
const form = document.getElementById('composeForm');
const templateSelect = document.getElementById('templateSelect');
const recipientSelect = document.getElementById('recipientSelect');
const subjectInput = document.getElementById('subjectInput');
const bodyInput = document.getElementById('bodyHtmlInput');
const preview = document.getElementById('previewPane');

// Recipient name/company can contain arbitrary text (entered by any
// owner/admin/operator) but here it's spliced into HTML that Quill will
// render as markup (dangerouslyPasteHTML), so it must be escaped -- the
// plain subject <input>.value assignment below needs no escaping since
// that never parses as HTML.
//
// This must escape quotes too, not just &<>: a template is freeform HTML
// and a placeholder could sit inside an attribute (e.g. an href="mailto:
// {{email}}" or title="{{name}}"), where an unescaped " or ' lets the
// value break out of the attribute and inject markup/handlers. A quote-
// blind escaper (e.g. the textContent/innerHTML round-trip, which only
// covers bare text-node content) is incomplete for that case.
function escapeHtml(text) {
    return text.replace(/[&<>"']/g, function (character) {
        return {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'}[character];
    });
}

function getSelectedRecipient() {
    const option = recipientSelect.selectedOptions[0];
    if (!option || !option.value) return null;
    return { name: option.dataset.name || '', email: option.dataset.email || '', company: option.dataset.company || '' };
}

function substitutePlaceholders(text, recipient, escapeForHtml) {
    const values = {
        '{{name}}': escapeForHtml ? escapeHtml(recipient.name) : recipient.name,
        '{{email}}': escapeForHtml ? escapeHtml(recipient.email) : recipient.email,
        '{{company}}': escapeForHtml ? escapeHtml(recipient.company) : recipient.company,
    };
    return text.replace(/\{\{name\}\}|\{\{email\}\}|\{\{company\}\}/g, function (match) {
        return values[match];
    });
}

// Loads the selected template into Subject/Message, substituting the
// currently selected recipient's actual name/email/company in place of
// the {{...}} placeholders wherever one is chosen, so what's shown here
// (and therefore in Preview, which just mirrors these fields) matches
// what will actually be sent.
function applyTemplateForCurrentRecipient() {
    const template = composeTemplates[templateSelect.value];
    if (!template) return;

    const recipient = getSelectedRecipient();
    if (recipient) {
        subjectInput.value = substitutePlaceholders(template.subject, recipient, false);
        quill.clipboard.dangerouslyPasteHTML(substitutePlaceholders(template.html_body, recipient, true));
    } else {
        subjectInput.value = template.subject;
        quill.clipboard.dangerouslyPasteHTML(template.html_body);
    }
    updatePreview();
}

templateSelect.addEventListener('change', function () {
    document.getElementById('templateIdInput').value = this.value;
    applyTemplateForCurrentRecipient();
});

recipientSelect.addEventListener('change', function () {
    if (templateSelect.value) {
        applyTemplateForCurrentRecipient();
    }
});

quill.on('text-change', updatePreview);
subjectInput.addEventListener('input', updatePreview);
form.addEventListener('reset', function () {
    setTimeout(function () {
        quill.setText('');
        document.getElementById('templateIdInput').value = '';
        updatePreview();
    });
});

function updatePreview() {
    const subject = subjectInput.value || '(No subject)';
    const escapedSubject = escapeHtml(subject);
    preview.srcdoc = '<!doctype html><html><head><meta charset="utf-8"><meta http-equiv="Content-Security-Policy" content="default-src none; style-src unsafe-inline; img-src https: http: data:"></head>' +
        '<body style="font-family:Arial,sans-serif;padding:16px"><h3>' + escapedSubject + '</h3><hr><main>' + quill.root.innerHTML + '</main></body></html>';
}

function prepareBody() {
    bodyInput.value = quill.getText().trim() === '' ? '' : quill.root.innerHTML;
}

async function submitCompose(endpoint, button) {
    prepareBody();
    if (!form.reportValidity()) return;
    button.disabled = true;
    try {
        const response = await fetch(endpoint, {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: new FormData(form),
        });
        const data = await response.json();
        // CI4 rotates the CSRF token on every request; without this, a second
        // Send/Save Draft on the same page load would always 403.
        if (data.csrf_hash) {
            const csrfInput = form.querySelector('input[name="' + csrfTokenName + '"]');
            if (csrfInput) csrfInput.value = data.csrf_hash;
        }
        showToast(data.message, data.success ? 'success' : 'danger');
    } catch (error) {
        showToast('The request could not be completed. Please try again.', 'danger');
    } finally {
        button.disabled = false;
    }
}

document.getElementById('sendButton').addEventListener('click', function () {
    prepareBody();
    if (!form.reportValidity()) return;
    const option = document.getElementById('recipientSelect').selectedOptions[0];
    const button = this;
    confirmAction('Send this email to ' + option.dataset.email + '?', function () {
        submitCompose('/compose/send', button);
    }, { confirmLabel: 'Send', confirmClass: 'btn-primary' });
});

document.getElementById('draftButton').addEventListener('click', function () {
    submitCompose('/compose/draft', this);
});
</script>
<?= $this->endSection() ?>
