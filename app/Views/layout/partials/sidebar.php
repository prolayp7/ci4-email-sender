<?php
$nav = [
    ['label' => 'Dashboard', 'icon' => 'layout-dashboard', 'href' => '/dashboard'],
    ['label' => 'Recipients', 'icon' => 'users', 'href' => '/recipients'],
    ['label' => 'Email Templates', 'icon' => 'file-text', 'href' => '/templates'],
    ['label' => 'Compose Email', 'icon' => 'send', 'href' => '/compose'],
    ['label' => 'Email History', 'icon' => 'history', 'href' => '/emails'],
    ['label' => 'SMTP Settings', 'icon' => 'server', 'href' => '/smtp'],
    ['label' => 'Settings', 'icon' => 'settings', 'href' => '/settings'],
];
$current = uri_string();
// Rendered twice: once as the static desktop sidebar, once inside the mobile offcanvas (see layout/main.php).
?>
<div class="d-flex flex-column h-100">
    <div class="fw-bold fs-5 mb-4 px-2">Email Manager</div>
    <div class="flex-grow-1">
        <?php foreach ($nav as $item) : ?>
            <a href="<?= esc($item['href']) ?>"
               class="d-flex align-items-center gap-2 px-2 py-2 rounded text-decoration-none mb-1 <?= str_starts_with($current, ltrim($item['href'], '/')) ? 'bg-primary-subtle text-primary fw-medium' : 'text-body' ?>">
                <i data-lucide="<?= esc($item['icon']) ?>" width="18" height="18"></i>
                <span><?= esc($item['label']) ?></span>
            </a>
        <?php endforeach ?>
    </div>
    <hr>
    <a href="#" class="d-flex align-items-center gap-2 px-2 py-2 text-body text-decoration-none">
        <i data-lucide="help-circle" width="18" height="18"></i> Help
    </a>
    <div class="px-2 py-2 text-truncate small text-muted"><?= esc(session()->get('user_name')) ?></div>
    <form method="post" action="/logout">
        <?= csrf_field() ?>
        <button type="submit" class="btn btn-sm btn-outline-secondary w-100 d-flex align-items-center justify-content-center gap-2">
            <i data-lucide="log-out" width="16" height="16"></i> Logout
        </button>
    </form>
</div>
