<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= esc($title ?? 'Email Manager') ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"
          integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <script src="https://cdn.jsdelivr.net/npm/lucide@0.454.0/dist/umd/lucide.min.js" defer
            integrity="sha384-m/CoPp6wBQz6MoZXP+VveuxfvSx0NGXiQyyakzXVOVHgG1fP5bM/UiO4pSNPV6PT" crossorigin="anonymous"></script>
</head>
<body class="bg-light">
<div class="d-flex">
    <div class="d-none d-md-flex bg-white border-end p-3" style="width:240px; min-height:100vh;">
        <?= $this->include('layout/partials/sidebar') ?>
    </div>
    <div class="offcanvas offcanvas-start" tabindex="-1" id="mobileSidebar" style="width:240px;">
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
<script>if (window.lucide) lucide.createIcons();</script>
</body>
</html>
