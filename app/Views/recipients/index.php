<?= $this->extend('layout/main') ?>
<?= $this->section('content') ?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <form method="get" class="d-flex gap-2">
        <input type="search" name="q" class="form-control" placeholder="Search recipients..." value="<?= esc($search ?? '') ?>">
        <button class="btn btn-outline-secondary">Search</button>
    </form>
    <div class="d-flex gap-2">
        <button type="button" id="bulkDeleteBtn" class="btn btn-outline-danger" style="display:none;" onclick="bulkDeleteRecipients()">Delete Selected</button>
        <a href="/recipients/export" class="btn btn-outline-secondary">Export CSV</a>
        <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#importModal">Import CSV</button>
        <a href="/recipients/create" class="btn btn-primary">Add Recipient</a>
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

<div class="card">
<?php if (empty($recipients)) : ?>
    <div class="card-body text-center text-muted py-5">No recipients yet. Add your first recipient.</div>
<?php else : ?>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead><tr><th style="width:32px;"><input type="checkbox" id="selectAll" onclick="toggleAll(this)"></th><th>Name</th><th>Email</th><th>Company</th><th>Status</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($recipients as $r) : ?>
                <tr>
                    <td><input type="checkbox" class="rowCheck" value="<?= (int) $r['id'] ?>" onclick="updateBulkButton()"></td>
                    <td><?= esc($r['name']) ?></td>
                    <td><?= esc($r['email']) ?></td>
                    <td><?= esc($r['company'] ?? '') ?></td>
                    <td><span class="badge <?= $r['status'] === 'active' ? 'badge-soft-success' : 'badge-soft-secondary' ?>"><?= esc($r['status']) ?></span></td>
                    <td class="text-end">
                        <a href="/recipients/edit/<?= (int) $r['id'] ?>" class="btn btn-sm btn-outline-secondary">Edit</a>
                        <button type="button" class="btn btn-sm btn-outline-danger" onclick="deleteRecipient(<?= (int) $r['id'] ?>)">Delete</button>
                    </td>
                </tr>
            <?php endforeach ?>
            </tbody>
        </table>
    </div>
<?php endif ?>
</div>
<div class="mt-3"><?= $pager->links() ?></div>

<div class="modal fade" id="importModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post" action="/recipients/import" enctype="multipart/form-data">
                <?= csrf_field() ?>
                <div class="modal-header"><h5 class="modal-title">Import Recipients</h5></div>
                <div class="modal-body">
                    <p class="small text-muted mb-1">CSV columns: Name, Email, Company, Phone. Max 2MB.</p>
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
    document.getElementById('bulkDeleteBtn').style.display = checked > 0 ? 'inline-block' : 'none';
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
</script>

<?= $this->endSection() ?>
