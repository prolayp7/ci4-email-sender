<?= $this->extend('layout/main') ?>

<?= $this->section('styles') ?>
<link href="/assets/css/pages/templates.css?v=<?= @filemtime(FCPATH . 'assets/css/pages/templates.css') ?>" rel="stylesheet">
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<?php
$sortUrl = static function (string $field) use ($sort, $dir, $search, $status) {
    $params = array_filter([
        'q'      => $search,
        'status' => $status,
        'sort'   => $field,
        'dir'    => ($sort === $field && $dir === 'asc') ? 'desc' : 'asc',
    ], static fn ($v) => $v !== null && $v !== '');
    return '/templates?' . http_build_query($params);
};
?>

<div class="d-flex flex-wrap align-items-start justify-content-between gap-3 mb-4">
    <div>
        <h1 class="templates-page-title">Email Templates</h1>
        <p class="templates-page-sub">Reusable templates for the emails you send</p>
    </div>
    <a href="/templates/create" class="btn btn-primary btn-sm">
        <i class="bi bi-plus-lg me-1"></i>Create Template
    </a>
</div>

<?php if (session()->getFlashdata('success')) : ?>
    <div class="alert alert-success"><?= esc(session()->getFlashdata('success')) ?></div>
<?php endif ?>
<?php if (session()->getFlashdata('error')) : ?>
    <div class="alert alert-danger"><?= esc(session()->getFlashdata('error')) ?></div>
<?php endif ?>

<!-- Stats strip -->
<div class="row g-3 mb-3">
    <div class="col-6 col-xl-4">
        <div class="templates-stat templates-stat--indigo">
            <span class="templates-stat__icon"><i class="bi bi-file-earmark-text-fill"></i></span>
            <div>
                <p class="templates-stat__label">Total Templates</p>
                <p class="templates-stat__value"><?= (int) $stats['total'] ?></p>
            </div>
        </div>
    </div>
    <div class="col-6 col-xl-4">
        <div class="templates-stat templates-stat--green">
            <span class="templates-stat__icon"><i class="bi bi-check-circle-fill"></i></span>
            <div>
                <p class="templates-stat__label">Active</p>
                <p class="templates-stat__value"><?= (int) $stats['active'] ?></p>
            </div>
        </div>
    </div>
    <div class="col-6 col-xl-4">
        <div class="templates-stat templates-stat--amber">
            <span class="templates-stat__icon"><i class="bi bi-pencil-square"></i></span>
            <div>
                <p class="templates-stat__label">Draft</p>
                <p class="templates-stat__value"><?= (int) $stats['draft'] ?></p>
            </div>
        </div>
    </div>
</div>

<!-- Toolbar -->
<form method="get" action="/templates" class="templates-toolbar" role="search">
    <div class="templates-toolbar__search">
        <i class="bi bi-search"></i>
        <input type="search" name="q" class="form-control" placeholder="Search by name or subject…" value="<?= esc($search ?? '') ?>">
    </div>
    <select class="form-select form-select-sm" name="status" aria-label="Filter by status" onchange="this.form.submit()">
        <option value="" <?= empty($status) ? 'selected' : '' ?>>All statuses</option>
        <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Active</option>
        <option value="draft" <?= $status === 'draft' ? 'selected' : '' ?>>Draft</option>
    </select>
    <button type="submit" class="btn btn-outline-secondary btn-sm">Search</button>
    <?php if ($search || $status) : ?>
        <a href="/templates" class="templates-toolbar__reset ms-auto"><i class="bi bi-arrow-counterclockwise me-1"></i>Reset filters</a>
    <?php endif ?>
</form>

<!-- Table card -->
<div class="templates-card">
    <?php if (empty($templates)) : ?>
        <div class="templates-empty">
            <div class="templates-empty__illus"><i class="bi bi-file-earmark-text"></i></div>
            <h6>No templates found</h6>
            <p><?= ($search || $status) ? 'Try adjusting your search or filters.' : 'Create your first email template to get started.' ?></p>
            <?php if ($search || $status) : ?>
                <a href="/templates" class="btn btn-outline-primary btn-sm"><i class="bi bi-arrow-counterclockwise me-1"></i>Clear filters</a>
            <?php else : ?>
                <a href="/templates/create" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>Create Template</a>
            <?php endif ?>
        </div>
    <?php else : ?>
        <div class="templates-table-wrap">
            <table class="table templates-table align-middle mb-0" aria-label="Email templates list">
                <thead>
                    <tr>
                        <th class="templates-th-sort <?= $sort === 'name' ? 'is-active' : '' ?>">
                            <a href="<?= $sortUrl('name') ?>">Name <span class="templates-sort-icon"><i class="bi bi-arrow-down-up"></i></span></a>
                        </th>
                        <th class="templates-th-sort <?= $sort === 'subject' ? 'is-active' : '' ?>">
                            <a href="<?= $sortUrl('subject') ?>">Subject <span class="templates-sort-icon"><i class="bi bi-arrow-down-up"></i></span></a>
                        </th>
                        <th class="templates-th-sort <?= $sort === 'status' ? 'is-active' : '' ?>">
                            <a href="<?= $sortUrl('status') ?>">Status <span class="templates-sort-icon"><i class="bi bi-arrow-down-up"></i></span></a>
                        </th>
                        <th class="templates-th-actions">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($templates as $t) : ?>
                    <tr>
                        <td><p class="templates-cell-name mb-0"><?= esc($t['name']) ?></p></td>
                        <td class="templates-meta"><?= esc($t['subject']) ?></td>
                        <td><span class="templates-status templates-status--<?= esc($t['status']) ?>"><?= esc($t['status']) ?></span></td>
                        <td class="templates-td-actions">
                            <a href="/templates/preview/<?= (int) $t['id'] ?>" class="templates-row-action" target="_blank">Preview</a>
                            <a href="/templates/edit/<?= (int) $t['id'] ?>" class="templates-row-action">Edit</a>
                            <form method="post" action="/templates/duplicate/<?= (int) $t['id'] ?>" class="d-inline"><?= csrf_field() ?><button type="submit" class="templates-row-action">Duplicate</button></form>
                            <button type="button" class="templates-row-action templates-row-action--danger" onclick="deleteTemplate(<?= (int) $t['id'] ?>)">Delete</button>
                        </td>
                    </tr>
                <?php endforeach ?>
                </tbody>
            </table>
        </div>

        <div class="templates-footer">
            <div class="templates-footer__meta">
                <?php $total = $pager->getTotal(); $perPage = $pager->getPerPage(); $current = $pager->getCurrentPage(); ?>
                <?php if ($total > 0) : ?>
                    Showing <?= (($current - 1) * $perPage) + 1 ?>–<?= min($current * $perPage, $total) ?> of <?= $total ?> templates
                <?php else : ?>
                    No templates
                <?php endif ?>
            </div>
            <div class="templates-footer__pager"><?= $pager->links() ?></div>
        </div>
    <?php endif ?>
</div>

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
