<?= $this->extend('layout/main') ?>
<?= $this->section('content') ?>

<div class="row g-4">
    <div class="col-lg-8">

        <div class="card mb-4">
            <div class="card-header bg-white">Setting up Gmail SMTP</div>
            <div class="card-body">
                <p class="text-muted small">Gmail does not accept your normal account password for SMTP sign-in — you need a 16-character <strong>App Password</strong> instead. This requires 2-Step Verification to be turned on first.</p>
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
                <div class="alert alert-warning small mb-0">
                    Seeing <code>534-5.7.9 Application-specific password required</code>? That means the password field still has your normal account password — replace it with the app password from step 3.
                </div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header bg-white">Setting up Microsoft 365 SMTP</div>
            <div class="card-body">
                <ul class="ps-3 mb-2">
                    <li class="mb-2">Host: <code>smtp.office365.com</code>, Port: <code>587</code>, Encryption: <code>TLS</code>.</li>
                    <li class="mb-2">Username: your full Microsoft 365 email address.</li>
                    <li class="mb-2">Password: if the account has multi-factor authentication enabled, you'll need an app password from your organization's Microsoft 365 security settings, not the normal sign-in password.</li>
                    <li>SMTP AUTH must be enabled for the mailbox — this is sometimes off by default and needs a Microsoft 365 admin to turn it on per-mailbox or tenant-wide.</li>
                </ul>
                <p class="text-muted small mb-0">Tenants that are OAuth-only for mail (no basic SMTP AUTH) aren't supported by this app's current username/password SMTP integration.</p>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header bg-white">Custom / other SMTP providers</div>
            <div class="card-body">
                <p class="text-muted small mb-0">Select <strong>Custom</strong> in SMTP Settings and enter the host, port, and encryption mode your provider documents, along with the username and password (or API-key-as-password) they issue for SMTP sign-in.</p>
            </div>
        </div>

        <div class="card">
            <div class="card-header bg-white">Troubleshooting</div>
            <div class="card-body">
                <dl class="row mb-0 small">
                    <dt class="col-sm-4">Unable to connect to the SMTP server</dt>
                    <dd class="col-sm-8">Check the host and port are correct, and that nothing (a firewall, antivirus, or your network) is blocking outbound SMTP connections.</dd>

                    <dt class="col-sm-4 mt-2">Application-specific password required</dt>
                    <dd class="col-sm-8 mt-2">You're using a normal account password where the provider requires an app password. See the Gmail or Microsoft 365 sections above.</dd>

                    <dt class="col-sm-4 mt-2">554 TLS already active</dt>
                    <dd class="col-sm-8 mt-2">Port/encryption mismatch — port <code>465</code> needs <code>SSL</code>, port <code>587</code> needs <code>TLS</code>. The SMTP Settings form keeps these in sync automatically when you change the port.</dd>

                    <dt class="col-sm-4 mt-2">Test email sends but real emails fail</dt>
                    <dd class="col-sm-8 mt-2">Check <strong>Email History</strong> for the specific error recorded against the failed send — it carries the same detail the test-connection check would show.</dd>
                </dl>
            </div>
        </div>

    </div>

    <div class="col-lg-4">
        <div class="card">
            <div class="card-header bg-white">Quick links</div>
            <div class="card-body d-grid gap-2">
                <a href="/smtp" class="btn btn-outline-primary text-start">SMTP Settings</a>
                <a href="/emails" class="btn btn-outline-primary text-start">Email History</a>
                <a href="/recipients" class="btn btn-outline-primary text-start">Recipients</a>
                <a href="/templates" class="btn btn-outline-primary text-start">Email Templates</a>
            </div>
        </div>
    </div>
</div>

<?= $this->endSection() ?>
