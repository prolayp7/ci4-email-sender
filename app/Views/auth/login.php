<!doctype html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#4f46e5">
    <title>Sign in — Email Manager</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"
          integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/@fontsource/inter@5.1.0/400.css" rel="stylesheet"
          integrity="sha384-plvHPamCpQvGXYKgEdJAz1ijiAhzXR0PwuixVr/ixFVvnnIeWe7p7XIN/t1lVuOo" crossorigin="anonymous">
    <link href="https://cdn.jsdelivr.net/npm/@fontsource/inter@5.1.0/500.css" rel="stylesheet"
          integrity="sha384-RLH5Taa3HV4nScDW/gsIx5FTDCCAK3y/YBXOQTuU7Xl6ugp8JU4/kjPhNdLWGenF" crossorigin="anonymous">
    <link href="https://cdn.jsdelivr.net/npm/@fontsource/inter@5.1.0/600.css" rel="stylesheet"
          integrity="sha384-tTxwv+V3obd48y7dk5weqcsMkR7W1jPewE84W/2Yv/mGVTb4hJ+vBydBFEkFWNAS" crossorigin="anonymous">
    <link href="https://cdn.jsdelivr.net/npm/@fontsource/inter@5.1.0/700.css" rel="stylesheet"
          integrity="sha384-ob21+geQweTMFxw49brwjVaw9Qz0ggOJiqVuYmFIHT6avMdxBq4VPfmIVuMOnDZY" crossorigin="anonymous">
    <link href="https://cdn.jsdelivr.net/npm/@fontsource/inter@5.1.0/800.css" rel="stylesheet"
          integrity="sha384-bEfteW+1y3/AmwtTsisGm08Kg6W+e/ITGzEcK7h0WjUzgqlgYQzeKBywKRfr+p29" crossorigin="anonymous">
    <link href="/assets/css/orchid.css?v=<?= @filemtime(FCPATH . 'assets/css/orchid.css') ?>" rel="stylesheet">
    <link href="/assets/css/pages/auth.css?v=<?= @filemtime(FCPATH . 'assets/css/pages/auth.css') ?>" rel="stylesheet">
</head>
<body class="orchid-body auth-body">

<button class="auth-theme-toggle" type="button" data-orchid-theme-toggle aria-label="Toggle theme">
    <i class="bi bi-moon-stars-fill"></i>
    <i class="bi bi-sun-fill"></i>
</button>

<div class="auth-shell">

    <!-- ================== LEFT HERO ================== -->
    <aside class="auth-hero" aria-hidden="true">
        <span class="auth-hero__brand">
            <span class="auth-hero__brand-mark login-logo-mark">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M3 8L11.1 13.4C11.6 13.73 12.4 13.73 12.9 13.4L21 8" stroke="#fff" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                    <rect x="3" y="5" width="18" height="14" rx="2" stroke="#fff" stroke-width="1.8"/>
                </svg>
            </span>
            <span>Email Manager</span>
        </span>

        <div class="auth-hero__body">
            <span class="auth-hero__eyebrow"><i class="bi bi-stars"></i> Your outbound email, in one place</span>
            <h1 class="auth-hero__title">Welcome back.</h1>
            <p class="auth-hero__lead">Sign in to send campaigns, manage recipients and templates, and track delivery through your own SMTP.</p>

            <ul class="auth-hero__features">
                <li><i class="bi bi-send-check-fill"></i><span>Reliable delivery through your own SMTP credentials</span></li>
                <li><i class="bi bi-people-fill"></i><span>Import and manage recipient lists from CSV</span></li>
                <li><i class="bi bi-file-earmark-richtext-fill"></i><span>Reusable templates with live placeholder previews</span></li>
            </ul>
        </div>
    </aside>

    <!-- ================== RIGHT FORM ================== -->
    <section class="auth-panel">
        <div class="auth-panel__top">
            <span></span>
            <span class="auth-panel__brand-sm">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M3 8L11.1 13.4C11.6 13.73 12.4 13.73 12.9 13.4L21 8" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                    <rect x="3" y="5" width="18" height="14" rx="2" stroke="currentColor" stroke-width="1.8"/>
                </svg>
                <span>Email Manager</span>
            </span>
        </div>

        <div class="auth-panel__body">
            <div class="auth-form">
                <div class="auth-form__eyebrow">Sign in</div>
                <h2 class="auth-form__title">Welcome back</h2>
                <p class="auth-form__subtitle">Sign in to manage your recipients, templates, and email delivery.</p>

                <?php if (session()->getFlashdata('error')) : ?>
                    <div class="alert alert-danger py-2"><?= esc(session()->getFlashdata('error')) ?></div>
                <?php endif ?>

                <form method="post" action="/login" novalidate>
                    <?= csrf_field() ?>

                    <div class="auth-field">
                        <label for="loginEmail" class="form-label">Email address</label>
                        <input type="email" class="form-control" id="loginEmail" name="email"
                               placeholder="you@example.com" autocomplete="email"
                               value="<?= esc(old('email') ?? '') ?>" required autofocus>
                    </div>

                    <div class="auth-field">
                        <label for="loginPassword" class="form-label">Password</label>
                        <input type="password" class="form-control" id="loginPassword" name="password"
                               autocomplete="current-password" data-auth-password required>
                        <button type="button" class="auth-field__toggle" data-auth-password-toggle="loginPassword" aria-label="Show password">
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>

                    <button type="submit" class="auth-btn-primary">
                        <span>Sign in</span>
                        <i class="bi bi-arrow-right"></i>
                    </button>
                </form>
            </div>
        </div>

        <footer class="auth-panel__footer">
            &copy; <?= date('Y') ?> Email Manager
        </footer>
    </section>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous" defer></script>
<script src="/assets/js/theme.js?v=<?= @filemtime(FCPATH . 'assets/js/theme.js') ?>" defer></script>
<script src="/assets/js/pages/auth.js?v=<?= @filemtime(FCPATH . 'assets/js/pages/auth.js') ?>" defer></script>
</body>
</html>
