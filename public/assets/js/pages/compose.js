(function () {
    'use strict';

    const bootstrap = window.composeBootstrap;
    const composeTemplates = bootstrap.templates;
    const csrfTokenName = bootstrap.csrfTokenName;

    const quill = new Quill('#composeEditor', { theme: 'snow' });
    const form = document.getElementById('composeForm');
    const templateSelect = document.getElementById('templateSelect');
    const recipientSelect = document.getElementById('recipientSelect');
    const subjectInput = document.getElementById('subjectInput');
    const bodyInput = document.getElementById('bodyHtmlInput');
    const preview = document.getElementById('previewPane');
    const attachmentsInput = document.getElementById('attachmentsInput');
    const attachmentPreviewList = document.getElementById('attachmentPreviewList');
    const attachmentSelectionStatus = document.getElementById('attachmentSelectionStatus');

    function formatFileSize(bytes) {
        if (bytes < 1024) return bytes + ' B';
        if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
        return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
    }

    // Recipient list can run long, so make it searchable by name/email instead
    // of a plain scrolling <select>. TomSelect wraps the native <select> in
    // place, keeps its value in sync, and still fires 'change' on it -- so the
    // listeners below (and the plain FormData submit) need no changes.
    const recipientTomSelect = new TomSelect(recipientSelect, {
        create: false,
        maxOptions: null,
        placeholder: 'Search recipients by name or email…',
    });

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
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[character];
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

    // Named (rather than anonymous) so Task 17's bulk-mode toggle can later
    // swap this out for a raw-template variant without double-binding.
    templateSelect._composeHandler = function () {
        document.getElementById('templateIdInput').value = this.value;
        applyTemplateForCurrentRecipient();
    };
    templateSelect.addEventListener('change', templateSelect._composeHandler);

    recipientSelect.addEventListener('change', function () {
        if (templateSelect.value) {
            applyTemplateForCurrentRecipient();
        }
    });

    quill.on('text-change', updatePreview);
    subjectInput.addEventListener('input', updatePreview);

    function renderAttachmentPreview() {
        attachmentPreviewList.innerHTML = '';
        const files = Array.from(attachmentsInput.files);
        files.forEach((file, index) => {
            const li = document.createElement('li');
            li.className = 'compose-attachment-list__item';

            const label = document.createElement('span');
            label.className = 'compose-attachment-list__name';
            label.textContent = file.name + ' (' + formatFileSize(file.size) + ')';
            label.title = file.name;

            const removeBtn = document.createElement('button');
            removeBtn.type = 'button';
            removeBtn.className = 'compose-attachment-list__remove';
            removeBtn.setAttribute('aria-label', 'Remove ' + file.name);
            removeBtn.innerHTML = '<i class="bi bi-x-lg"></i>';
            removeBtn.addEventListener('click', () => {
                const dt = new DataTransfer();
                Array.from(attachmentsInput.files).forEach((f, i) => {
                    if (i !== index) dt.items.add(f);
                });
                attachmentsInput.files = dt.files;
                renderAttachmentPreview();
            });

            li.appendChild(label);
            li.appendChild(removeBtn);
            attachmentPreviewList.appendChild(li);
        });
        attachmentSelectionStatus.textContent = files.length === 0
            ? 'No attachments selected.'
            : files.length + (files.length === 1 ? ' attachment selected.' : ' attachments selected.');
    }

    attachmentsInput.addEventListener('change', renderAttachmentPreview);

    form.addEventListener('reset', function () {
        setTimeout(function () {
            quill.setText('');
            document.getElementById('templateIdInput').value = '';
            recipientTomSelect.clear();
            renderAttachmentPreview();
            updatePreview();
        });
    });

    function updatePreview() {
        const subject = subjectInput.value || '(No subject)';
        const escapedSubject = escapeHtml(subject);
        preview.srcdoc = '<!doctype html><html><head><meta charset="utf-8"><meta http-equiv="Content-Security-Policy" content="default-src \'none\'; style-src \'unsafe-inline\'; img-src https: http: data:"></head>' +
            '<body style="font-family:Arial,sans-serif;padding:16px"><h3>' + escapedSubject + '</h3><hr><main>' + quill.root.innerHTML + '</main></body></html>';
    }

    function prepareBody() {
        bodyInput.value = quill.getText().trim() === '' ? '' : quill.root.innerHTML;
    }

    let currentCsrfHash = bootstrap.csrfHash;

    async function submitCompose(endpoint, button) {
        prepareBody();
        // TomSelect hides the underlying required <select>, and a hidden
        // control's validation bubble doesn't reliably show/block submission
        // across browsers -- check it explicitly rather than trusting
        // reportValidity() alone for this one field.
        if (!recipientSelect.value) {
            showToast('Please select a recipient.', 'danger');
            return;
        }
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
                currentCsrfHash = data.csrf_hash;
            }
            showToast(data.message, data.success ? 'success' : 'danger');
            return data;
        } catch (error) {
            showToast('The request could not be completed. Please try again.', 'danger');
        } finally {
            button.disabled = false;
        }
    }

    // ---------- Edit-draft mode ----------
    if (bootstrap.draft) {
        recipientTomSelect.setValue(String(bootstrap.draft.recipient_id));
        subjectInput.value = bootstrap.draft.subject;
        quill.clipboard.dangerouslyPasteHTML(bootstrap.draft.body_html);
        if (bootstrap.draft.template_id) {
            templateSelect.value = String(bootstrap.draft.template_id);
            document.getElementById('templateIdInput').value = String(bootstrap.draft.template_id);
        }
        updatePreview();
    }

    document.getElementById('sendButton').addEventListener('click', function () {
        submitCompose(bootstrap.draft ? ('/compose/update/' + bootstrap.draft.id) : '/compose/send', this);
    });

    document.getElementById('draftButton').addEventListener('click', function () {
        submitCompose(bootstrap.draft ? ('/compose/update/' + bootstrap.draft.id) : '/compose/draft', this);
    });
})();
