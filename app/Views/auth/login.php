<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sign in — Email Manager</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"
          integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link href="https://cdn.jsdelivr.net/npm/@fontsource/inter@5.1.0/400.css" rel="stylesheet"
          integrity="sha384-plvHPamCpQvGXYKgEdJAz1ijiAhzXR0PwuixVr/ixFVvnnIeWe7p7XIN/t1lVuOo" crossorigin="anonymous">
    <link href="https://cdn.jsdelivr.net/npm/@fontsource/inter@5.1.0/500.css" rel="stylesheet"
          integrity="sha384-RLH5Taa3HV4nScDW/gsIx5FTDCCAK3y/YBXOQTuU7Xl6ugp8JU4/kjPhNdLWGenF" crossorigin="anonymous">
    <link href="https://cdn.jsdelivr.net/npm/@fontsource/inter@5.1.0/600.css" rel="stylesheet"
          integrity="sha384-tTxwv+V3obd48y7dk5weqcsMkR7W1jPewE84W/2Yv/mGVTb4hJ+vBydBFEkFWNAS" crossorigin="anonymous">
    <link href="https://cdn.jsdelivr.net/npm/@fontsource/inter@5.1.0/700.css" rel="stylesheet"
          integrity="sha384-ob21+geQweTMFxw49brwjVaw9Qz0ggOJiqVuYmFIHT6avMdxBq4VPfmIVuMOnDZY" crossorigin="anonymous">
    <link href="/assets/css/theme.css?v=<?= @filemtime(FCPATH . 'assets/css/theme.css') ?>" rel="stylesheet">
</head>
<body class="d-flex align-items-center" style="min-height:100vh;">
<div class="container" style="max-width:400px;">
    <div class="card">
        <div class="card-body p-4">
            <div class="text-center mb-4">
                <div class="login-logo-mark mx-auto mb-2">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M3 8L11.1 13.4C11.6 13.73 12.4 13.73 12.9 13.4L21 8" stroke="#fff" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                        <rect x="3" y="5" width="18" height="14" rx="2" stroke="#fff" stroke-width="1.8"/>
                    </svg>
                </div>
                <div class="fw-bold fs-5">Email Manager</div>
            </div>
            <h1 class="h5 text-center mb-1">Welcome back</h1>
            <p class="text-muted small text-center mb-4">Sign in to manage your recipients, templates, and email delivery.</p>
            <?php if (session()->getFlashdata('error')) : ?>
                <div class="alert alert-danger py-2"><?= esc(session()->getFlashdata('error')) ?></div>
            <?php endif ?>
            <form method="post" action="/login">
                <?= csrf_field() ?>
                <div class="mb-3">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-control form-control-filled" placeholder="you@example.com" value="<?= esc(old('email') ?? '') ?>" required autofocus>
                </div>
                <div class="mb-3">
                    <label class="form-label">Password</label>
                    <div class="input-group">
                        <input type="password" name="password" id="passwordInput" class="form-control form-control-filled" required>
                        <button type="button" class="btn btn-filled-addon" onclick="togglePassword()" aria-label="Show password">
                            <svg id="eyeIcon" width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7Z" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
                                <circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="1.6"/>
                            </svg>
                        </button>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary w-100 mt-1">Login</button>
            </form>
        </div>
    </div>
</div>
<script>
function togglePassword() {
    const input = document.getElementById('passwordInput');
    const isHidden = input.type === 'password';
    input.type = isHidden ? 'text' : 'password';
    document.querySelector('[onclick="togglePassword()"]').setAttribute('aria-label', isHidden ? 'Hide password' : 'Show password');
}
</script>
</body>
</html>
