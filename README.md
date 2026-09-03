# CI4 Email Manager

A secure, role-aware CodeIgniter 4 application for managing recipients and templates, configuring SMTP, sending individual emails, and reviewing or retrying deliveries.

## Requirements

- PHP 8.2 or newer with `intl`, `mbstring`, `mysqli`, `json`, and `openssl`
- MySQL 8 or compatible MariaDB release
- Composer 2

## Installation

```bash
composer install
cp .env.example .env
php spark key:generate
```

Create separate application and test databases:

```sql
CREATE DATABASE ci4mailer CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE ci4mailer_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Edit `.env` with the correct base URL and database credentials. Keep `.env` private. Then initialize the schema and administrator account:

```bash
php spark migrate
ADMIN_SEED_EMAIL=admin@example.com ADMIN_SEED_PASSWORD='replace-with-a-strong-password' php spark db:seed AdminUserSeeder
```

Run locally with `php spark serve`, then open `http://localhost:8080` and sign in with the seeded account.

## SMTP setup

Configure SMTP from **SMTP Settings** after signing in. Credentials are encrypted in the database using `encryption.key`; the password is decrypted only while establishing a connection.

- Gmail and Google Workspace: enable two-step verification and use an App Password where supported. Do not use a normal Google account password. OAuth2 is preferable where available, but this application currently implements username/password SMTP authentication.
- Microsoft 365: use `smtp.office365.com`, port `587`, and TLS. Depending on tenant policy, SMTP AUTH must be enabled and MFA accounts may require an app password. OAuth-only tenants require an integration not included here.
- Custom providers: enter the provider host, port, encryption mode, username, password, and verified sender identity.

Use **Test Connection** before sending. Provider policy, firewall rules, DNS, and account permissions can all affect delivery.

## Tests

The test connection must point to a disposable database because the suite refreshes its schema:

```bash
vendor/bin/phpunit --no-coverage
```

Never configure `database.tests.database` to use production data.

## Production deployment

- Set `CI_ENVIRONMENT=production` and use a unique, secret `encryption.key`.
- Serve only over HTTPS and enable secure cookies in deployment configuration.
- Point the web server document root at `public/`; do not expose the repository root.
- Store `.env` values in the hosting platform's secret manager rather than source control.
- Restrict database and SMTP accounts to the permissions they require.
- Back up MySQL and test restoration regularly.
- Rotate credentials periodically. Rotating `encryption.key` without migrating encrypted SMTP values makes existing SMTP passwords unreadable.
- Review `writable/logs` without exposing it publicly and define a retention policy for recipient and email-history data.

See [SECURITY_AUDIT.md](SECURITY_AUDIT.md) for controls, limitations, and pre-production recommendations.
