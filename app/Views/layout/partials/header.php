<header class="orchid-header" role="banner">
    <div class="orchid-header__left">
        <button class="btn btn-icon orchid-header__toggle" type="button" data-orchid-sidebar-toggle aria-label="Toggle sidebar" aria-controls="orchidSidebar" aria-expanded="false">
            <i class="bi bi-list"></i>
        </button>
        <div>
            <h1 class="h6 mb-0"><?= esc($title ?? 'Dashboard') ?></h1>
            <?php if (! empty($breadcrumb)) : ?>
                <nav class="small text-body-secondary"><?= esc($breadcrumb) ?></nav>
            <?php endif ?>
        </div>
    </div>

    <div class="orchid-header__right">
        <button class="btn btn-icon orchid-theme-toggle" type="button" data-orchid-theme-toggle aria-label="Toggle color theme">
            <i class="bi bi-sun-fill orchid-theme-toggle__sun"></i>
            <i class="bi bi-moon-stars-fill orchid-theme-toggle__moon"></i>
        </button>

        <div class="dropdown">
            <button class="btn orchid-profile-btn" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="User profile">
                <span class="avatar avatar-sm bg-primary-subtle text-primary fw-semibold"><?= esc(strtoupper(substr((string) session()->get('user_name'), 0, 1)) ?: '?') ?></span>
                <span class="orchid-profile-btn__meta d-none d-lg-flex">
                    <span class="orchid-profile-btn__name"><?= esc(session()->get('user_name')) ?></span>
                    <span class="orchid-profile-btn__role"><?= esc(ucfirst((string) session()->get('user_role'))) ?></span>
                </span>
                <i class="bi bi-chevron-down d-none d-lg-inline"></i>
            </button>
            <ul class="dropdown-menu dropdown-menu-end orchid-dropdown orchid-dropdown--profile">
                <li class="orchid-dropdown__header">
                    <div class="d-flex align-items-center gap-2">
                        <span class="avatar bg-primary-subtle text-primary fw-semibold"><?= esc(strtoupper(substr((string) session()->get('user_name'), 0, 1)) ?: '?') ?></span>
                        <div>
                            <p class="mb-0 fw-semibold"><?= esc(session()->get('user_name')) ?></p>
                            <small class="text-body-secondary"><?= esc(session()->get('user_email')) ?></small>
                        </div>
                    </div>
                </li>
                <li><a class="dropdown-item" href="/settings"><i class="bi bi-gear"></i>Settings</a></li>
                <li><a class="dropdown-item" href="/help"><i class="bi bi-life-preserver"></i>Help</a></li>
                <li><hr class="dropdown-divider"></li>
                <li>
                    <form method="post" action="/logout">
                        <?= csrf_field() ?>
                        <button type="submit" class="dropdown-item text-danger"><i class="bi bi-box-arrow-right"></i>Logout</button>
                    </form>
                </li>
            </ul>
        </div>
    </div>
</header>
