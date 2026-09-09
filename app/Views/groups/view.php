<?= $this->extend('layout/main') ?>

<?= $this->section('styles') ?>
<link href="/assets/css/pages/emails.css?v=<?= @filemtime(FCPATH . 'assets/css/pages/emails.css') ?>" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.bootstrap5.min.css" rel="stylesheet"
      integrity="sha384-piG3EtH1fBnPi68q4spy+Qgpb0dHK1D1dwk0GaHwFkvmUxYi526bBlk3xJcjEBsD" crossorigin="anonymous">
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<?php $canManage = in_array(session()->get('user_role'), ['owner', 'admin', 'operator'], true); ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <a href="/groups" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>All groups</a>
    <?php if ($canManage) : ?>
        <button type="button" class="btn btn-sm btn-outline-danger" onclick="deleteGroup(<?= (int) $group['id'] ?>)">
            <i class="bi bi-trash me-1"></i>Delete Group
        </button>
    <?php endif ?>
</div>

<h1 class="emails-page-title mb-1"><?= esc($group['name']) ?></h1>
<p class="emails-page-sub mb-4"><?= count($members) ?> recipient(s) in this group</p>

<?php if (session()->getFlashdata('success')) : ?>
    <div class="alert alert-success"><?= esc(session()->getFlashdata('success')) ?></div>
<?php endif ?>
<?php if (session()->getFlashdata('error')) : ?>
    <div class="alert alert-danger"><?= esc(session()->getFlashdata('error')) ?></div>
<?php endif ?>

<?php if ($canManage) : ?>
<div class="emails-card mb-4">
    <div class="p-4">
        <h2 class="h6 mb-3">Add recipients to this group</h2>
        <form method="post" action="/groups/add-recipients" class="d-flex gap-2 align-items-start flex-wrap">
            <?= csrf_field() ?>
            <input type="hidden" name="group_name" value="<?= esc($group['name'], 'attr') ?>">
            <select name="ids[]" id="addRecipientsSelect" class="form-select" multiple style="min-width:280px;flex:1 1 320px;">
                <?php foreach ($availableRecipients as $r) : ?>
                    <option value="<?= (int) $r['id'] ?>"><?= esc($r['name']) ?> (<?= esc($r['email']) ?>)</option>
                <?php endforeach ?>
            </select>
            <button type="submit" class="btn btn-primary">Add to group</button>
        </form>
    </div>
</div>
<?php endif ?>

<div class="emails-card">
    <?php if (empty($members)) : ?>
        <div class="emails-empty">
            <div class="emails-empty__illus"><i class="bi bi-people"></i></div>
            <h6>No recipients in this group yet</h6>
            <p>Add recipients above, from the Recipients page's bulk action, or during CSV import.</p>
        </div>
    <?php else : ?>
        <div class="emails-table-wrap">
            <table class="table table-hover emails-table align-middle mb-0" aria-label="Group members">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Status</th>
                        <?php if ($canManage) : ?><th class="emails-th-actions">Actions</th><?php endif ?>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($members as $member) : ?>
                    <tr>
                        <td><?= esc($member['name']) ?></td>
                        <td class="emails-meta"><?= esc($member['email']) ?></td>
                        <td><span class="emails-status emails-status--<?= esc($member['status']) ?>"><?= esc($member['status']) ?></span></td>
                        <?php if ($canManage) : ?>
                            <td class="emails-td-actions">
                                <form method="post" action="/groups/remove-recipient/<?= (int) $group['id'] ?>/<?= (int) $member['id'] ?>" class="d-inline">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="emails-row-action emails-row-action--danger">Remove</button>
                                </form>
                            </td>
                        <?php endif ?>
                    </tr>
                <?php endforeach ?>
                </tbody>
            </table>
        </div>
    <?php endif ?>
</div>

<?php if ($canManage) : ?>
<form id="deleteGroupForm" method="post" style="display:none;"><?= csrf_field() ?></form>
<script src="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/js/tom-select.complete.min.js"
        integrity="sha384-cnROoUgVILyibe3J0zhzWoJ9p2WmdnK7j/BOTSWqVDbC1pVw2d+i6Q/1ESKJKCYf" crossorigin="anonymous"></script>
<script>
new TomSelect('#addRecipientsSelect', { create: false, maxOptions: null, placeholder: 'Search recipients by name or email…' });

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
