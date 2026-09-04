<?= $this->extend('layout/main') ?>

<?= $this->section('styles') ?>
<link href="/assets/css/pages/help.css?v=<?= @filemtime(FCPATH . 'assets/css/pages/help.css') ?>" rel="stylesheet">
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<div class="mb-4">
    <h1 class="help-page-title">Help</h1>
    <p class="help-page-sub">SMTP setup guides and answers to common delivery problems.</p>
</div>

<div class="row g-4">
    <div class="col-lg-8">

        <div class="help-panel">
            <div class="help-panel__head">
                <i class="bi bi-google"></i>
                <h2>Setting up Gmail SMTP</h2>
            </div>
            <p>Gmail does not accept your normal account password for SMTP sign-in — you need a 16-character <strong>App Password</strong> instead. This requires 2-Step Verification to be turned on first.</p>
            <ol class="ps-3">
                <li class="mb-2">Turn on <strong>2-Step Verification</strong> on the Google account you want to send from: <a href="https://myaccount.google.com/security" target="_blank" rel="noopener">myaccount.google.com/security</a>.</li>
                <li class="mb-2">Go to <a href="https://myaccount.google.com/apppasswords" target="_blank" rel="noopener">myaccount.google.com/apppasswords</a> and create a new app password. Name it something like "Email Manager".</li>
                <li class="mb-2">Google shows a 16-character password (e.g. <code>abcd efgh ijkl mnop</code>). Copy it <strong>without spaces</strong>.</li>
                <li class="mb-2">In <a href="/smtp">SMTP Settings</a>, select the <strong>Gmail</strong> preset and fill in:
                    <ul class="mt-2">
                        <li>Host: <code>smtp.gmail.com</code></li>
                        <li>Port: <code>587</code>, Encryption: <code>TLS</code></li>
                        <li>Username: your full Gmail address</li>
                        <li>Password: the app password from step 3 (not your normal Gmail password)</li>
                    </ul>
                </li>
                <li>Save, then use <strong>Send Test Email</strong> to confirm delivery.</li>
            </ol>
            <div class="help-callout">
                <i class="bi bi-exclamation-triangle-fill"></i>
                <span>Seeing <code>534-5.7.9 Application-specific password required</code>? That means the password field still has your normal account password — replace it with the app password from step 3.</span>
            </div>
        </div>

        <div class="help-panel">
            <div class="help-panel__head">
                <i class="bi bi-server"></i>
                <h2>Custom / other SMTP providers</h2>
            </div>
            <p class="mb-0">Select <strong>Custom</strong> in SMTP Settings and enter the host, port, and encryption mode your provider documents, along with the username and password (or API-key-as-password) they issue for SMTP sign-in.</p>
        </div>

        <div class="help-panel">
            <div class="help-panel__head">
                <i class="bi bi-question-circle"></i>
                <h2>Troubleshooting</h2>
            </div>
            <div class="help-faq">
                <div class="help-faq__item">
                    <p class="help-faq__q">Unable to connect to the SMTP server</p>
                    <p class="help-faq__a">Check the host and port are correct, and that nothing (a firewall, antivirus, or your network) is blocking outbound SMTP connections.</p>
                </div>
                <div class="help-faq__item">
                    <p class="help-faq__q">Application-specific password required</p>
                    <p class="help-faq__a">You're using a normal account password where the provider requires an app password. See the Gmail section above.</p>
                </div>
                <div class="help-faq__item">
                    <p class="help-faq__q">554 TLS already active</p>
                    <p class="help-faq__a">Port/encryption mismatch — port <code>465</code> needs <code>SSL</code>, port <code>587</code> needs <code>TLS</code>. The SMTP Settings form keeps these in sync automatically when you change the port.</p>
                </div>
                <div class="help-faq__item">
                    <p class="help-faq__q">Test email sends but real emails fail</p>
                    <p class="help-faq__a">Check <strong>Email History</strong> for the specific error recorded against the failed send — it carries the same detail the test-connection check would show.</p>
                </div>
            </div>
        </div>

    </div>

    <div class="col-lg-4">
        <div class="help-panel">
            <div class="help-panel__head">
                <i class="bi bi-link-45deg"></i>
                <h2>Quick links</h2>
            </div>
            <div class="help-links">
                <a href="/smtp"><i class="bi bi-server"></i>SMTP Settings</a>
                <a href="/emails"><i class="bi bi-clock-history"></i>Email History</a>
                <a href="/recipients"><i class="bi bi-people"></i>Recipients</a>
                <a href="/templates"><i class="bi bi-file-earmark-text"></i>Email Templates</a>
            </div>
        </div>
    </div>
</div>

<?= $this->endSection() ?>
