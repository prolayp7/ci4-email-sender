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
                <p class="text-muted small">In <a href="/smtp">SMTP Settings</a>, select the <strong>Microsoft 365</strong> preset, then: Host <code>smtp.office365.com</code>, Port <code>587</code>, Encryption <code>TLS</code>, Username = the full mailbox address. What goes in the password field depends on the account type below.</p>

                <h6 class="small fw-semibold mt-3">Personal account (outlook.com, hotmail.com, live.com)</h6>
                <ol class="ps-3 mb-2">
                    <li class="mb-2">At <a href="https://account.microsoft.com/security" target="_blank" rel="noopener">account.microsoft.com/security</a>, open <strong>Manage how I sign in</strong> and make sure <strong>Two-step verification</strong> is explicitly turned on (not just available) — App Passwords only appears once that's on.</li>
                    <li class="mb-2">Look for <strong>App passwords</strong> there, or try the direct link <a href="https://account.live.com/proofs/AppPassword" target="_blank" rel="noopener">account.live.com/proofs/AppPassword</a> — Microsoft has been removing this option from the redesigned Security page for many accounts even when 2-step verification is on.</li>
                    <li>If it's genuinely not available: Microsoft has been disabling basic-auth SMTP entirely for consumer Outlook.com/Hotmail accounts (the same change that hit Exchange Online tenants), so username/password sending may simply not be supported for that account anymore. For testing, use Gmail instead, or a transactional-email provider (Mailgun, Brevo, SES) that issues SMTP credentials meant for this.</li>
                </ol>

                <h6 class="small fw-semibold mt-3">Work/school account on a Microsoft 365 tenant</h6>
                <p class="text-muted small mb-2">This is the case that usually trips people up: Microsoft disables SMTP AUTH for every mailbox by default now.</p>
                <ol class="ps-3">
                    <li class="mb-2">A tenant admin must enable SMTP AUTH for the mailbox: Microsoft 365 admin center → <strong>Users → Active users</strong> → select the user → <strong>Mail</strong> tab → "Manage email apps" → check <strong>Authenticated SMTP</strong> → Save.</li>
                    <li class="mb-2">Even with that on, a tenant-wide <strong>Security Defaults</strong> or <strong>Conditional Access</strong> policy blocking legacy/basic authentication (very common today) will still block it — an admin has to exclude that mailbox/service account from the policy, or allow basic auth for SMTP specifically.</li>
                    <li>If MFA is required on the account, ask the admin whether a per-user app password is available for it; otherwise, use a dedicated service account without MFA, scoped just for sending.</li>
                </ol>

                <div class="alert alert-warning small mb-0">
                    <code>535 5.7.3 Authentication unsuccessful</code> means SMTP AUTH is off for the mailbox, or blocked by Conditional Access — see the tenant steps above, not a password typo.
                </div>
                <p class="text-muted small mt-3 mb-0">This app only supports username/password SMTP authentication, not OAuth2 — a tenant that has gone OAuth-only for mail sending won't work here regardless of configuration.</p>
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

                    <dt class="col-sm-4 mt-2">535 Authentication unsuccessful</dt>
                    <dd class="col-sm-8 mt-2">On Microsoft 365, this means SMTP AUTH is disabled for the mailbox or blocked by a tenant policy — see the work/school steps in the Microsoft 365 section above.</dd>

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
