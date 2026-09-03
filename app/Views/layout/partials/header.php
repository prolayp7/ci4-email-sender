<header class="d-flex justify-content-between align-items-center border-bottom bg-white px-4 py-3">
    <div class="d-flex align-items-center gap-3">
        <button class="btn btn-outline-secondary d-md-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#mobileSidebar">
            <i data-lucide="menu" width="18" height="18"></i>
        </button>
        <div>
            <h1 class="h5 mb-0"><?= esc($title ?? 'Dashboard') ?></h1>
            <?php if (! empty($breadcrumb)) : ?>
                <nav class="small text-muted"><?= esc($breadcrumb) ?></nav>
            <?php endif ?>
        </div>
    </div>
    <div class="d-flex align-items-center gap-3">
        <div class="dropdown">
            <button class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                <?= esc(session()->get('user_name')) ?>
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                <li><a class="dropdown-item" href="/settings">Profile</a></li>
                <li><form method="post" action="/logout"><?= csrf_field() ?><button class="dropdown-item">Logout</button></form></li>
            </ul>
        </div>
    </div>
</header>
