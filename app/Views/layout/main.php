<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= esc($title ?? 'Email Manager') ?></title>
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
    <script src="https://cdn.jsdelivr.net/npm/lucide@0.454.0/dist/umd/lucide.min.js" defer
            integrity="sha384-m/CoPp6wBQz6MoZXP+VveuxfvSx0NGXiQyyakzXVOVHgG1fP5bM/UiO4pSNPV6PT" crossorigin="anonymous"></script>
</head>
<body>
<div class="d-flex">
    <div class="d-none d-md-flex bg-white p-3" style="width:var(--theme-sidebar-width); min-height:100vh; box-shadow: 1px 0 3px 0 rgba(25,24,34,.1);">
        <?= $this->include('layout/partials/sidebar') ?>
    </div>
    <div class="offcanvas offcanvas-start bg-white" tabindex="-1" id="mobileSidebar" style="width:264px;">
        <div class="offcanvas-body p-3">
            <?= $this->include('layout/partials/sidebar') ?>
        </div>
    </div>
    <div class="flex-grow-1" style="min-width:0;">
        <?= $this->include('layout/partials/header') ?>
        <main class="p-4">
            <?= $this->renderSection('content') ?>
        </main>
    </div>
</div>
<?= $this->include('layout/partials/toast') ?>
<?= $this->include('layout/partials/confirm_dialog') ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
<script src="/assets/js/app.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (window.lucide) lucide.createIcons();
});
</script>
</body>
</html>
