<!doctype html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#4f46e5">
    <title><?= esc($title ?? 'Email Manager') ?></title>
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
    <?php // theme.css stays loaded (before orchid.css) so pages not yet migrated to
    // orchid classes (kpi-icon, badge-soft-*) don't lose their only styling. Orchid's
    // rules win on shared classes like .card/.btn-primary since it loads after. ?>
    <link href="/assets/css/theme.css?v=<?= @filemtime(FCPATH . 'assets/css/theme.css') ?>" rel="stylesheet">
    <link href="/assets/css/orchid.css?v=<?= @filemtime(FCPATH . 'assets/css/orchid.css') ?>" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/lucide@0.454.0/dist/umd/lucide.min.js" defer
            integrity="sha384-m/CoPp6wBQz6MoZXP+VveuxfvSx0NGXiQyyakzXVOVHgG1fP5bM/UiO4pSNPV6PT" crossorigin="anonymous"></script>
</head>
<body class="orchid-body">

<div class="orchid-backdrop" data-orchid-sidebar-close></div>

<aside class="orchid-sidebar" id="orchidSidebar" aria-label="Primary navigation">
    <?= $this->include('layout/partials/sidebar') ?>
</aside>

<div class="orchid-app">
    <?= $this->include('layout/partials/header') ?>
    <main class="orchid-main" id="orchid-main" tabindex="-1">
        <div class="container-fluid px-3 px-lg-4 py-4">
            <?= $this->renderSection('content') ?>
        </div>
    </main>
</div>

<?= $this->include('layout/partials/toast') ?>
<?= $this->include('layout/partials/confirm_dialog') ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
<script src="/assets/js/theme.js?v=<?= @filemtime(FCPATH . 'assets/js/theme.js') ?>" defer></script>
<script src="/assets/js/sidebar.js?v=<?= @filemtime(FCPATH . 'assets/js/sidebar.js') ?>" defer></script>
<script src="/assets/js/app.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (window.lucide) lucide.createIcons();
});
</script>
</body>
</html>
