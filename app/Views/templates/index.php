<?= $this->extend('layout/main') ?>
<?= $this->section('content') ?>
<div class="d-flex justify-content-end mb-3">
    <a href="/templates/create" class="btn btn-primary">Create Template</a>
</div>
<?php if (session()->getFlashdata('success')) : ?><div class="alert alert-success"><?= esc(session()->getFlashdata('success')) ?></div><?php endif ?>
<div class="card border-0 shadow-sm">
<?php if (empty($templates)) : ?>
    <div class="card-body text-center text-muted py-5">No templates yet. Create your first email template.</div>
<?php else : ?>
    <table class="table table-hover mb-0">
        <thead><tr><th>Name</th><th>Subject</th><th>Status</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($templates as $t) : ?>
            <tr>
                <td><?= esc($t['name']) ?></td>
                <td><?= esc($t['subject']) ?></td>
                <td><span class="badge text-bg-secondary"><?= esc($t['status']) ?></span></td>
                <td class="text-end">
                    <a href="/templates/preview/<?= (int) $t['id'] ?>" class="btn btn-sm btn-outline-secondary" target="_blank">Preview</a>
                    <a href="/templates/edit/<?= (int) $t['id'] ?>" class="btn btn-sm btn-outline-secondary">Edit</a>
                    <form method="post" action="/templates/duplicate/<?= (int) $t['id'] ?>" class="d-inline"><?= csrf_field() ?><button class="btn btn-sm btn-outline-secondary">Duplicate</button></form>
                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="deleteTemplate(<?= (int) $t['id'] ?>)">Delete</button>
                </td>
            </tr>
        <?php endforeach ?>
        </tbody>
    </table>
<?php endif ?>
</div>
<div class="mt-3"><?= $pager->links() ?></div>
<form id="deleteForm" method="post" style="display:none;"><?= csrf_field() ?></form>
<script>
function deleteTemplate(id) {
    confirmAction('Delete this template? This action cannot be undone.', function () {
        const form = document.getElementById('deleteForm');
        form.action = '/templates/delete/' + id;
        form.submit();
    });
}
</script>
<?= $this->endSection() ?>
