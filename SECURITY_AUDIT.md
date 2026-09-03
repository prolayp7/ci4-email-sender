# Internal Security Audit

Date: 2026-09-03

## Scope

This review covers the CodeIgniter application code, configuration defaults, database migrations, automated tests, and documented deployment model in this repository. It does not cover the host OS, reverse proxy, mail provider, network, production secrets, or third-party infrastructure.

## Implemented controls

- Authentication uses `password_hash()` and `password_verify()`. Login regenerates the session identifier and uses per-IP and per-account throttling.
- Disabled users are rejected. Authentication and role filters protect private and privileged routes; SMTP settings are restricted to owners and administrators.
- State-changing browser routes use POST and CodeIgniter's CSRF filter outside the test environment.
- Session cookies are HTTP-only and SameSite=Lax. HTTPS deployments should enable the Secure flag.
- Query Builder and model APIs parameterize database values. User-controlled history filters are allow-listed or bounded.
- Output is escaped by default. Admin-authored email HTML is displayed only in sandboxed preview iframes without same-origin or script permission.
- SMTP passwords are encrypted at rest, masked on read paths, decrypted only when needed, and excluded from logs and API responses.
- Activity logging records authentication, data changes, SMTP changes, sends, failures, retries, and password changes. Secret-shaped values are defensively redacted.
- Recipient imports validate file type, size, and row fields, handle duplicate addresses, and avoid permanent public upload storage.
- Security headers include Content Security Policy and browser-hardening headers. Template previews add a script-blocking policy.
- Production errors are intended to suppress stack traces while retaining diagnostics in server-side logs.
- Foreign keys and allow-listed model fields protect relationships and reduce unintended mass assignment.

## Verification performed

- PHPUnit covers authentication and throttling, authorization filters, security headers, recipient CRUD/import/export, template rendering and preview, SMTP storage and controllers, send recording, compose/drafts, email history/retry, settings, and activity-log redaction.
- PHP syntax checks were run against the added controllers and views.
- The full suite was run against the disposable MySQL test database.
- Repository checks verified that local `.env` and writable runtime data are ignored and not staged.
- Source scans checked for the known local database password and common secret assignments.

Automated coverage supports development assurance but does not prove the absence of vulnerabilities.

## Known limitations

- Two-factor authentication is not implemented.
- Sessions use local file storage; multi-server deployments need shared or external session storage.
- SMTP supports credential-based authentication; OAuth2 provider flows are not implemented.
- Login throttling depends on the cache backend and does not replace edge rate limiting.
- No automated dependency scanner or scheduled SAST/DAST pipeline is configured.
- No recipient-consent workflow, bounce processing, suppression synchronization, or jurisdiction-specific retention automation is included.
- HTML email is intentionally flexible. In-app previews are sandboxed, but recipients' clients apply their own sanitization.
- Real delivery cannot be tested without authorized external SMTP credentials.

## Required pre-production work

- Independently test access control, CSRF, sessions, injection, stored HTML, uploads, and rate-limit bypasses.
- Validate proxy headers, TLS, Secure cookies, CSP, permissions, log exposure, backups, and restores in the deployed environment.
- Run dependency and host vulnerability scans and establish patching ownership.
- Verify SPF, DKIM, DMARC, sender authorization, provider quotas, and applicable legal requirements.
- Define retention/deletion and incident-response procedures for recipient data, credentials, logs, and email history.

## Assessment statement

**This document records an internal development-time review, not a certified VAPT — engage a qualified external security assessor before any production deployment handling real recipient data.**
