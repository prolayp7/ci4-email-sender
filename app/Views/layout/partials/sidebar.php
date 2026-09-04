<?php
$nav = [
    ['label' => 'Dashboard', 'icon' => 'bi-speedometer2', 'href' => '/dashboard'],
    ['label' => 'Recipients', 'icon' => 'bi-people', 'href' => '/recipients'],
    ['label' => 'Email Templates', 'icon' => 'bi-file-earmark-text', 'href' => '/templates'],
    ['label' => 'Compose Email', 'icon' => 'bi-send', 'href' => '/compose'],
    ['label' => 'Email History', 'icon' => 'bi-clock-history', 'href' => '/emails'],
    ['label' => 'Trash', 'icon' => 'bi-trash3', 'href' => '/emails/trash'],
    ['label' => 'SMTP Settings', 'icon' => 'bi-server', 'href' => '/smtp'],
    ['label' => 'Settings', 'icon' => 'bi-gear', 'href' => '/settings'],
];
$current = uri_string();
$isActive = static function (string $href) use ($current): bool {
    $path = ltrim($href, '/');
    if ($path === 'emails') {
        return $current === 'emails' || preg_match('#^emails/\d+$#', $current) === 1;
    }
    return str_starts_with($current, $path);
};
?>
<div class="orchid-sidebar__brand">
    <a href="/dashboard" class="orchid-brand" aria-label="Email Manager home">
        <span class="orchid-brand__logo login-logo-mark" aria-hidden="true">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M3 8L11.1 13.4C11.6 13.73 12.4 13.73 12.9 13.4L21 8" stroke="#fff" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                <rect x="3" y="5" width="18" height="14" rx="2" stroke="#fff" stroke-width="1.8"/>
            </svg>
        </span>
        <span class="orchid-brand__text">Email Manager</span>
    </a>
    <button class="btn btn-sm btn-icon orchid-sidebar__close d-lg-none" type="button" data-orchid-sidebar-close aria-label="Close sidebar">
        <i class="bi bi-x-lg"></i>
    </button>
</div>

<nav class="orchid-sidebar__nav" aria-label="Main">
    <div class="orchid-nav-section">
        <span class="orchid-nav-section__label">Main</span>
        <ul class="orchid-nav">
            <?php foreach ($nav as $item) : ?>
                <?php $active = $isActive($item['href']); ?>
                <li>
                    <a href="<?= esc($item['href']) ?>"
                       class="orchid-nav__link <?= $active ? 'active' : '' ?>"
                       <?= $active ? 'aria-current="page"' : '' ?>>
                        <i class="bi <?= esc($item['icon']) ?>"></i><span><?= esc($item['label']) ?></span>
                    </a>
                </li>
            <?php endforeach ?>
        </ul>
    </div>

    <div class="orchid-nav-section">
        <span class="orchid-nav-section__label">Support</span>
        <ul class="orchid-nav">
            <li>
                <a href="/help" class="orchid-nav__link <?= str_starts_with($current, 'help') ? 'active' : '' ?>">
                    <i class="bi bi-question-circle"></i><span>Help</span>
                </a>
            </li>
        </ul>
    </div>
</nav>

<div class="orchid-sidebar__footer p-3 border-top">
    <div class="d-flex align-items-center gap-2 mb-2">
        <span class="avatar avatar-sm bg-primary-subtle text-primary fw-semibold flex-shrink-0"><?= esc(strtoupper(substr((string) session()->get('user_name'), 0, 1)) ?: '?') ?></span>
        <div class="text-truncate small orchid-sidebar__footer-meta">
            <div class="fw-semibold text-truncate"><?= esc(session()->get('user_name')) ?></div>
            <div class="text-body-secondary text-truncate"><?= esc(session()->get('user_email')) ?></div>
        </div>
    </div>
    <form method="post" action="/logout">
        <?= csrf_field() ?>
        <button type="submit" class="btn btn-sm btn-outline-secondary w-100 d-flex align-items-center justify-content-center gap-2">
            <i class="bi bi-box-arrow-right"></i> <span class="orchid-sidebar__footer-meta">Logout</span>
        </button>
    </form>
</div>
