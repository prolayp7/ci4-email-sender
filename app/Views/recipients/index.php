<?= $this->extend('layout/main') ?>

<?= $this->section('styles') ?>
<link href="/assets/css/pages/recipients.css?v=<?= @filemtime(FCPATH . 'assets/css/pages/recipients.css') ?>" rel="stylesheet">
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<?php
$avatarClass = static fn (int $id) => 'recipients-av-' . ($id % 8);
$initial = static fn (string $name) => esc(strtoupper(substr($name, 0, 1)) ?: '?');

$sortUrl = static function (string $field) use ($sort, $dir, $search, $status) {
    $params = array_filter([
        'q'      => $search,
        'status' => $status,
        'sort'   => $field,
        'dir'    => ($sort === $field && $dir === 'asc') ? 'desc' : 'asc',
    ], static fn ($v) => $v !== null && $v !== '');
    return '/recipients?' . http_build_query($params);
};
?>

<div class="d-flex flex-wrap align-items-start justify-content-between gap-3 mb-4">
    <div>
        <h1 class="recipients-page-title">Recipients</h1>
        <p class="recipients-page-sub">Manage the contacts you send email campaigns to</p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <button class="btn btn-outline-secondary btn-sm" type="button" data-bs-toggle="modal" data-bs-target="#importModal">
            <i class="bi bi-upload me-1"></i>Import
        </button>
        <a href="/recipients/export" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-download me-1"></i>Export CSV
        </a>
        <button class="btn btn-primary btn-sm" type="button" data-bs-toggle="modal" data-bs-target="#addRecipientModal">
            <i class="bi bi-plus-lg me-1"></i>Add Recipient
        </button>
    </div>
</div>

<?php if (session()->getFlashdata('success')) : ?>
    <div class="alert alert-success"><?= esc(session()->getFlashdata('success')) ?></div>
<?php endif ?>
<?php if (session()->getFlashdata('error')) : ?>
    <div class="alert alert-danger"><?= esc(session()->getFlashdata('error')) ?></div>
<?php endif ?>
<?php $summary = session()->getFlashdata('importSummary'); ?>
<?php if ($summary) : ?>
    <div class="alert alert-info">
        Imported: <?= (int) $summary['imported'] ?> &middot;
        Skipped: <?= (int) $summary['skipped'] ?> &middot;
        Invalid: <?= (int) $summary['invalid'] ?> &middot;
        Duplicates: <?= (int) $summary['duplicates'] ?>
    </div>
<?php endif ?>

<!-- Stats strip -->
<div class="row g-3 mb-3">
    <div class="col-6 col-xl-4">
        <div class="recipients-stat recipients-stat--indigo">
            <span class="recipients-stat__icon"><i class="bi bi-people-fill"></i></span>
            <div>
                <p class="recipients-stat__label">Total Recipients</p>
                <p class="recipients-stat__value"><?= (int) $stats['total'] ?></p>
            </div>
        </div>
    </div>
    <div class="col-6 col-xl-4">
        <div class="recipients-stat recipients-stat--green">
            <span class="recipients-stat__icon"><i class="bi bi-check-circle-fill"></i></span>
            <div>
                <p class="recipients-stat__label">Active</p>
                <p class="recipients-stat__value"><?= (int) $stats['active'] ?></p>
            </div>
        </div>
    </div>
    <div class="col-6 col-xl-4">
        <div class="recipients-stat recipients-stat--red">
            <span class="recipients-stat__icon"><i class="bi bi-slash-circle-fill"></i></span>
            <div>
                <p class="recipients-stat__label">Unsubscribed</p>
                <p class="recipients-stat__value"><?= (int) $stats['unsubscribed'] ?></p>
            </div>
        </div>
    </div>
</div>

<!-- Toolbar -->
<form method="get" action="/recipients" class="recipients-toolbar" role="search">
    <div class="recipients-toolbar__search">
        <i class="bi bi-search"></i>
        <input type="search" name="q" class="form-control" placeholder="Search by name, email, or company…" value="<?= esc($search ?? '') ?>">
    </div>
    <select class="form-select form-select-sm" name="status" aria-label="Filter by status" onchange="this.form.submit()">
        <option value="" <?= empty($status) ? 'selected' : '' ?>>All statuses</option>
        <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Active</option>
        <option value="unsubscribed" <?= $status === 'unsubscribed' ? 'selected' : '' ?>>Unsubscribed</option>
    </select>
    <button type="submit" class="btn btn-outline-secondary btn-sm">Search</button>
    <?php if ($search || $status) : ?>
        <a href="/recipients" class="recipients-toolbar__reset ms-auto"><i class="bi bi-arrow-counterclockwise me-1"></i>Reset filters</a>
    <?php endif ?>
</form>

<!-- Table card -->
<div class="recipients-card">

    <div class="recipients-bulkbar" id="bulkBar">
        <span class="recipients-bulkbar__count"><span id="bulkCount">0</span> selected</span>
        <div class="recipients-bulkbar__actions">
            <button class="btn btn-outline-danger" type="button" onclick="bulkDeleteRecipients()"><i class="bi bi-trash me-1"></i>Delete</button>
            <button class="btn btn-link text-decoration-none" type="button" onclick="toggleAll({checked:false}); updateBulkButton();">Clear selection</button>
        </div>
    </div>

    <?php if (empty($recipients)) : ?>
        <div class="recipients-empty">
            <div class="recipients-empty__illus"><i class="bi bi-people"></i></div>
            <h6>No recipients found</h6>
            <p><?= ($search || $status) ? 'Try adjusting your search or filters.' : 'Add your first recipient to get started.' ?></p>
            <?php if ($search || $status) : ?>
                <a href="/recipients" class="btn btn-outline-primary btn-sm"><i class="bi bi-arrow-counterclockwise me-1"></i>Clear filters</a>
            <?php else : ?>
                <a href="/recipients/create" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>Add Recipient</a>
            <?php endif ?>
        </div>
    <?php else : ?>
        <div class="recipients-table-wrap">
            <table class="table recipients-table align-middle mb-0" aria-label="Recipients list">
                <thead>
                    <tr>
                        <th class="recipients-th-check"><input type="checkbox" id="selectAll" onclick="toggleAll(this)"></th>
                        <th class="recipients-th-sort <?= $sort === 'name' ? 'is-active' : '' ?>">
                            <a href="<?= $sortUrl('name') ?>">Name <span class="recipients-sort-icon"><i class="bi bi-arrow-down-up"></i></span></a>
                        </th>
                        <th class="recipients-th-sort <?= $sort === 'email' ? 'is-active' : '' ?>">
                            <a href="<?= $sortUrl('email') ?>">Email <span class="recipients-sort-icon"><i class="bi bi-arrow-down-up"></i></span></a>
                        </th>
                        <th class="recipients-th-sort <?= $sort === 'company' ? 'is-active' : '' ?>">
                            <a href="<?= $sortUrl('company') ?>">Company <span class="recipients-sort-icon"><i class="bi bi-arrow-down-up"></i></span></a>
                        </th>
                        <th class="recipients-th-sort <?= $sort === 'status' ? 'is-active' : '' ?>">
                            <a href="<?= $sortUrl('status') ?>">Status <span class="recipients-sort-icon"><i class="bi bi-arrow-down-up"></i></span></a>
                        </th>
                        <th class="recipients-th-actions">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($recipients as $r) : ?>
                    <tr>
                        <td class="recipients-td-check"><input type="checkbox" class="rowCheck" value="<?= (int) $r['id'] ?>" onclick="updateBulkButton()"></td>
                        <td>
                            <div class="recipients-cell-user">
                                <span class="avatar avatar-sm <?= $avatarClass((int) $r['id']) ?>"><?= $initial($r['name']) ?></span>
                                <p class="recipients-cell-name mb-0"><?= esc($r['name']) ?></p>
                            </div>
                        </td>
                        <td class="recipients-meta"><?= esc($r['email']) ?></td>
                        <td class="recipients-meta"><?= esc($r['company'] ?? '—') ?></td>
                        <td>
                            <span class="recipients-status recipients-status--<?= esc($r['status']) ?>">
                                <span class="recipients-status__dot"></span><?= esc(ucfirst($r['status'])) ?>
                            </span>
                        </td>
                        <td class="recipients-td-actions">
                            <button type="button" class="recipients-row-action" aria-label="View recipient"
                                    onclick='viewRecipient(<?= json_encode([
                                        "id" => (int) $r["id"], "name" => $r["name"], "email" => $r["email"], "company" => $r["company"] ?? "",
                                        "phone" => $r["phone"] ?? "", "status" => $r["status"], "notes" => $r["notes"] ?? "",
                                        "created_at" => $r["created_at"],
                                    ], JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP) ?>)'>
                                <i class="bi bi-eye"></i>
                            </button>
                            <a href="/recipients/edit/<?= (int) $r['id'] ?>" class="recipients-row-action" aria-label="Edit recipient"><i class="bi bi-pencil"></i></a>
                            <button type="button" class="recipients-row-action" aria-label="Delete recipient" onclick="deleteRecipient(<?= (int) $r['id'] ?>)"><i class="bi bi-trash"></i></button>
                        </td>
                    </tr>
                <?php endforeach ?>
                </tbody>
            </table>
        </div>

        <div class="recipients-footer">
            <div class="recipients-footer__meta">
                <?php $total = $pager->getTotal(); $perPage = $pager->getPerPage(); $current = $pager->getCurrentPage(); ?>
                <?php if ($total > 0) : ?>
                    Showing <?= (($current - 1) * $perPage) + 1 ?>–<?= min($current * $perPage, $total) ?> of <?= $total ?> recipients
                <?php else : ?>
                    No recipients
                <?php endif ?>
            </div>
            <div class="recipients-footer__pager"><?= $pager->links() ?></div>
        </div>
    <?php endif ?>
</div>

<!-- View modal -->
<div class="modal fade" id="viewRecipientModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Recipient details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="recipients-view-header">
                    <span class="avatar bg-primary-subtle text-primary fw-semibold" id="viewAvatar">?</span>
                    <div class="flex-grow-1 min-w-0">
                        <h5 id="viewName">—</h5>
                        <p id="viewEmail">—</p>
                    </div>
                    <span id="viewStatus"></span>
                </div>
                <dl class="recipients-view-grid">
                    <div><dt>Company</dt><dd id="viewCompany">—</dd></div>
                    <div><dt>Phone</dt><dd id="viewPhone">—</dd></div>
                    <div><dt>Added</dt><dd id="viewCreated">—</dd></div>
                    <div class="recipients-view-grid__full"><dt>Notes</dt><dd id="viewNotes">—</dd></div>
                </dl>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                <a href="#" class="btn btn-primary btn-sm" id="viewEditLink"><i class="bi bi-pencil me-1"></i>Edit</a>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="importModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post" action="/recipients/import" enctype="multipart/form-data">
                <?= csrf_field() ?>
                <div class="modal-header"><h5 class="modal-title">Import Recipients</h5></div>
                <div class="modal-body">
                    <p class="small text-body-secondary mb-1">CSV columns: Name, Email, Company, Phone. Max 2MB.</p>
                    <p class="small mb-3"><a href="/samples/recipients-sample.csv" download>Download a sample CSV</a> to see the expected format.</p>
                    <input type="file" name="csv" accept=".csv" class="form-control" required>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Import</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="addRecipientModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="addRecipientForm" novalidate>
                <input type="hidden" id="addRecipientCsrfField" name="<?= csrf_token() ?>" value="<?= csrf_hash() ?>">
                <div class="modal-header">
                    <h5 class="modal-title">Add Recipient</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="addRecipientAlert" class="alert alert-danger py-2 d-none"></div>
                    <?= $this->include('recipients/_fields') ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="addRecipientSubmitBtn">
                        <span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
                        <span>Save</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<form id="deleteForm" method="post" style="display:none;"><?= csrf_field() ?></form>
<form id="bulkDeleteForm" method="post" action="/recipients/bulk-delete" style="display:none;"><?= csrf_field() ?></form>
<script>
function deleteRecipient(id) {
    confirmAction('Delete this recipient? This action cannot be undone.', function () {
        const form = document.getElementById('deleteForm');
        form.action = '/recipients/delete/' + id;
        form.submit();
    });
}
function toggleAll(source) {
    document.querySelectorAll('.rowCheck').forEach(cb => cb.checked = source.checked);
    updateBulkButton();
}
function updateBulkButton() {
    const checked = document.querySelectorAll('.rowCheck:checked').length;
    document.getElementById('bulkCount').textContent = checked;
    document.getElementById('bulkBar').classList.toggle('is-visible', checked > 0);
}
function bulkDeleteRecipients() {
    const ids = Array.from(document.querySelectorAll('.rowCheck:checked')).map(cb => cb.value);
    confirmAction('Delete ' + ids.length + ' selected recipient(s)? This action cannot be undone.', function () {
        const form = document.getElementById('bulkDeleteForm');
        ids.forEach(id => {
            const input = document.createElement('input');
            input.type = 'hidden'; input.name = 'ids[]'; input.value = id;
            form.appendChild(input);
        });
        form.submit();
    });
}

function viewRecipient(r) {
    const viewModal = bootstrap.Modal.getOrCreateInstance(document.getElementById('viewRecipientModal'));
    document.getElementById('viewAvatar').textContent = (r.name[0] || '?').toUpperCase();
    document.getElementById('viewName').textContent = r.name;
    document.getElementById('viewEmail').textContent = r.email;
    document.getElementById('viewCompany').textContent = r.company || '—';
    document.getElementById('viewPhone').textContent = r.phone || '—';
    document.getElementById('viewNotes').textContent = r.notes || '—';
    document.getElementById('viewCreated').textContent = r.created_at || '—';
    document.getElementById('viewEditLink').href = '/recipients/edit/' + r.id;
    const statusEl = document.getElementById('viewStatus');
    statusEl.innerHTML = '<span class="recipients-status recipients-status--' + r.status + '"><span class="recipients-status__dot"></span>' + r.status.charAt(0).toUpperCase() + r.status.slice(1) + '</span>';
    viewModal.show();
}

// ---------- Add Recipient modal ----------
(function () {
    const form = document.getElementById('addRecipientForm');
    const alertBox = document.getElementById('addRecipientAlert');
    const submitBtn = document.getElementById('addRecipientSubmitBtn');

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
            const response = await fetch('/recipients/create', {
                method: 'POST',
                headers: { Accept: 'application/json' },
                body: new FormData(form),
            });
            const data = await response.json();

            if (data.csrfName && data.csrfHash) {
                const csrfField = document.getElementById('addRecipientCsrfField');
                csrfField.name = data.csrfName;
                csrfField.value = data.csrfHash;
            }

            if (data.success) {
                window.location.reload();
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

    document.getElementById('addRecipientModal').addEventListener('hidden.bs.modal', () => {
        form.reset();
        clearFieldErrors();
        alertBox.classList.add('d-none');
    });
})();
</script>

<?= $this->endSection() ?>
