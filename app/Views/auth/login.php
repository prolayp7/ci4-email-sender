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
    <div class="text-center mb-4">
        <div class="app-sidebar-brand fs-3">Email Manager</div>
    </div>
    <div class="card">
        <div class="card-body p-4">
            <h1 class="h5 mb-1">Sign in</h1>
            <p class="text-muted small mb-3">Enter your credentials to access the admin panel.</p>
            <?php if (session()->getFlashdata('error')) : ?>
                <div class="alert alert-danger py-2"><?= esc(session()->getFlashdata('error')) ?></div>
            <?php endif ?>
            <form method="post" action="/login">
                <?= csrf_field() ?>
                <div class="mb-3">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-control" value="<?= esc(old('email') ?? '') ?>" required autofocus>
                </div>
                <div class="mb-3">
                    <label class="form-label">Password</label>
                    <input type="password" name="password" class="form-control" required>
                </div>
                <button type="submit" class="btn btn-primary w-100">Login</button>
            </form>
        </div>
    </div>
</div>
</body>
</html>
