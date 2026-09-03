<?= $this->extend('layout/main') ?>
<?= $this->section('content') ?>
<?php if (session()->getFlashdata('success')) : ?><div class="alert alert-success"><?= esc(session()->getFlashdata('success')) ?></div><?php endif ?>
<?php if (session()->getFlashdata('error')) : ?><div class="alert alert-danger"><?= esc(session()->getFlashdata('error')) ?></div><?php endif ?>
<div class="row g-4">
    <div class="col-md-7">
        <div class="card">
            <div class="card-body">
                <div class="mb-3 d-flex gap-2">
                    <button type="button" class="btn btn-sm btn-outline-primary provider-btn" data-provider="gmail" onclick="selectProvider(this,'gmail','smtp.gmail.com',587,'tls')">Gmail</button>
                    <button type="button" class="btn btn-sm btn-outline-primary provider-btn" data-provider="microsoft365" onclick="selectProvider(this,'microsoft365','smtp.office365.com',587,'tls')">Microsoft 365</button>
                    <button type="button" class="btn btn-sm btn-outline-primary provider-btn" data-provider="custom" onclick="selectProvider(this,'custom','',587,'tls')">Custom</button>
                </div>
                <form method="post" action="/smtp">
                    <?= csrf_field() ?>
                    <input type="hidden" name="provider" id="smtpProvider" value="<?= esc($activeProvider) ?>">
                    <div class="mb-3"><label class="form-label">Label</label><input type="text" name="label" id="smtpLabel" class="form-control" value="<?= esc($config['label'] ?? '') ?>" required></div>
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
                    <div class="mb-3"><label class="form-label">Username</label><input type="text" name="username" id="smtpUsername" class="form-control" value="<?= esc($config['username'] ?? '') ?>" required></div>
                    <div class="mb-3"><label class="form-label">Password</label><input type="password" name="password" id="smtpPassword" class="form-control" placeholder="<?= $config ? 'Enter to replace saved password' : '' ?>" required></div>
                    <div class="mb-3"><label class="form-label">From Email</label><input type="email" name="from_email" id="smtpFromEmail" class="form-control" value="<?= esc($config['from_email'] ?? '') ?>" required></div>
                    <div class="mb-3"><label class="form-label">From Name</label><input type="text" name="from_name" id="smtpFromName" class="form-control" value="<?= esc($config['from_name'] ?? '') ?>" required></div>
                    <button type="submit" class="btn btn-primary">Save SMTP Configuration</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-md-5">
        <div class="card">
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
const smtpConfigs = <?= json_encode($configs) ?>;

function selectProvider(btn, provider, defaultHost, defaultPort, defaultEnc) {
    document.querySelectorAll('.provider-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    document.getElementById('smtpProvider').value = provider;

    const saved = smtpConfigs[provider];
    if (saved) {
        document.getElementById('smtpLabel').value = saved.label;
        document.getElementById('smtpHost').value = saved.host;
        document.getElementById('smtpPort').value = saved.port;
        document.getElementById('smtpEncryption').value = saved.encryption;
        document.getElementById('smtpUsername').value = saved.username;
        document.getElementById('smtpFromEmail').value = saved.from_email;
        document.getElementById('smtpFromName').value = saved.from_name;
        document.getElementById('smtpPassword').placeholder = 'Enter to replace saved password';
    } else {
        document.getElementById('smtpLabel').value = '';
        document.getElementById('smtpHost').value = defaultHost;
        document.getElementById('smtpPort').value = defaultPort;
        document.getElementById('smtpEncryption').value = defaultEnc;
        document.getElementById('smtpUsername').value = '';
        document.getElementById('smtpFromEmail').value = '';
        document.getElementById('smtpFromName').value = '';
        document.getElementById('smtpPassword').placeholder = '';
    }
}

document.querySelectorAll('.provider-btn').forEach(b => {
    if (b.dataset.provider === <?= json_encode($activeProvider) ?>) {
        b.classList.add('active');
    }
});
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
