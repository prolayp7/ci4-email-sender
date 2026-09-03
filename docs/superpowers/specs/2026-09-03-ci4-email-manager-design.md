# CI4 Email Manager — Design Spec

Date: 2026-09-03
Source brief: `PROMPT.md.md` (full 40-section spec, "build everything" scope confirmed)

## 1. Stack

- CodeIgniter 4 (latest stable via Composer), PHP 8.5, MySQL 8.4
- Bootstrap 5 via CDN (no Node/build step)
- Lucide icons via CDN
- Quill (CDN) for HTML template/compose editing
- Vanilla JS + fetch for AJAX (send flow, test SMTP, CSV import summary)
- No queues, no Redis, no extra Composer packages beyond CI4 itself (CI4's Email library wraps PHPMailer-equivalent SMTP handling natively)

Local DB: `ci4mailer`, user `root`, password provided by user out-of-band, stored only in local `.env` (never committed).

## 2. Database schema

All tables: `id` PK, `created_at`/`updated_at` timestamps (CI4 migration `withTimestamps`).

**users**
- name varchar(150)
- email varchar(191) unique
- password_hash varchar(255)
- role enum('owner','admin','operator','viewer') default 'admin'
- status enum('active','disabled') default 'active'
- last_login_at datetime null

**recipients**
- name varchar(150)
- email varchar(191) unique, indexed
- company varchar(150) null
- phone varchar(30) null
- status enum('active','unsubscribed') default 'active'
- notes text null

**email_templates**
- name varchar(150)
- subject varchar(255)
- html_body mediumtext
- text_body text null
- status enum('active','draft') default 'active'

**smtp_settings**
- label varchar(100)
- host varchar(191)
- port smallint unsigned
- encryption enum('tls','ssl')
- username varchar(191)
- password_encrypted text — encrypted via CI4 Encryption service, never returned via API/JSON
- from_email varchar(191)
- from_name varchar(150)
- is_active boolean default false — exactly one row active at a time (enforced in service layer)

**emails**
- recipient_id FK -> recipients.id
- template_id FK -> email_templates.id, nullable
- user_id FK -> users.id
- subject varchar(255)
- body_html mediumtext
- body_text text null
- status enum('pending','sent','failed','draft') default 'pending', indexed — 'draft' backs the Compose page's "Save Draft" action; the History list excludes drafts unless explicitly filtered for
- error_message text null
- message_id varchar(191) null
- attempt_count smallint unsigned default 0
- sent_at datetime null, indexed
- indexed: recipient_id, status, sent_at

**activity_logs**
- user_id FK -> users.id, nullable
- action varchar(100) (e.g. `login`, `recipient.created`, `email.sent`)
- description varchar(255)
- ip_address varchar(45) null

No password/SMTP secret ever written to `activity_logs` or app logs.

## 3. Routes

```
GET/POST /login
POST     /logout

GET      /dashboard

GET      /recipients
GET/POST /recipients/create
GET/POST /recipients/edit/(:num)
POST     /recipients/delete/(:num)
POST     /recipients/import          (CSV upload)
GET      /recipients/export          (CSV download)

GET      /templates
GET/POST /templates/create
GET/POST /templates/edit/(:num)
POST     /templates/delete/(:num)
POST     /templates/duplicate/(:num)
GET      /templates/preview/(:num)

GET      /compose
POST     /compose/send

GET      /emails
GET      /emails/(:num)              (detail)
POST     /emails/retry/(:num)

GET/POST /smtp
POST     /smtp/test

GET/POST /settings
```

All routes except `/login` sit behind an `Auth` filter; state-changing POSTs behind CSRF (CI4 global CSRF filter) + a `Role` filter stub (v1 seeds one admin/owner user; role checks are enforced in the filter now so adding operator/viewer restrictions later is a config change, not a rewrite).

## 4. Services (business logic out of controllers)

- `AuthService` — login attempt validation, throttling (CI4 `Throttler`, keyed by IP+email), session regeneration on success
- `RecipientImportService` — CSV parse, per-row validation (name/email format/max length), duplicate detection against DB and within the file, returns a summary (imported/skipped/invalid/duplicates)
- `TemplateRenderer` — safe `{{name}}/{{email}}/{{company}}` substitution via `str_replace` on a whitelist of tokens (never `eval`/`extract`)
- `SmtpConfigService` — encrypt/decrypt SMTP password via CI4 `Encryption` service (key from `.env` `encryption.key`), enforce single active config, mask password in every read path
- `EmailSenderService` — the one-by-one send: validates recipient + content, loads active SMTP config, decrypts password in-memory only, sends via CI4 `Email` library, writes the `emails` row (sent/failed + error message + message id), used by both Compose-send and Retry
- `ActivityLogger` — writes `activity_logs` rows, strips anything password/token-shaped before logging

## 5. Security controls

- CSRF: CI4 global CSRF filter on all state-changing routes; AJAX sends the token via header
- XSS: `esc()` in every view for user-generated output; template HTML bodies are admin-authored (trusted operator, not end-user input) but still rendered inside a sandboxed preview iframe to avoid script execution leaking into the app shell
- SQLi: Query Builder / parameter binding only, no raw concatenation
- File upload (CSV): extension + MIME + size validation, safe generated filename, stored under `writable/uploads` (outside webroot), never executed
- Headers filter: CSP, `X-Content-Type-Options: nosniff`, `X-Frame-Options: DENY`, `Referrer-Policy: strict-origin-when-cross-origin`, `Permissions-Policy`
- Cookies: HttpOnly + SameSite=Lax always; `Secure` flag turned on automatically when the request is HTTPS
- Auth: `password_hash`/`password_verify`, session ID regenerated on login, session destroyed + regenerated on logout, rate-limited login attempts
- Error handling: `CI_ENVIRONMENT=production` in the shipped `.env.example` guidance suppresses stack traces/queries from end users; technical detail still goes to CI4's log files
- Secrets: SMTP password encrypted at rest, decrypted only inside `EmailSenderService` right before connecting, never serialized into any JSON/API response or log line

## 6. UI structure

- `app/Views/layout/main.php` — sidebar (Dashboard/Recipients/Templates/Compose/History/SMTP/Settings, Help/Profile/Logout pinned bottom) + header (title/breadcrumb/search/profile dropdown) + toast container + confirm-dialog component, responsive (sidebar collapses to off-canvas on mobile)
- Status badges: sent=green, failed=red, pending=amber — one shared Blade-style partial/view component
- Every list page ships loading/empty/error/success states per spec section 30
- Toasts for every mutating action (add/edit/delete/import/send/retry/SMTP save)
- Confirm dialogs for destructive actions (delete recipient/template, send email)

## 7. Testing approach

Given this is a monolithic CI4 app without a JS test runner in scope, verification is:
- CI4 feature tests (`tests/`) for: auth (valid/invalid login, logout, unauthorized redirect), recipients CRUD + duplicate/invalid email, CSV import (valid/invalid/oversized/duplicate rows), templates CRUD + placeholder substitution, SMTP settings save/mask, email send success/failure recording, retry flow
- Manual QA pass through the running app (spark serve) covering the section-38 checklist: every route reachable, responsive check, browser console clean, no secrets in network responses/logs
- `SECURITY_AUDIT.md` documents what was checked and explicitly states this is not a substitute for a qualified external VAPT

## 8. Documentation deliverables

- `README.md` — requirements, install, composer, DB setup + migrate command, `.env` config, SMTP setup (Gmail app-password note, M365, generic), running locally, production notes
- `SECURITY_AUDIT.md` — controls implemented, checks performed, known limitations, what needs external pentest, production hardening recommendations
- `.env.example` — safe placeholders only, no real credentials

## 9. Explicit scope confirmation

Per user decision: build the full spec (no trimming), SMTP stored encrypted in DB (not env-only), Bootstrap 5 CDN, Quill CDN editor, CI4 built-in Encryption service.
