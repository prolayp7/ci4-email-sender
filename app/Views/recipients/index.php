<?= $this->extend('layout/main') ?>

<?= $this->section('styles') ?>
<link href="/assets/css/pages/recipients.css?v=<?= @filemtime(FCPATH . 'assets/css/pages/recipients.css') ?>" rel="stylesheet">
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<?php
$avatarClass = static fn (int $id) => 'recipients-av-' . ($id % 8);
$initial = static fn (string $name) => esc(strtoupper(substr($name, 0, 1)) ?: '?');

$activeFilterParams = static fn () => [
    'q' => $search, 'status' => $status, 'location' => $location, 'company' => $company,
    'tag_id' => $tagId, 'last_activity' => $lastActivity, 'template_id' => $templateId, 'sent_status' => $sentStatus,
];
$sortUrl = static function (string $field) use ($sort, $dir, $activeFilterParams) {
    $params = array_filter(array_merge($activeFilterParams(), [
        'sort' => $field,
        'dir'  => ($sort === $field && $dir === 'asc') ? 'desc' : 'asc',
    ]), static fn ($v) => $v !== null && $v !== '');
    return '/recipients?' . http_build_query($params);
};
$hasAnyFilter = array_filter($activeFilterParams(), static fn ($v) => $v !== null && $v !== '') !== [];
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

<!-- Stats strip -->
<div class="row g-3 mb-3">
    <div class="col-6 col-xl">
        <div class="recipients-stat recipients-stat--indigo">
            <span class="recipients-stat__icon"><i class="bi bi-people-fill"></i></span>
            <div>
                <p class="recipients-stat__label">Total Recipients</p>
                <p class="recipients-stat__value"><?= (int) $stats['total'] ?></p>
            </div>
        </div>
    </div>
    <div class="col-6 col-xl">
        <div class="recipients-stat recipients-stat--green">
            <span class="recipients-stat__icon"><i class="bi bi-check-circle-fill"></i></span>
            <div>
                <p class="recipients-stat__label">Active</p>
                <p class="recipients-stat__value"><?= (int) $stats['active'] ?></p>
            </div>
        </div>
    </div>
    <div class="col-6 col-xl">
        <div class="recipients-stat recipients-stat--red">
            <span class="recipients-stat__icon"><i class="bi bi-slash-circle-fill"></i></span>
            <div>
                <p class="recipients-stat__label">Unsubscribed</p>
                <p class="recipients-stat__value"><?= (int) $stats['unsubscribed'] ?></p>
            </div>
        </div>
    </div>
    <div class="col-6 col-xl">
        <div class="recipients-stat recipients-stat--amber">
            <span class="recipients-stat__icon"><i class="bi bi-exclamation-triangle-fill"></i></span>
            <div>
                <p class="recipients-stat__label">Bounced</p>
                <p class="recipients-stat__value"><?= (int) $stats['bounced'] ?></p>
            </div>
        </div>
    </div>
    <div class="col-6 col-xl">
        <div class="recipients-stat recipients-stat--slate">
            <span class="recipients-stat__icon"><i class="bi bi-dash-circle-fill"></i></span>
            <div>
                <p class="recipients-stat__label">Suppressed</p>
                <p class="recipients-stat__value"><?= (int) $stats['suppressed'] ?></p>
            </div>
        </div>
    </div>
</div>

<!-- Toolbar -->
<form method="get" action="/recipients" class="recipients-toolbar" role="search">
    <div class="recipients-toolbar__search">
        <i class="bi bi-search"></i>
        <input type="search" name="q" class="form-control" placeholder="Search by name, email, company, or location…" value="<?= esc($search ?? '') ?>">
    </div>
    <select class="form-select form-select-sm" name="status" aria-label="Filter by status" onchange="this.form.submit()">
        <option value="" <?= empty($status) ? 'selected' : '' ?>>All statuses</option>
        <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Active</option>
        <option value="unsubscribed" <?= $status === 'unsubscribed' ? 'selected' : '' ?>>Unsubscribed</option>
        <option value="bounced" <?= $status === 'bounced' ? 'selected' : '' ?>>Bounced</option>
        <option value="suppressed" <?= $status === 'suppressed' ? 'selected' : '' ?>>Suppressed</option>
    </select>
    <select class="form-select form-select-sm" name="location" aria-label="Filter by location" onchange="this.form.submit()">
        <option value="">All locations</option>
        <?php foreach ($locations as $l) : if (empty($l['location'])) continue; ?>
            <option value="<?= esc($l['location'], 'attr') ?>" <?= $location === $l['location'] ? 'selected' : '' ?>><?= esc($l['location']) ?></option>
        <?php endforeach ?>
    </select>
    <select class="form-select form-select-sm" name="company" aria-label="Filter by company" onchange="this.form.submit()">
        <option value="">All companies</option>
        <?php foreach ($companies as $c) : if (empty($c['company'])) continue; ?>
            <option value="<?= esc($c['company'], 'attr') ?>" <?= $company === $c['company'] ? 'selected' : '' ?>><?= esc($c['company']) ?></option>
        <?php endforeach ?>
    </select>
    <select class="form-select form-select-sm" name="tag_id" aria-label="Filter by tag" onchange="this.form.submit()">
        <option value="">All tags</option>
        <?php foreach ($tags as $t) : ?>
            <option value="<?= (int) $t['id'] ?>" <?= $tagId === (int) $t['id'] ? 'selected' : '' ?>><?= esc($t['name']) ?></option>
        <?php endforeach ?>
    </select>
    <select class="form-select form-select-sm" name="last_activity" aria-label="Filter by last activity" onchange="this.form.submit()">
        <option value="">Any last activity</option>
        <option value="7" <?= $lastActivity === '7' ? 'selected' : '' ?>>Contacted in last 7 days</option>
        <option value="30" <?= $lastActivity === '30' ? 'selected' : '' ?>>Contacted in last 30 days</option>
        <option value="90" <?= $lastActivity === '90' ? 'selected' : '' ?>>Contacted in last 90 days</option>
        <option value="never" <?= $lastActivity === 'never' ? 'selected' : '' ?>>Never contacted</option>
    </select>
    <button type="submit" class="btn btn-outline-secondary btn-sm">Search</button>
    <?php if ($hasAnyFilter) : ?>
        <a href="/recipients" class="recipients-toolbar__reset ms-auto"><i class="bi bi-arrow-counterclockwise me-1"></i>Reset filters</a>
    <?php endif ?>

    <!-- Campaign filter: who has/hasn't been sent a given template yet -->
    <select class="form-select form-select-sm" name="template_id" aria-label="Filter by campaign template" onchange="this.form.submit()">
        <option value="">All recipients (no campaign filter)</option>
        <?php foreach ($templates as $t) : ?>
            <option value="<?= (int) $t['id'] ?>" <?= $templateId === (int) $t['id'] ? 'selected' : '' ?>><?= esc($t['name']) ?></option>
        <?php endforeach ?>
    </select>
    <select class="form-select form-select-sm" name="sent_status" aria-label="Filter by sent status" onchange="this.form.submit()">
        <option value="" <?= empty($sentStatus) ? 'selected' : '' ?>>Sent + not sent</option>
        <option value="unsent" <?= $sentStatus === 'unsent' ? 'selected' : '' ?>>Not sent yet</option>
        <option value="sent" <?= $sentStatus === 'sent' ? 'selected' : '' ?>>Already sent</option>
    </select>
</form>

<!-- Table card -->
<div class="recipients-card">

    <div class="recipients-bulkbar" id="bulkBar">
        <span class="recipients-bulkbar__count"><span id="bulkCount">0</span> selected</span>
        <div class="recipients-bulkbar__actions">
            <button class="btn btn-outline-primary btn-sm" type="button" onclick="bulkEmailRecipients()"><i class="bi bi-envelope me-1"></i>Send Email</button>
            <div class="dropdown d-inline-block">
                <button class="btn btn-outline-secondary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="bi bi-arrow-repeat me-1"></i>Change Status
                </button>
                <ul class="dropdown-menu">
                    <?php foreach (['active' => 'Active', 'unsubscribed' => 'Unsubscribed', 'bounced' => 'Bounced', 'suppressed' => 'Suppressed'] as $value => $label) : ?>
                        <li><a class="dropdown-item" href="#" onclick="bulkChangeStatus('<?= $value ?>'); return false;"><?= $label ?></a></li>
                    <?php endforeach ?>
                </ul>
            </div>
            <button class="btn btn-outline-secondary btn-sm" type="button" onclick="prepareAddToGroupModal()" data-bs-toggle="modal" data-bs-target="#addToGroupModal"><i class="bi bi-collection me-1"></i>Add to Group</button>
            <button class="btn btn-outline-secondary btn-sm" type="button" onclick="bulkExportRecipients()"><i class="bi bi-download me-1"></i>Export</button>
            <button class="btn btn-outline-danger btn-sm" type="button" onclick="bulkDeleteRecipients()"><i class="bi bi-trash me-1"></i>Delete</button>
            <button class="btn btn-link btn-sm text-decoration-none" type="button" onclick="toggleAll({checked:false}); updateBulkButton();">Clear selection</button>
        </div>
    </div>

    <?php if (empty($recipients)) : ?>
        <div class="recipients-empty">
            <div class="recipients-empty__illus"><i class="bi bi-people"></i></div>
            <h6>No recipients found</h6>
            <p><?= $hasAnyFilter ? 'Try adjusting your search or filters.' : 'Add your first recipient to get started.' ?></p>
            <?php if ($hasAnyFilter) : ?>
                <a href="/recipients" class="btn btn-outline-primary btn-sm"><i class="bi bi-arrow-counterclockwise me-1"></i>Clear filters</a>
            <?php else : ?>
                <a href="/recipients/create" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>Add Recipient</a>
            <?php endif ?>
        </div>
    <?php else : ?>
        <div class="recipients-table-wrap">
            <table class="table table-hover recipients-table align-middle mb-0" aria-label="Recipients list">
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
                        <th class="recipients-th-sort <?= $sort === 'location' ? 'is-active' : '' ?>">
                            <a href="<?= $sortUrl('location') ?>">Location <span class="recipients-sort-icon"><i class="bi bi-arrow-down-up"></i></span></a>
                        </th>
                        <th class="recipients-th-sort <?= $sort === 'status' ? 'is-active' : '' ?>">
                            <a href="<?= $sortUrl('status') ?>">Status <span class="recipients-sort-icon"><i class="bi bi-arrow-down-up"></i></span></a>
                        </th>
                        <th>Campaign</th>
                        <th>Last Activity</th>
                        <?php if ($templateId) : ?>
                            <th>Sent</th>
                        <?php endif ?>
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
                                <div class="min-w-0">
                                    <p class="recipients-cell-name mb-0"><?= esc($r['name']) ?></p>
                                    <?php if (! empty($tagsByRecipient[$r['id']])) : ?>
                                        <div class="recipients-tag-chips">
                                            <?php foreach ($tagsByRecipient[$r['id']] as $tagName) : ?>
                                                <span class="recipients-tag-chip"><?= esc($tagName) ?></span>
                                            <?php endforeach ?>
                                        </div>
                                    <?php endif ?>
                                </div>
                            </div>
                        </td>
                        <td class="recipients-meta"><?= esc($r['email']) ?></td>
                        <td class="recipients-meta"><?= esc($r['company'] ?? '—') ?></td>
                        <td class="recipients-meta"><?= esc($r['location'] ?? '—') ?></td>
                        <td>
                            <span class="recipients-status recipients-status--<?= esc($r['status']) ?>">
                                <span class="recipients-status__dot"></span><?= esc(ucfirst($r['status'])) ?>
                            </span>
                        </td>
                        <td class="recipients-meta"><?= esc($lastCampaignByRecipient[$r['id']] ?? '—') ?></td>
                        <td class="recipients-meta">
                            <?php if (! empty($r['last_activity_at'])) : ?>
                                <?= esc(date('M j, Y', strtotime($r['last_activity_at']))) ?>
                            <?php else : ?>
                                <span class="text-body-secondary">Never contacted</span>
                            <?php endif ?>
                        </td>
                        <?php if ($templateId) : ?>
                            <td class="recipients-meta">
                                <?php if (! empty($r['last_sent_at'])) : ?>
                                    <span class="text-success"><i class="bi bi-check-circle-fill me-1"></i>Sent <?= esc(date('M j, Y', strtotime($r['last_sent_at']))) ?></span>
                                <?php else : ?>
                                    <span class="text-body-secondary">Not sent</span>
                                <?php endif ?>
                            </td>
                        <?php endif ?>
                        <td class="recipients-td-actions">
                            <a href="/recipients/view/<?= (int) $r['id'] ?>" class="recipients-row-action" aria-label="View recipient" title="View profile"><i class="bi bi-eye"></i></a>
                            <a href="/recipients/edit/<?= (int) $r['id'] ?>" class="recipients-row-action" aria-label="Edit recipient" title="Edit"><i class="bi bi-pencil"></i></a>
                            <div class="dropdown d-inline-block">
                                <button type="button" class="recipients-row-action" aria-label="More actions" title="More actions" data-bs-toggle="dropdown" aria-expanded="false">
                                    <i class="bi bi-three-dots-vertical"></i>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end recipients-actions-menu">
                                    <?php if ($r['status'] !== 'unsubscribed') : ?>
                                        <li><a class="dropdown-item" href="#" onclick="setRecipientStatus(<?= (int) $r['id'] ?>, 'unsubscribed'); return false;"><i class="bi bi-slash-circle me-2"></i>Unsubscribe</a></li>
                                    <?php else : ?>
                                        <li><a class="dropdown-item" href="#" onclick="setRecipientStatus(<?= (int) $r['id'] ?>, 'active'); return false;"><i class="bi bi-arrow-counterclockwise me-2"></i>Reactivate</a></li>
                                    <?php endif ?>
                                    <li><a class="dropdown-item text-danger" href="#" onclick="deleteRecipient(<?= (int) $r['id'] ?>); return false;"><i class="bi bi-trash me-2"></i>Delete</a></li>
                                </ul>
                            </div>
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
            <form method="get" action="/recipients" class="recipients-footer__pagesize">
                <?php foreach (array_filter($activeFilterParams(), static fn ($v) => $v !== null && $v !== '') as $key => $value) : ?>
                    <input type="hidden" name="<?= esc($key, 'attr') ?>" value="<?= esc($value, 'attr') ?>">
                <?php endforeach ?>
                <label for="perPageSelect" class="small text-body-secondary mb-0">Per page</label>
                <select id="perPageSelect" name="per_page" class="form-select form-select-sm w-auto" onchange="this.form.submit()">
                    <?php foreach ([25, 50, 100] as $option) : ?>
                        <option value="<?= $option ?>" <?= $pager->getPerPage() === $option ? 'selected' : '' ?>><?= $option ?></option>
                    <?php endforeach ?>
                </select>
            </form>
            <div class="recipients-footer__pager"><?= $pager->links() ?></div>
        </div>
    <?php endif ?>
</div>

<div class="modal fade" id="importModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Import Recipients</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <ol class="import-steps mb-4">
                    <li class="import-steps__item" data-step-indicator="upload">1. Upload</li>
                    <li class="import-steps__item" data-step-indicator="map">2. Map Columns</li>
                    <li class="import-steps__item" data-step-indicator="validate">3. Validate</li>
                    <li class="import-steps__item" data-step-indicator="done">4. Import</li>
                </ol>

                <div id="importAlert" class="alert alert-danger py-2 d-none"></div>

                <div class="import-step" data-step="upload">
                    <p class="small text-body-secondary mb-1">Any column headers are fine — you'll match them to fields next. Max 2MB.</p>
                    <p class="small mb-3"><a href="/samples/recipients-sample.csv" download>Download a sample CSV</a> to see an example format.</p>
                    <input type="file" id="importFileInput" accept=".csv" class="form-control">
                </div>

                <div class="import-step d-none" data-step="map">
                    <p class="small text-body-secondary mb-3">Match each recipient field to a column from your file. Name and Email are required.</p>
                    <div id="importMappingRows"></div>
                </div>

                <div class="import-step d-none" data-step="validate">
                    <div class="row g-3 mb-3" id="importValidateStats"></div>
                    <p class="small text-body-secondary mb-3" id="importValidateErrors"></p>
                    <label class="form-label small fw-semibold">If a row's email already exists</label>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="duplicateMode" id="dupSkip" value="skip" checked>
                        <label class="form-check-label" for="dupSkip">Skip it — leave the existing recipient unchanged</label>
                    </div>
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="radio" name="duplicateMode" id="dupUpdate" value="update">
                        <label class="form-check-label" for="dupUpdate">Update the existing recipient with this row's values</label>
                    </div>
                    <label for="importGroupName" class="form-label small fw-semibold">Add everyone in this file to a group (optional)</label>
                    <input type="text" id="importGroupName" class="form-control" maxlength="100" list="existingGroupNames" autocomplete="off" placeholder="Type an existing name or a new one">
                    <div class="form-text">Applies to every valid row, whether it's a new recipient or an existing one.</div>
                </div>

                <div class="import-step d-none" data-step="done">
                    <div id="importDoneSummary"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary d-none" id="importBackBtn">Back</button>
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal" id="importCancelBtn">Cancel</button>
                <button type="button" class="btn btn-outline-secondary d-none" data-bs-dismiss="modal" id="importCloseBtn">Close</button>
                <button type="button" class="btn btn-primary" id="importNextBtn">Next: Map Columns</button>
            </div>
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

<div class="modal fade" id="addToGroupModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="addToGroupForm" method="post" action="/groups/add-recipients">
                <?= csrf_field() ?>
                <div class="modal-header">
                    <h5 class="modal-title">Add to Group</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="small text-body-secondary mb-2"><span id="addToGroupCount">0</span> recipient(s) selected.</p>
                    <label for="addToGroupName" class="form-label">Group name</label>
                    <input type="text" name="group_name" id="addToGroupName" class="form-control" maxlength="100" required list="existingGroupNames" autocomplete="off" placeholder="Type an existing name or a new one">
                    <?php if (! empty($groups)) : ?>
                        <datalist id="existingGroupNames">
                            <?php foreach ($groups as $g) : ?>
                                <option value="<?= esc($g['name'], 'attr') ?>"></option>
                            <?php endforeach ?>
                        </datalist>
                    <?php endif ?>
                    <div class="form-text">An existing name adds to that group; a new one creates it first.</div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add to Group</button>
                </div>
            </form>
        </div>
    </div>
</div>

<form id="deleteForm" method="post" style="display:none;"><?= csrf_field() ?></form>
<form id="bulkDeleteForm" method="post" action="/recipients/bulk-delete" style="display:none;"><?= csrf_field() ?></form>
<form id="bulkStatusForm" method="post" action="/recipients/bulk-status" style="display:none;"><?= csrf_field() ?></form>
<form id="bulkExportForm" method="post" action="/recipients/export" style="display:none;"><?= csrf_field() ?></form>
<form id="statusForm" method="post" style="display:none;"><?= csrf_field() ?><input type="hidden" name="status" id="statusFormValue"></form>
<script>
function deleteRecipient(id) {
    confirmAction('Delete this recipient? This action cannot be undone.', function () {
        const form = document.getElementById('deleteForm');
        form.action = '/recipients/delete/' + id;
        form.submit();
    });
}
function setRecipientStatus(id, status) {
    const label = status === 'unsubscribed' ? 'unsubscribe' : 'reactivate';
    confirmAction('Are you sure you want to ' + label + ' this recipient?', function () {
        const form = document.getElementById('statusForm');
        form.action = '/recipients/status/' + id;
        document.getElementById('statusFormValue').value = status;
        form.submit();
    }, { confirmLabel: label.charAt(0).toUpperCase() + label.slice(1), confirmClass: 'btn-primary' });
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
function selectedRecipientIds() {
    return Array.from(document.querySelectorAll('.rowCheck:checked')).map(cb => cb.value);
}
function appendIds(form, ids) {
    ids.forEach(id => {
        const input = document.createElement('input');
        input.type = 'hidden'; input.name = 'ids[]'; input.value = id;
        form.appendChild(input);
    });
}
function bulkDeleteRecipients() {
    const ids = selectedRecipientIds();
    confirmAction('Delete ' + ids.length + ' selected recipient(s)? This action cannot be undone.', function () {
        const form = document.getElementById('bulkDeleteForm');
        appendIds(form, ids);
        form.submit();
    });
}
function bulkChangeStatus(status) {
    const ids = selectedRecipientIds();
    if (ids.length === 0) return;
    confirmAction('Change status of ' + ids.length + ' selected recipient(s) to "' + status + '"?', function () {
        const form = document.getElementById('bulkStatusForm');
        appendIds(form, ids);
        const statusInput = document.createElement('input');
        statusInput.type = 'hidden'; statusInput.name = 'status'; statusInput.value = status;
        form.appendChild(statusInput);
        form.submit();
    }, { confirmLabel: 'Update', confirmClass: 'btn-primary' });
}
function bulkExportRecipients() {
    const ids = selectedRecipientIds();
    if (ids.length === 0) return;
    const form = document.getElementById('bulkExportForm');
    appendIds(form, ids);
    form.submit();
}
function prepareAddToGroupModal() {
    const ids = selectedRecipientIds();
    const form = document.getElementById('addToGroupForm');
    form.querySelectorAll('input[name="ids[]"]').forEach((el) => el.remove());
    appendIds(form, ids);
    document.getElementById('addToGroupCount').textContent = ids.length;
}
function bulkEmailRecipients() {
    const ids = selectedRecipientIds();
    if (ids.length === 0) return;
    let url = '/compose?bulk_recipients=' + ids.join(',');
    const templateId = <?= json_encode($templateId) ?>;
    if (templateId) url += '&template_id=' + templateId;
    window.location.href = url;
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

// ---------- Import wizard ----------
(function () {
    const csrfTokenName = <?= json_encode(csrf_token()) ?>;
    let currentCsrfHash = <?= json_encode(csrf_hash()) ?>;

    const modalEl = document.getElementById('importModal');
    const alertBox = document.getElementById('importAlert');
    const nextBtn = document.getElementById('importNextBtn');
    const backBtn = document.getElementById('importBackBtn');
    const cancelBtn = document.getElementById('importCancelBtn');
    const closeBtn = document.getElementById('importCloseBtn');
    const fileInput = document.getElementById('importFileInput');

    const STEPS = ['upload', 'map', 'validate', 'done'];
    const FIELD_LABELS = { name: 'Name *', email: 'Email *', company: 'Company', location: 'Location', phone: 'Phone' };

    let state = { step: 'upload', token: null, headers: [], mapping: {}, didImportAnything: false };

    function showAlert(message) {
        alertBox.textContent = message;
        alertBox.classList.remove('d-none');
    }

    function showStep(step) {
        state.step = step;
        STEPS.forEach((s) => {
            document.querySelector('.import-step[data-step="' + s + '"]').classList.toggle('d-none', s !== step);
            document.querySelector('[data-step-indicator="' + s + '"]').classList.toggle('is-active', s === step);
        });
        backBtn.classList.toggle('d-none', step === 'upload' || step === 'done');
        cancelBtn.classList.toggle('d-none', step === 'done');
        closeBtn.classList.toggle('d-none', step !== 'done');
        nextBtn.classList.toggle('d-none', step === 'done');
        nextBtn.textContent = { upload: 'Next: Map Columns', map: 'Next: Validate', validate: 'Import' }[step] ?? '';
    }

    function renderMappingRows() {
        const container = document.getElementById('importMappingRows');
        container.innerHTML = '';
        Object.entries(FIELD_LABELS).forEach(([field, label]) => {
            const row = document.createElement('div');
            row.className = 'row align-items-center mb-2';
            const select = document.createElement('select');
            select.className = 'form-select form-select-sm';
            select.dataset.mapField = field;

            const none = document.createElement('option');
            none.value = '';
            none.textContent = '— Do not import —';
            select.appendChild(none);

            state.headers.forEach((header, index) => {
                const opt = document.createElement('option');
                opt.value = String(index);
                opt.textContent = header || '(column ' + (index + 1) + ')';
                if (state.mapping[field] === index) opt.selected = true;
                select.appendChild(opt);
            });

            row.innerHTML = '<div class="col-4">' + label + '</div><div class="col-8"></div>';
            row.querySelector('.col-8').appendChild(select);
            container.appendChild(row);
        });
    }

    function readMapping() {
        const mapping = {};
        document.querySelectorAll('#importMappingRows [data-map-field]').forEach((select) => {
            mapping[select.dataset.mapField] = select.value === '' ? null : parseInt(select.value, 10);
        });
        return mapping;
    }

    function renderValidateStats(summary) {
        document.getElementById('importValidateStats').innerHTML =
            '<div class="col-4"><div class="import-stat import-stat--green"><strong>' + summary.imported + '</strong><span>Valid new</span></div></div>' +
            '<div class="col-4"><div class="import-stat import-stat--amber"><strong>' + summary.duplicates + '</strong><span>Duplicates</span></div></div>' +
            '<div class="col-4"><div class="import-stat import-stat--red"><strong>' + summary.invalid + '</strong><span>Invalid</span></div></div>';

        const errEl = document.getElementById('importValidateErrors');
        errEl.textContent = (summary.errors && summary.errors.length)
            ? summary.errors.slice(0, 5).join(' · ') + (summary.errors.length > 5 ? ' …' : '')
            : '';
    }

    function renderDoneSummary(summary) {
        document.getElementById('importDoneSummary').innerHTML =
            '<p class="mb-1"><strong>' + summary.imported + '</strong> new recipient(s) imported.</p>' +
            '<p class="mb-1"><strong>' + summary.updated + '</strong> existing recipient(s) updated.</p>' +
            '<p class="mb-1"><strong>' + (summary.duplicates - summary.updated) + '</strong> duplicate(s) skipped.</p>' +
            '<p class="mb-0"><strong>' + summary.invalid + '</strong> row(s) invalid and skipped.</p>';
    }

    async function postForm(url, fields) {
        const body = new URLSearchParams();
        Object.entries(fields).forEach(([key, value]) => { if (value !== null) body.set(key, value); });
        body.set(csrfTokenName, currentCsrfHash);
        const response = await fetch(url, {
            method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: body.toString(),
        });
        const data = await response.json();
        if (data.csrf_hash) currentCsrfHash = data.csrf_hash;
        return data;
    }

    nextBtn.addEventListener('click', async () => {
        alertBox.classList.add('d-none');

        if (state.step === 'upload') {
            if (!fileInput.files[0]) { showAlert('Please choose a CSV file.'); return; }
            const body = new FormData();
            body.append('csv', fileInput.files[0]);
            body.append(csrfTokenName, currentCsrfHash);
            nextBtn.disabled = true;
            try {
                const response = await fetch('/recipients/import/upload', { method: 'POST', body });
                const data = await response.json();
                if (data.csrf_hash) currentCsrfHash = data.csrf_hash;
                if (!data.success) { showAlert(data.message); return; }
                state.token = data.token;
                state.headers = data.headers;
                state.mapping = data.suggestedMapping;
                renderMappingRows();
                showStep('map');
            } finally {
                nextBtn.disabled = false;
            }
            return;
        }

        if (state.step === 'map') {
            const mapping = readMapping();
            if (mapping.email === null) { showAlert('Map a column to Email before continuing.'); return; }
            if (mapping.name === null) { showAlert('Map a column to Name before continuing.'); return; }
            state.mapping = mapping;
            nextBtn.disabled = true;
            try {
                const data = await postForm('/recipients/import/validate', {
                    token: state.token, map_name: mapping.name, map_email: mapping.email,
                    map_company: mapping.company, map_location: mapping.location, map_phone: mapping.phone,
                });
                if (!data.success) { showAlert(data.message); return; }
                renderValidateStats(data.summary);
                showStep('validate');
            } finally {
                nextBtn.disabled = false;
            }
            return;
        }

        if (state.step === 'validate') {
            const duplicateMode = document.querySelector('input[name="duplicateMode"]:checked').value;
            const groupName = document.getElementById('importGroupName').value.trim();
            nextBtn.disabled = true;
            try {
                const data = await postForm('/recipients/import/commit', {
                    token: state.token, map_name: state.mapping.name, map_email: state.mapping.email,
                    map_company: state.mapping.company, map_location: state.mapping.location, map_phone: state.mapping.phone,
                    duplicate_mode: duplicateMode, group_name: groupName,
                });
                if (!data.success) { showAlert(data.message); return; }
                state.didImportAnything = true;
                renderDoneSummary(data.summary);
                showStep('done');
            } finally {
                nextBtn.disabled = false;
            }
        }
    });

    backBtn.addEventListener('click', () => {
        if (state.step === 'map') showStep('upload');
        else if (state.step === 'validate') { renderMappingRows(); showStep('map'); }
    });

    modalEl.addEventListener('hidden.bs.modal', () => {
        const shouldReload = state.didImportAnything;
        fileInput.value = '';
        alertBox.classList.add('d-none');
        state = { step: 'upload', token: null, headers: [], mapping: {}, didImportAnything: false };
        showStep('upload');
        if (shouldReload) window.location.reload();
    });

    showStep('upload');
})();
</script>

<?= $this->endSection() ?>
