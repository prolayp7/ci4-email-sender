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
            (window.recipientTomSelect || recipientTomSelect).clear();
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

    // ---------- Bulk mode ----------
    const bulkToggle = document.getElementById('bulkModeToggle');
    const selectAllActiveBtn = document.getElementById('selectAllActiveBtn');
    const actionsSingle = document.getElementById('composeActionsSingle');
    const actionsBulk = document.getElementById('composeActionsBulk');
    let isBulkMode = false;

    function applyRawTemplate() {
        const template = composeTemplates[templateSelect.value];
        if (!template) return;
        subjectInput.value = template.subject;
        quill.clipboard.dangerouslyPasteHTML(template.html_body);
        // Only ever called while isBulkMode is true (see templateSelect's
        // bulk handler below) -- route to the substituting bulk preview
        // rather than the raw single-recipient one, so the placeholders in
        // this template don't sit unsubstituted in the panel until the next
        // keystroke/change happens to refresh it.
        if (isBulkMode && typeof updateBulkPreview === 'function') {
            updateBulkPreview();
        } else {
            updatePreview();
        }
    }

    if (bulkToggle) {
        bulkToggle.addEventListener('change', function () {
            isBulkMode = this.checked;
            recipientSelect.multiple = isBulkMode;
            // Destroy whichever instance is actually live: after the first
            // toggle, that's window.recipientTomSelect (reassigned below),
            // not the original `recipientTomSelect` const -- destroying the
            // stale const on a later toggle leaves the live wrapper in the
            // DOM and TomSelect ends up wrapping the <select> a second time.
            (window.recipientTomSelect || recipientTomSelect).destroy();
            window.recipientTomSelect = new TomSelect(recipientSelect, {
                create: false,
                maxOptions: null,
                placeholder: isBulkMode ? 'Search and select recipients…' : 'Search recipients by name or email…',
            });
            selectAllActiveBtn.classList.toggle('d-none', !isBulkMode);
            actionsSingle.classList.toggle('d-none', isBulkMode);
            actionsBulk.classList.toggle('d-none', !isBulkMode);

            // Re-bind the change listener to whichever TomSelect instance is
            // now live, and switch template application to the raw (bulk)
            // or substituting (single) variant.
            templateSelect.removeEventListener('change', templateSelect._composeHandler);
            templateSelect._composeHandler = function () {
                document.getElementById('templateIdInput').value = this.value;
                isBulkMode ? applyRawTemplate() : applyTemplateForCurrentRecipient();
            };
            templateSelect.addEventListener('change', templateSelect._composeHandler);
        });
    }

    if (selectAllActiveBtn) {
        selectAllActiveBtn.addEventListener('click', function () {
            const allIds = Array.from(recipientSelect.options).map((o) => o.value).filter((v) => v !== '');
            window.recipientTomSelect.setValue(allIds);
        });
    }

    // ---------- Preview as (bulk mode) ----------
    const previewAsSelect = document.getElementById('previewAsSelect');

    function refreshPreviewAsOptions() {
        if (!isBulkMode) {
            previewAsSelect.classList.add('d-none');
            updatePreview();
            return;
        }
        const selected = Array.from(recipientSelect.selectedOptions);
        previewAsSelect.innerHTML = '';
        selected.forEach((opt) => {
            const o = document.createElement('option');
            o.value = opt.value;
            o.textContent = opt.dataset.name;
            previewAsSelect.appendChild(o);
        });
        previewAsSelect.classList.toggle('d-none', selected.length === 0);
        updateBulkPreview();
    }

    function updateBulkPreview() {
        const option = Array.from(recipientSelect.options).find((o) => o.value === previewAsSelect.value);
        const subject = subjectInput.value || '(No subject)';
        const bodyHtml = quill.root.innerHTML;
        if (!option) {
            preview.srcdoc = '<!doctype html><html><body><p style="color:#6c757d;font-family:sans-serif">Select recipients to preview.</p></body></html>';
            return;
        }
        const recipient = { name: option.dataset.name || '', email: option.dataset.email || '', company: option.dataset.company || '' };
        const escapedSubject = escapeHtml(substitutePlaceholders(subject, recipient, false));
        const substitutedBody = substitutePlaceholders(bodyHtml, recipient, true);
        preview.srcdoc = '<!doctype html><html><head><meta charset="utf-8"><meta http-equiv="Content-Security-Policy" content="default-src \'none\'; style-src \'unsafe-inline\'; img-src https: http: data:"></head>' +
            '<body style="font-family:Arial,sans-serif;padding:16px"><h3>' + escapedSubject + '</h3><hr><main>' + substitutedBody + '</main></body></html>';
    }

    if (previewAsSelect) {
        previewAsSelect.addEventListener('change', updateBulkPreview);
        recipientSelect.addEventListener('change', refreshPreviewAsOptions);
        quill.on('text-change', function () { if (isBulkMode) updateBulkPreview(); });
        subjectInput.addEventListener('input', function () { if (isBulkMode) updateBulkPreview(); });
    }

    // ---------- Bulk send loop ----------
    const bulkProgressPanel = document.getElementById('bulkProgressPanel');
    const bulkProgressBar = document.getElementById('bulkProgressBar');
    const bulkProgressSummary = document.getElementById('bulkProgressSummary');
    const bulkProgressList = document.getElementById('bulkProgressList');

    async function runBulkSend() {
        prepareBody();
        const recipientIds = Array.from(recipientSelect.selectedOptions).map((o) => o.value).filter((v) => v !== '');
        if (recipientIds.length === 0) {
            showToast('Select at least one recipient.', 'danger');
            return;
        }
        if (!form.reportValidity()) return;

        const bulkSendButton = document.getElementById('bulkSendButton');
        bulkSendButton.disabled = true;
        bulkProgressPanel.classList.remove('d-none');
        bulkProgressList.innerHTML = '';
        bulkProgressBar.style.width = '0%';

        const rows = {};
        recipientIds.forEach((id) => {
            const option = Array.from(recipientSelect.options).find((o) => o.value === id);
            const li = document.createElement('li');
            li.className = 'compose-progress__item';
            li.textContent = (option ? option.dataset.name : id) + ' — queued';
            bulkProgressList.appendChild(li);
            rows[id] = li;
        });

        try {
            const startBody = new FormData();
            startBody.append('subject', subjectInput.value);
            startBody.append('body_html', bodyInput.value);
            startBody.append('template_id', document.getElementById('templateIdInput').value);
            startBody.append('recipient_count', String(recipientIds.length));
            startBody.append(csrfTokenName, currentCsrfHash);
            Array.from(attachmentsInput.files).forEach((f) => startBody.append('attachments[]', f));

            const startResp = await fetch('/compose/bulk/start', {
                method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: startBody,
            });
            const startData = await startResp.json();
            if (startData.csrf_hash) currentCsrfHash = startData.csrf_hash;
            if (!startData.success) {
                showToast(startData.message, 'danger');
                return;
            }

            let sentCount = 0;
            let failedCount = 0;
            for (const recipientId of recipientIds) {
                rows[recipientId].textContent = rows[recipientId].textContent.replace('queued', 'sending…');

                let data;
                try {
                    const body = new URLSearchParams();
                    body.set('batch_id', startData.batch_id);
                    body.set('recipient_id', recipientId);
                    body.set(csrfTokenName, currentCsrfHash);

                    const resp = await fetch('/compose/bulk/send-one', {
                        method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' }, body: body.toString(),
                    });
                    data = await resp.json();
                } catch (error) {
                    data = { success: false, message: 'request error' };
                }
                if (data.csrf_hash) currentCsrfHash = data.csrf_hash;

                if (data.success) {
                    sentCount++;
                    rows[recipientId].textContent = rows[recipientId].textContent.replace('sending…', 'sent ✓');
                    rows[recipientId].classList.add('compose-progress__item--sent');
                } else {
                    failedCount++;
                    rows[recipientId].textContent = rows[recipientId].textContent.replace('sending…', 'failed ✗ (' + data.message + ')');
                    rows[recipientId].classList.add('compose-progress__item--failed');
                }

                const done = sentCount + failedCount;
                bulkProgressBar.style.width = Math.round((done / recipientIds.length) * 100) + '%';
                bulkProgressSummary.textContent = done + ' / ' + recipientIds.length + ' processed (' + sentCount + ' sent, ' + failedCount + ' failed)';
            }

            showToast('Bulk send finished: ' + sentCount + ' sent, ' + failedCount + ' failed.', failedCount === 0 ? 'success' : 'danger');
            fetch('/compose/bulk/log-summary', {
                method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
                body: new URLSearchParams({ batch_id: startData.batch_id, sent: String(sentCount), failed: String(failedCount), [csrfTokenName]: currentCsrfHash }).toString(),
            });
        } catch (error) {
            showToast('The request could not be completed. Please try again.', 'danger');
        } finally {
            bulkSendButton.disabled = false;
        }
    }

    const bulkSendButtonEl = document.getElementById('bulkSendButton');
    if (bulkSendButtonEl) {
        bulkSendButtonEl.addEventListener('click', runBulkSend);
    }

    // ---------- Bulk recipients pre-selection ----------
    const params = new URLSearchParams(window.location.search);
    const preselected = params.get('bulk_recipients');
    if (preselected && bulkToggle) {
        bulkToggle.checked = true;
        bulkToggle.dispatchEvent(new Event('change'));
        window.recipientTomSelect.setValue(preselected.split(','));
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
