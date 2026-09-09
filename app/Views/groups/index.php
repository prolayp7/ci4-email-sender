<?= $this->extend('layout/main') ?>

<?= $this->section('styles') ?>
<link href="/assets/css/pages/emails.css?v=<?= @filemtime(FCPATH . 'assets/css/pages/emails.css') ?>" rel="stylesheet">
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<?php $canManage = in_array(session()->get('user_role'), ['owner', 'admin', 'operator'], true); ?>

<div class="d-flex flex-wrap align-items-start justify-content-between gap-3 mb-4">
    <div>
        <h1 class="emails-page-title">Recipient Groups</h1>
        <p class="emails-page-sub">Segment recipients into named groups you can send to as a batch on the Compose page.</p>
    </div>
    <?php if ($canManage) : ?>
        <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#newGroupModal">
            <i class="bi bi-plus-lg me-1"></i>New Group
        </button>
    <?php endif ?>
</div>

<?php if (session()->getFlashdata('success')) : ?>
    <div class="alert alert-success"><?= esc(session()->getFlashdata('success')) ?></div>
<?php endif ?>
<?php if (session()->getFlashdata('error')) : ?>
    <div class="alert alert-danger"><?= esc(session()->getFlashdata('error')) ?></div>
<?php endif ?>

<div class="emails-card">
    <?php if (empty($groups)) : ?>
        <div class="emails-empty">
            <div class="emails-empty__illus"><i class="bi bi-people"></i></div>
            <h6>No groups yet</h6>
            <p>Create a group, then add recipients to it here or from the Recipients page.</p>
        </div>
    <?php else : ?>
        <div class="emails-table-wrap">
            <table class="table table-hover emails-table align-middle mb-0" aria-label="Recipient groups">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Total</th>
                        <th>Sendable</th>
                        <th class="emails-th-actions">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($groups as $group) : ?>
                    <tr>
                        <td><?= esc($group['name']) ?></td>
                        <td class="emails-meta"><?= (int) $group['total'] ?></td>
                        <td class="emails-meta"><?= (int) $group['sendable'] ?></td>
                        <td class="emails-td-actions">
                            <a href="/groups/view/<?= (int) $group['id'] ?>" class="emails-row-action">View</a>
                            <?php if ($canManage) : ?>
                                <button type="button" class="emails-row-action emails-row-action--danger" onclick="deleteGroup(<?= (int) $group['id'] ?>)">Delete</button>
                            <?php endif ?>
                        </td>
                    </tr>
                <?php endforeach ?>
                </tbody>
            </table>
        </div>
    <?php endif ?>
</div>

<?php if ($canManage) : ?>
<div class="modal fade" id="newGroupModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post" action="/groups">
                <?= csrf_field() ?>
                <div class="modal-header">
                    <h5 class="modal-title">New Group</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <label for="newGroupName" class="form-label">Group name</label>
                    <input type="text" name="name" id="newGroupName" class="form-control" maxlength="100" required autofocus>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Group</button>
                </div>
            </form>
        </div>
    </div>
</div>

<form id="deleteGroupForm" method="post" style="display:none;"><?= csrf_field() ?></form>
<script>
function deleteGroup(id) {
    confirmAction('Delete this group? Recipients stay untouched, they just leave this group. This cannot be undone.', function () {
        const form = document.getElementById('deleteGroupForm');
        form.action = '/groups/delete/' + id;
        form.submit();
    });
}
</script>
<?php endif ?>

<?= $this->endSection() ?>
