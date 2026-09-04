<?= $this->extend('layout/main') ?>

<?= $this->section('styles') ?>
<link href="/assets/css/pages/smtp.css?v=<?= @filemtime(FCPATH . 'assets/css/pages/smtp.css') ?>" rel="stylesheet">
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<div class="mb-4">
    <h1 class="smtp-page-title">SMTP Settings</h1>
    <p class="smtp-page-sub">Configure the outbound mail server used to send campaigns and test emails.</p>
</div>

<?php if (session()->getFlashdata('success')) : ?><div class="alert alert-success"><?= esc(session()->getFlashdata('success')) ?></div><?php endif ?>
<?php if (session()->getFlashdata('error')) : ?><div class="alert alert-danger"><?= esc(session()->getFlashdata('error')) ?></div><?php endif ?>

<div class="row g-4">
    <div class="col-lg-7">
        <div class="smtp-panel">
            <div class="smtp-tabs" role="tablist" aria-label="SMTP provider">
                <button type="button" class="smtp-tab provider-btn" data-provider="gmail" onclick="selectProvider(this,'gmail','smtp.gmail.com',587,'tls')">Gmail</button>
                <button type="button" class="smtp-tab provider-btn" data-provider="custom" onclick="selectProvider(this,'custom','',587,'tls')">Custom</button>
            </div>

            <form method="post" action="/smtp">
                <?= csrf_field() ?>
                <input type="hidden" name="provider" id="smtpProvider" value="<?= esc($activeProvider) ?>">

                <div class="smtp-section">
                    <h6>Connection</h6>
                    <div class="mb-3">
                        <label for="smtpLabel" class="form-label">Label</label>
                        <input type="text" name="label" id="smtpLabel" class="form-control" value="<?= esc($config['label'] ?? '') ?>" required>
                    </div>
                    <div class="smtp-field-grid smtp-field-grid--3 mb-3">
                        <div>
                            <label for="smtpHost">Host</label>
                            <input type="text" name="host" id="smtpHost" class="form-control" value="<?= esc($config['host'] ?? '') ?>" required>
                        </div>
                        <div>
                            <label for="smtpPort">Port</label>
                            <input type="number" name="port" id="smtpPort" class="form-control" value="<?= esc((string) ($config['port'] ?? 587)) ?>" oninput="syncEncryptionToPort(this.value)" required>
                        </div>
                    </div>
                    <div class="smtp-field-grid">
                        <div>
                            <label for="smtpEncryption">Encryption</label>
                            <select name="encryption" id="smtpEncryption" class="form-select">
                                <option value="tls" <?= ($config['encryption'] ?? '') === 'tls' ? 'selected' : '' ?>>TLS</option>
                                <option value="ssl" <?= ($config['encryption'] ?? '') === 'ssl' ? 'selected' : '' ?>>SSL</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="smtp-section">
                    <h6>Authentication</h6>
                    <div class="smtp-field-grid">
                        <div>
                            <label for="smtpUsername">Username</label>
                            <input type="text" name="username" id="smtpUsername" class="form-control" value="<?= esc($config['username'] ?? '') ?>" required>
                        </div>
                        <div>
                            <label for="smtpPassword">Password</label>
                            <input type="password" name="password" id="smtpPassword" class="form-control" placeholder="<?= $config ? 'Enter to replace saved password' : '' ?>" required>
                        </div>
                    </div>
                </div>

                <div class="smtp-section">
                    <h6>Sender identity</h6>
                    <div class="smtp-field-grid">
                        <div>
                            <label for="smtpFromEmail">From Email</label>
                            <input type="email" name="from_email" id="smtpFromEmail" class="form-control" value="<?= esc($config['from_email'] ?? '') ?>" required>
                        </div>
                        <div>
                            <label for="smtpFromName">From Name</label>
                            <input type="text" name="from_name" id="smtpFromName" class="form-control" value="<?= esc($config['from_name'] ?? '') ?>" required>
                        </div>
                    </div>
                </div>

                <div class="smtp-actions">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check2 me-1"></i>Save SMTP Configuration</button>
                </div>
            </form>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="smtp-panel">
            <div class="smtp-panel__head">
                <h2>Test SMTP Connection</h2>
                <p>Send a one-off test email through the saved configuration.</p>
            </div>
            <label for="testEmailInput" class="form-label small fw-semibold">Send test to</label>
            <input type="email" id="testEmailInput" class="form-control mb-3" placeholder="you@example.com">
            <button type="button" class="btn btn-outline-primary" onclick="sendTestEmail()"><i class="bi bi-send me-1"></i>Send Test Email</button>
            <div id="testResult" class="smtp-test-result"></div>
        </div>
    </div>
</div>

<script>
const smtpConfigs = <?= json_encode($configs) ?>;
// CI4 rotates the CSRF token on every request, and Send Test Email can be
// clicked repeatedly without a page reload -- without tracking the updated
// hash from each response, the second click would always 403.
let currentCsrfHash = <?= json_encode(csrf_hash()) ?>;

// Port 465 requires implicit SSL, port 587 requires STARTTLS ("TLS" here).
// Mixing them (e.g. TLS on port 465) makes CI4's Email class attempt a
// STARTTLS handshake on an already-encrypted socket, which the server
// rejects with "554 TLS already active".
function syncEncryptionToPort(port) {
    if (port === '465') {
        document.getElementById('smtpEncryption').value = 'ssl';
    } else if (port === '587') {
        document.getElementById('smtpEncryption').value = 'tls';
    }
}

function selectProvider(btn, provider, defaultHost, defaultPort, defaultEnc) {
    document.querySelectorAll('.provider-btn').forEach(b => b.classList.remove('is-active'));
    btn.classList.add('is-active');
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
        b.classList.add('is-active');
    }
});
function sendTestEmail() {
    const email = document.getElementById('testEmailInput').value;
    const resultEl = document.getElementById('testResult');
    resultEl.textContent = 'Sending...';
    resultEl.className = 'smtp-test-result';
    const body = new URLSearchParams();
    body.set('test_email', email);
    body.set(<?= json_encode(csrf_token()) ?>, currentCsrfHash);
    fetch('/smtp/test', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: body.toString(),
    }).then(r => r.json()).then(data => {
        if (data.csrf_hash) currentCsrfHash = data.csrf_hash;
        resultEl.textContent = data.message;
        resultEl.className = 'smtp-test-result ' + (data.success ? 'text-success' : 'text-danger');
        showToast(data.message, data.success ? 'success' : 'danger');
    });
}
</script>

<?= $this->endSection() ?>
