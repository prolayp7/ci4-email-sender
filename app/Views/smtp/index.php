<?= $this->extend('layout/main') ?>
<?= $this->section('content') ?>
<?php if (session()->getFlashdata('success')) : ?><div class="alert alert-success"><?= esc(session()->getFlashdata('success')) ?></div><?php endif ?>
<?php if (session()->getFlashdata('error')) : ?><div class="alert alert-danger"><?= esc(session()->getFlashdata('error')) ?></div><?php endif ?>
<div class="row g-4">
    <div class="col-md-7">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="mb-3 d-flex gap-2">
                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="applyPreset('smtp.gmail.com',587,'tls')">Gmail</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="applyPreset('smtp.office365.com',587,'tls')">Microsoft 365</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="applyPreset('',587,'tls')">Custom</button>
                </div>
                <form method="post" action="/smtp">
                    <?= csrf_field() ?>
                    <div class="mb-3"><label class="form-label">Label</label><input type="text" name="label" class="form-control" value="<?= esc($config['label'] ?? '') ?>" required></div>
                    <div class="row">
                        <div class="col-8 mb-3"><label class="form-label">Host</label><input type="text" name="host" id="smtpHost" class="form-control" value="<?= esc($config['host'] ?? '') ?>" required></div>
                        <div class="col-4 mb-3"><label class="form-label">Port</label><input type="number" name="port" id="smtpPort" class="form-control" value="<?= esc((string) ($config['port'] ?? 587)) ?>" required></div>
                    </div>
                    <div class="mb-3"><label class="form-label">Encryption</label>
                        <select name="encryption" id="smtpEncryption" class="form-select">
                            <option value="tls" <?= ($config['encryption'] ?? '') === 'tls' ? 'selected' : '' ?>>TLS</option>
                            <option value="ssl" <?= ($config['encryption'] ?? '') === 'ssl' ? 'selected' : '' ?>>SSL</option>
                        </select>
                    </div>
                    <div class="mb-3"><label class="form-label">Username</label><input type="text" name="username" class="form-control" value="<?= esc($config['username'] ?? '') ?>" required></div>
                    <div class="mb-3"><label class="form-label">Password</label><input type="password" name="password" class="form-control" placeholder="<?= $config ? 'Enter to replace saved password' : '' ?>" required></div>
                    <div class="mb-3"><label class="form-label">From Email</label><input type="email" name="from_email" class="form-control" value="<?= esc($config['from_email'] ?? '') ?>" required></div>
                    <div class="mb-3"><label class="form-label">From Name</label><input type="text" name="from_name" class="form-control" value="<?= esc($config['from_name'] ?? '') ?>" required></div>
                    <button type="submit" class="btn btn-primary">Save SMTP Configuration</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-md-5">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <h6>Test SMTP Connection</h6>
                <div class="mb-2"><input type="email" id="testEmailInput" class="form-control" placeholder="Send test to..."></div>
                <button type="button" class="btn btn-outline-primary" onclick="sendTestEmail()">Send Test Email</button>
                <div id="testResult" class="mt-2 small"></div>
            </div>
        </div>
    </div>
</div>
<script>
function applyPreset(host, port, enc) {
    document.getElementById('smtpHost').value = host;
    document.getElementById('smtpPort').value = port;
    document.getElementById('smtpEncryption').value = enc;
}
function sendTestEmail() {
    const email = document.getElementById('testEmailInput').value;
    const resultEl = document.getElementById('testResult');
    resultEl.textContent = 'Sending...';
    const body = new URLSearchParams();
    body.set('test_email', email);
    body.set(<?= json_encode(csrf_token()) ?>, <?= json_encode(csrf_hash()) ?>);
    fetch('/smtp/test', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: body.toString(),
    }).then(r => r.json()).then(data => {
        resultEl.textContent = data.message;
        resultEl.className = 'mt-2 small ' + (data.success ? 'text-success' : 'text-danger');
        showToast(data.message, data.success ? 'success' : 'danger');
    });
}
</script>
<?= $this->endSection() ?>
