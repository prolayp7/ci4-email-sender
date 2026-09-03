# CI4 Email Manager Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build a secure CodeIgniter 4 + MySQL app for managing recipients, templates, and one-by-one SMTP email sending, with full auth, security hardening, and history/audit trail.

**Architecture:** Monolithic CI4 app. Controllers stay thin; all business logic (import, rendering, SMTP encryption, sending, logging) lives in `app/Services`. Bootstrap 5 + Lucide + Quill via CDN, no build step. MySQL via Query Builder only.

**Tech Stack:** PHP 8.5, CodeIgniter 4 (latest via Composer), MySQL 8.4, Bootstrap 5 (CDN), Lucide (CDN), Quill (CDN), vanilla JS/fetch.

**Spec:** `docs/superpowers/specs/2026-09-03-ci4-email-manager-design.md`

## Global Constraints

- DB: database `ci4mailer` (dev), `ci4mailer_test` (tests), user `root`, password from local `.env` only — never commit `.env`, never hardcode the password in code.
- No Node/build step; all frontend libs load from CDN.
- No queues/Redis; sending is synchronous, one recipient per request.
- Query Builder / parameter binding only — no raw SQL string concatenation.
- Every view escapes user-generated output via `esc()`.
- CSRF filter active globally; every state-changing form/AJAX call includes the CSRF token.
- SMTP password: encrypted at rest via CI4's `Encryption` service, decrypted only inside `EmailSenderService`/`SmtpConfigService` right before use, never included in any JSON response, view, or log line.
- Passwords: `password_hash()`/`password_verify()` only, never plaintext.
- Security headers filter (CSP, X-Content-Type-Options, X-Frame-Options, Referrer-Policy, Permissions-Policy) applied globally.
- Tests run against MySQL (`ci4mailer_test` DB group) — no SQLite extension is installed in this environment.

---

### Task 1: Bootstrap CodeIgniter project + database config

**Files:**
- Create: whole CI4 skeleton via Composer at repo root
- Modify: `.env` (local, untracked), `app/Config/Database.php` (verify `tests` group), `.gitignore`
- Create: `.env.example`

**Interfaces:**
- Produces: working `php spark serve`, `default` DB group -> `ci4mailer`, `tests` DB group -> `ci4mailer_test`, both MySQLi driver.

- [ ] **Step 1: Install CodeIgniter 4**

```bash
cd /home/prolay/Projects/ci4-email-sender
composer create-project codeigniter4/appstarter tmp_ci4 --no-interaction
shopt -s dotglob
mv tmp_ci4/* .
rmdir tmp_ci4
```

- [ ] **Step 2: Verify install**

Run: `php spark --version`
Expected: prints CodeIgniter CLI Tool version, no errors.

- [ ] **Step 3: Create databases**

```bash
mysql -u root -p"$DB_ROOT_PASSWORD" -e "CREATE DATABASE IF NOT EXISTS ci4mailer CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; CREATE DATABASE IF NOT EXISTS ci4mailer_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

- [ ] **Step 4: Configure `.env`**

Copy `env` to `.env`, set:

```
CI_ENVIRONMENT = development

app.baseURL = 'http://localhost:8080/'

database.default.hostname = localhost
database.default.database = ci4mailer
database.default.username = root
database.default.password = <root password>
database.default.DBDriver = MySQLi

database.tests.hostname = localhost
database.tests.database = ci4mailer_test
database.tests.username = root
database.tests.password = <root password>
database.tests.DBDriver = MySQLi

encryption.key =
session.driver = 'CodeIgniter\Session\Handlers\FileHandler'
session.cookieHTTPOnly = true
session.cookieSameSite = 'Lax'
```

Run: `php spark key:generate` to populate `encryption.key` (used later for SMTP password encryption).

- [ ] **Step 5: Write `.env.example`** (safe placeholders, no real values)

```
CI_ENVIRONMENT = production

app.baseURL = 'http://localhost:8080/'

database.default.hostname = localhost
database.default.database = ci4mailer
database.default.username = your_db_user
database.default.password = your_db_password
database.default.DBDriver = MySQLi

database.tests.hostname = localhost
database.tests.database = ci4mailer_test
database.tests.username = your_db_user
database.tests.password = your_db_password
database.tests.DBDriver = MySQLi

encryption.key =

session.cookieHTTPOnly = true
session.cookieSameSite = 'Lax'
```

- [ ] **Step 6: Confirm `.env` is git-ignored**

Run: `grep -n "^\.env$" .gitignore || echo ".env" >> .gitignore`
Run: `git check-ignore -v .env`
Expected: prints the matching `.gitignore` rule (confirms `.env` won't be committed).

- [ ] **Step 7: Smoke test the server**

Run: `php spark serve &` then `curl -s -o /dev/null -w "%{http_code}" http://localhost:8080/` then kill the server.
Expected: `200`.

- [ ] **Step 8: Commit**

```bash
git add app bootstrap.php composer.json composer.lock public spark writable .gitignore .env.example env preload.php
git commit -m "feat: bootstrap CodeIgniter 4 project with MySQL config"
```

---

### Task 2: Database migrations + admin seeder

**Files:**
- Create: `app/Database/Migrations/2026-09-03-000001_CreateUsers.php`
- Create: `app/Database/Migrations/2026-09-03-000002_CreateRecipients.php`
- Create: `app/Database/Migrations/2026-09-03-000003_CreateEmailTemplates.php`
- Create: `app/Database/Migrations/2026-09-03-000004_CreateSmtpSettings.php`
- Create: `app/Database/Migrations/2026-09-03-000005_CreateEmails.php`
- Create: `app/Database/Migrations/2026-09-03-000006_CreateActivityLogs.php`
- Create: `app/Database/Seeds/AdminUserSeeder.php`
- Test: `tests/database/MigrationsTest.php`

**Interfaces:**
- Produces: tables `users`, `recipients`, `email_templates`, `smtp_settings`, `emails`, `activity_logs` exactly as columns below; `AdminUserSeeder` inserts one `owner` user, email `admin@example.com`, password from `ADMIN_SEED_PASSWORD` env var (falls back to a random generated password printed to console — never a hardcoded literal).

- [ ] **Step 1: Write the migrations test (fails first — no tables yet)**

```php
<?php
// tests/database/MigrationsTest.php
namespace Tests\Database;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Config\Database;

final class MigrationsTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $refresh = true;

    public function testAllTablesExist(): void
    {
        $db = Database::connect();
        $tables = $db->listTables();

        foreach (['users', 'recipients', 'email_templates', 'smtp_settings', 'emails', 'activity_logs'] as $table) {
            $this->assertContains($table, $tables, "Missing table: {$table}");
        }
    }

    public function testEmailsForeignKeysAndIndexes(): void
    {
        $db = Database::connect();
        $fields = $db->getFieldNames('emails');

        foreach (['recipient_id', 'template_id', 'user_id', 'status', 'sent_at', 'attempt_count', 'message_id'] as $column) {
            $this->assertContains($column, $fields);
        }
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php spark test tests/database/MigrationsTest.php`
Expected: FAIL — tables don't exist yet.

- [ ] **Step 3: Write the migrations**

```php
<?php
// app/Database/Migrations/2026-09-03-000001_CreateUsers.php
namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateUsers extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'            => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'name'          => ['type' => 'VARCHAR', 'constraint' => 150],
            'email'         => ['type' => 'VARCHAR', 'constraint' => 191],
            'password_hash' => ['type' => 'VARCHAR', 'constraint' => 255],
            'role'          => ['type' => 'ENUM', 'constraint' => ['owner', 'admin', 'operator', 'viewer'], 'default' => 'admin'],
            'status'        => ['type' => 'ENUM', 'constraint' => ['active', 'disabled'], 'default' => 'active'],
            'last_login_at' => ['type' => 'DATETIME', 'null' => true],
            'created_at'    => ['type' => 'DATETIME', 'null' => true],
            'updated_at'    => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('email');
        $this->forge->createTable('users');
    }

    public function down()
    {
        $this->forge->dropTable('users');
    }
}
```

```php
<?php
// app/Database/Migrations/2026-09-03-000002_CreateRecipients.php
namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateRecipients extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'         => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'name'       => ['type' => 'VARCHAR', 'constraint' => 150],
            'email'      => ['type' => 'VARCHAR', 'constraint' => 191],
            'company'    => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
            'phone'      => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true],
            'status'     => ['type' => 'ENUM', 'constraint' => ['active', 'unsubscribed'], 'default' => 'active'],
            'notes'      => ['type' => 'TEXT', 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('email');
        $this->forge->createTable('recipients');
    }

    public function down()
    {
        $this->forge->dropTable('recipients');
    }
}
```

```php
<?php
// app/Database/Migrations/2026-09-03-000003_CreateEmailTemplates.php
namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateEmailTemplates extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'         => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'name'       => ['type' => 'VARCHAR', 'constraint' => 150],
            'subject'    => ['type' => 'VARCHAR', 'constraint' => 255],
            'html_body'  => ['type' => 'MEDIUMTEXT'],
            'text_body'  => ['type' => 'TEXT', 'null' => true],
            'status'     => ['type' => 'ENUM', 'constraint' => ['active', 'draft'], 'default' => 'active'],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->createTable('email_templates');
    }

    public function down()
    {
        $this->forge->dropTable('email_templates');
    }
}
```

```php
<?php
// app/Database/Migrations/2026-09-03-000004_CreateSmtpSettings.php
namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateSmtpSettings extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'                => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'label'             => ['type' => 'VARCHAR', 'constraint' => 100],
            'host'              => ['type' => 'VARCHAR', 'constraint' => 191],
            'port'              => ['type' => 'SMALLINT', 'unsigned' => true],
            'encryption'        => ['type' => 'ENUM', 'constraint' => ['tls', 'ssl']],
            'username'          => ['type' => 'VARCHAR', 'constraint' => 191],
            'password_encrypted'=> ['type' => 'TEXT'],
            'from_email'        => ['type' => 'VARCHAR', 'constraint' => 191],
            'from_name'         => ['type' => 'VARCHAR', 'constraint' => 150],
            'is_active'         => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'created_at'        => ['type' => 'DATETIME', 'null' => true],
            'updated_at'        => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->createTable('smtp_settings');
    }

    public function down()
    {
        $this->forge->dropTable('smtp_settings');
    }
}
```

```php
<?php
// app/Database/Migrations/2026-09-03-000005_CreateEmails.php
namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateEmails extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'             => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'recipient_id'   => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'template_id'    => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'user_id'        => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'subject'        => ['type' => 'VARCHAR', 'constraint' => 255],
            'body_html'      => ['type' => 'MEDIUMTEXT'],
            'body_text'      => ['type' => 'TEXT', 'null' => true],
            'status'         => ['type' => 'ENUM', 'constraint' => ['pending', 'sent', 'failed', 'draft'], 'default' => 'pending'],
            'error_message'  => ['type' => 'TEXT', 'null' => true],
            'message_id'     => ['type' => 'VARCHAR', 'constraint' => 191, 'null' => true],
            'attempt_count'  => ['type' => 'SMALLINT', 'unsigned' => true, 'default' => 0],
            'sent_at'        => ['type' => 'DATETIME', 'null' => true],
            'created_at'     => ['type' => 'DATETIME', 'null' => true],
            'updated_at'     => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('recipient_id');
        $this->forge->addKey('status');
        $this->forge->addKey('sent_at');
        $this->forge->addForeignKey('recipient_id', 'recipients', 'id', '', 'CASCADE');
        $this->forge->addForeignKey('template_id', 'email_templates', 'id', 'SET NULL', 'SET NULL');
        $this->forge->addForeignKey('user_id', 'users', 'id', '', 'CASCADE');
        $this->forge->createTable('emails');
    }

    public function down()
    {
        $this->forge->dropTable('emails');
    }
}
```

```php
<?php
// app/Database/Migrations/2026-09-03-000006_CreateActivityLogs.php
namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateActivityLogs extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'          => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'user_id'     => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'action'      => ['type' => 'VARCHAR', 'constraint' => 100],
            'description' => ['type' => 'VARCHAR', 'constraint' => 255],
            'ip_address'  => ['type' => 'VARCHAR', 'constraint' => 45, 'null' => true],
            'created_at'  => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addForeignKey('user_id', 'users', 'id', 'SET NULL', 'SET NULL');
        $this->forge->createTable('activity_logs');
    }

    public function down()
    {
        $this->forge->dropTable('activity_logs');
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php spark test tests/database/MigrationsTest.php`
Expected: PASS (DatabaseTestTrait auto-migrates the `tests` DB group).

- [ ] **Step 5: Write the admin seeder**

```php
<?php
// app/Database/Seeds/AdminUserSeeder.php
namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class AdminUserSeeder extends Seeder
{
    public function run()
    {
        $email = getenv('ADMIN_SEED_EMAIL') ?: 'admin@example.com';
        $password = getenv('ADMIN_SEED_PASSWORD') ?: bin2hex(random_bytes(8));

        $this->db->table('users')->insert([
            'name'          => 'Administrator',
            'email'         => $email,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'role'          => 'owner',
            'status'        => 'active',
            'created_at'    => date('Y-m-d H:i:s'),
            'updated_at'    => date('Y-m-d H:i:s'),
        ]);

        if (! getenv('ADMIN_SEED_PASSWORD')) {
            CLI::write("Seeded admin {$email} with generated password: {$password}", 'yellow');
        }
    }
}
```

Add `use CodeIgniter\CLI\CLI;` to the top of the file.

- [ ] **Step 6: Run migrations + seeder against the dev DB**

Run: `php spark migrate` then `php spark db:seed AdminUserSeeder`
Expected: both succeed; note the printed admin password if `ADMIN_SEED_PASSWORD` wasn't set in `.env`.

- [ ] **Step 7: Commit**

```bash
git add app/Database tests/database
git commit -m "feat: add database migrations and admin seeder"
```

---

### Task 3: Security headers filter + global CSRF/session config

**Files:**
- Create: `app/Filters/SecurityHeaders.php`
- Modify: `app/Config/Filters.php` (register `securityheaders` as a global filter, confirm `csrf` global filter is enabled)
- Modify: `app/Config/Security.php` (CSRF: SameSite, regenerate token)
- Modify: `app/Config/Cookie.php` (`samesite = 'Lax'`, `httponly = true`, `secure` conditional handled by CI4 automatically when `app.baseURL` is https or `Config\App::$forceGlobalSecureRequests` — set here for production docs)
- Test: `tests/filters/SecurityHeadersTest.php`

**Interfaces:**
- Produces: every response includes `Content-Security-Policy`, `X-Content-Type-Options: nosniff`, `X-Frame-Options: DENY`, `Referrer-Policy: strict-origin-when-cross-origin`, `Permissions-Policy: geolocation=(), microphone=(), camera=()`.

- [ ] **Step 1: Write the failing test**

```php
<?php
// tests/filters/SecurityHeadersTest.php
namespace Tests\Filters;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;

final class SecurityHeadersTest extends CIUnitTestCase
{
    use FeatureTestTrait;

    public function testSecurityHeadersArePresent(): void
    {
        $result = $this->get('/login');

        $result->assertHeader('X-Content-Type-Options', 'nosniff');
        $result->assertHeader('X-Frame-Options', 'DENY');
        $result->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $this->assertNotEmpty($result->response()->getHeaderLine('Content-Security-Policy'));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php spark test tests/filters/SecurityHeadersTest.php`
Expected: FAIL — headers missing (also fails because `/login` route doesn't exist yet; that's fine, it fails either way — proceed to implementation, `/login` route is added in Task 4 and this test's route existence is confirmed there. For now assert against `/` instead if `/login` 404s.)

Adjust the test to hit `/` if needed at this stage; it will be pointed at `/login` once Task 4 lands.

- [ ] **Step 3: Write the filter**

```php
<?php
// app/Filters/SecurityHeaders.php
namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class SecurityHeaders implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        $response->setHeader('X-Content-Type-Options', 'nosniff');
        $response->setHeader('X-Frame-Options', 'DENY');
        $response->setHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->setHeader('Permissions-Policy', 'geolocation=(), microphone=(), camera=()');
        $response->setHeader(
            'Content-Security-Policy',
            "default-src 'self'; "
            . "script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; "
            . "style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; "
            . "font-src 'self' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; "
            . "img-src 'self' data:; "
            . "frame-ancestors 'none'"
        );

        return $response;
    }
}
```

- [ ] **Step 4: Register the filter globally**

In `app/Config/Filters.php`, add to the `aliases` array: `'securityheaders' => \App\Filters\SecurityHeaders::class,` and add `'securityheaders'` to the `$globals['after']` array. Confirm `'csrf'` is present in `$globals['before']` (CI4 ships it there by default — leave as-is if already present).

- [ ] **Step 5: Configure cookies**

In `app/Config/Cookie.php`, set `public bool $httponly = true;` and `public string $samesite = 'Lax';`.

- [ ] **Step 6: Run test to verify it passes**

Run: `php spark test tests/filters/SecurityHeadersTest.php`
Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add app/Filters/SecurityHeaders.php app/Config/Filters.php app/Config/Cookie.php tests/filters
git commit -m "feat: add global security headers filter"
```

---

### Task 4: Auth (service, controller, filter, views)

**Files:**
- Create: `app/Services/AuthService.php`
- Create: `app/Controllers/AuthController.php`
- Create: `app/Filters/AuthFilter.php`
- Create: `app/Filters/RoleFilter.php`
- Create: `app/Views/auth/login.php`
- Modify: `app/Config/Filters.php` (register `auth`/`role` aliases, apply `auth` to all authenticated route groups in Task 5's routes)
- Modify: `app/Config/Routes.php` (add `/login`, `/logout`)
- Test: `tests/services/AuthServiceTest.php`
- Test: `tests/controllers/AuthControllerTest.php`

**Interfaces:**
- Produces: `AuthService::attempt(string $email, string $password, string $ip): array{success: bool, user: ?array, reason: ?string}` — regenerates session on success, throttles by `$ip.$email`; `AuthService::logout(): void`; `AuthFilter` redirects unauthenticated requests to `/login`; `RoleFilter::before($request, $roles)` (roles = array of allowed role strings) returns 403 if the session user's role isn't in the list.
- Consumes: `users` table (Task 2), `SecurityHeaders` filter already global (Task 3).

- [ ] **Step 1: Write the failing service test**

```php
<?php
// tests/services/AuthServiceTest.php
namespace Tests\Services;

use App\Services\AuthService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

final class AuthServiceTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $refresh = true;

    public function testValidLoginSucceedsAndRegeneratesSession(): void
    {
        $this->db->table('users')->insert([
            'name' => 'Admin', 'email' => 'admin@test.com',
            'password_hash' => password_hash('Secret123!', PASSWORD_DEFAULT),
            'role' => 'owner', 'status' => 'active',
            'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $service = new AuthService();
        $result = $service->attempt('admin@test.com', 'Secret123!', '127.0.0.1');

        $this->assertTrue($result['success']);
        $this->assertSame('admin@test.com', $result['user']['email']);
    }

    public function testInvalidPasswordFails(): void
    {
        $this->db->table('users')->insert([
            'name' => 'Admin', 'email' => 'admin@test.com',
            'password_hash' => password_hash('Secret123!', PASSWORD_DEFAULT),
            'role' => 'owner', 'status' => 'active',
            'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $service = new AuthService();
        $result = $service->attempt('admin@test.com', 'wrong-password', '127.0.0.1');

        $this->assertFalse($result['success']);
        $this->assertSame('invalid_credentials', $result['reason']);
    }

    public function testDisabledUserCannotLogin(): void
    {
        $this->db->table('users')->insert([
            'name' => 'Admin', 'email' => 'admin@test.com',
            'password_hash' => password_hash('Secret123!', PASSWORD_DEFAULT),
            'role' => 'owner', 'status' => 'disabled',
            'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $service = new AuthService();
        $result = $service->attempt('admin@test.com', 'Secret123!', '127.0.0.1');

        $this->assertFalse($result['success']);
        $this->assertSame('account_disabled', $result['reason']);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php spark test tests/services/AuthServiceTest.php`
Expected: FAIL — class `App\Services\AuthService` not found.

- [ ] **Step 3: Implement `AuthService`**

```php
<?php
// app/Services/AuthService.php
namespace App\Services;

use CodeIgniter\Throttle\Throttler;

class AuthService
{
    public function attempt(string $email, string $password, string $ip): array
    {
        /** @var Throttler $throttler */
        $throttler = service('throttler');
        $throttleKey = 'login-' . $ip . '-' . strtolower($email);

        if ($throttler->check($throttleKey, 5, MINUTE) === false) {
            return ['success' => false, 'user' => null, 'reason' => 'rate_limited'];
        }

        $user = db_connect()->table('users')
            ->where('email', $email)
            ->get()
            ->getRowArray();

        if (! $user || ! password_verify($password, $user['password_hash'])) {
            return ['success' => false, 'user' => null, 'reason' => 'invalid_credentials'];
        }

        if ($user['status'] !== 'active') {
            return ['success' => false, 'user' => null, 'reason' => 'account_disabled'];
        }

        session()->regenerate(true);
        session()->set([
            'user_id'      => $user['id'],
            'user_email'   => $user['email'],
            'user_name'    => $user['name'],
            'user_role'    => $user['role'],
            'isLoggedIn'   => true,
        ]);

        db_connect()->table('users')->where('id', $user['id'])->update([
            'last_login_at' => date('Y-m-d H:i:s'),
        ]);

        unset($user['password_hash']);

        return ['success' => true, 'user' => $user, 'reason' => null];
    }

    public function logout(): void
    {
        session()->destroy();
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php spark test tests/services/AuthServiceTest.php`
Expected: PASS.

- [ ] **Step 5: Write the failing controller test**

```php
<?php
// tests/controllers/AuthControllerTest.php
namespace Tests\Controllers;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

final class AuthControllerTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $refresh = true;

    public function testLoginPageLoads(): void
    {
        $result = $this->get('/login');
        $result->assertStatus(200);
        $result->assertSee('Login');
    }

    public function testValidLoginRedirectsToDashboard(): void
    {
        $this->db->table('users')->insert([
            'name' => 'Admin', 'email' => 'admin@test.com',
            'password_hash' => password_hash('Secret123!', PASSWORD_DEFAULT),
            'role' => 'owner', 'status' => 'active',
            'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $result = $this->withSession([])->post('/login', [
            'email' => 'admin@test.com', 'password' => 'Secret123!',
        ]);

        $result->assertRedirectTo('/dashboard');
    }

    public function testUnauthorizedAccessRedirectsToLogin(): void
    {
        $result = $this->get('/dashboard');
        $result->assertRedirectTo('/login');
    }
}
```

- [ ] **Step 6: Run test to verify it fails**

Run: `php spark test tests/controllers/AuthControllerTest.php`
Expected: FAIL — route/controller not found.

- [ ] **Step 7: Implement `AuthController`, filters, view, and routes**

```php
<?php
// app/Controllers/AuthController.php
namespace App\Controllers;

use App\Services\AuthService;
use CodeIgniter\Controller;

class AuthController extends Controller
{
    public function showLogin()
    {
        if (session()->get('isLoggedIn')) {
            return redirect()->to('/dashboard');
        }
        return view('auth/login');
    }

    public function login()
    {
        $rules = [
            'email'    => 'required|valid_email',
            'password' => 'required|min_length[8]',
        ];

        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $service = new AuthService();
        $result = $service->attempt(
            $this->request->getPost('email'),
            $this->request->getPost('password'),
            $this->request->getIPAddress()
        );

        if (! $result['success']) {
            $message = $result['reason'] === 'rate_limited'
                ? 'Too many login attempts. Please wait a minute and try again.'
                : 'Invalid email or password.';

            log_message('notice', 'Failed login attempt for {email} from {ip}', [
                'email' => $this->request->getPost('email'),
                'ip'    => $this->request->getIPAddress(),
            ]);

            return redirect()->back()->withInput()->with('error', $message);
        }

        return redirect()->to('/dashboard');
    }

    public function logout()
    {
        (new AuthService())->logout();
        return redirect()->to('/login');
    }
}
```

```php
<?php
// app/Filters/AuthFilter.php
namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class AuthFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        if (! session()->get('isLoggedIn')) {
            return redirect()->to('/login');
        }
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
    }
}
```

```php
<?php
// app/Filters/RoleFilter.php
namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class RoleFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $allowed = $arguments ?? [];
        $role = session()->get('user_role');

        if (! empty($allowed) && ! in_array($role, $allowed, true)) {
            return service('response')->setStatusCode(403)->setBody('Forbidden');
        }
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
    }
}
```

```php
<?php
// app/Views/auth/login.php
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sign in — Email Manager</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light d-flex align-items-center" style="min-height:100vh;">
<div class="container" style="max-width:400px;">
    <div class="card shadow-sm border-0">
        <div class="card-body p-4">
            <h1 class="h4 mb-3 fw-semibold">Sign in</h1>
            <?php if (session()->getFlashdata('error')) : ?>
                <div class="alert alert-danger py-2"><?= esc(session()->getFlashdata('error')) ?></div>
            <?php endif ?>
            <form method="post" action="/login">
                <?= csrf_field() ?>
                <div class="mb-3">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-control" value="<?= esc(old('email')) ?>" required autofocus>
                </div>
                <div class="mb-3">
                    <label class="form-label">Password</label>
                    <input type="password" name="password" class="form-control" required>
                </div>
                <button type="submit" class="btn btn-primary w-100">Login</button>
            </form>
        </div>
    </div>
</div>
</body>
</html>
```

Add to `app/Config/Routes.php`:

```php
$routes->get('login', 'AuthController::showLogin');
$routes->post('login', 'AuthController::login');
$routes->post('logout', 'AuthController::logout');

$routes->group('', ['filter' => 'auth'], function ($routes) {
    $routes->get('dashboard', 'DashboardController::index');
});
```

Register filters in `app/Config/Filters.php` aliases: `'auth' => \App\Filters\AuthFilter::class, 'role' => \App\Filters\RoleFilter::class,`.

- [ ] **Step 8: Add a stub `DashboardController`** (fleshed out in Task 5)

```php
<?php
// app/Controllers/DashboardController.php
namespace App\Controllers;

use CodeIgniter\Controller;

class DashboardController extends Controller
{
    public function index()
    {
        return $this->response->setBody('dashboard placeholder');
    }
}
```

- [ ] **Step 9: Run tests to verify they pass**

Run: `php spark test tests/controllers/AuthControllerTest.php`
Expected: PASS.

- [ ] **Step 10: Point `SecurityHeadersTest` at `/login` and re-run** (Task 3 test used a placeholder route)

Update `tests/filters/SecurityHeadersTest.php` to call `$this->get('/login')` (already written that way above) and confirm:

Run: `php spark test tests/filters/SecurityHeadersTest.php`
Expected: PASS.

- [ ] **Step 11: Commit**

```bash
git add app/Services/AuthService.php app/Controllers/AuthController.php app/Controllers/DashboardController.php app/Filters/AuthFilter.php app/Filters/RoleFilter.php app/Views/auth app/Config/Filters.php app/Config/Routes.php tests/services/AuthServiceTest.php tests/controllers/AuthControllerTest.php tests/filters/SecurityHeadersTest.php
git commit -m "feat: add authentication, session security, and route protection"
```

---

### Task 5: ActivityLogger service

**Files:**
- Create: `app/Services/ActivityLogger.php`
- Modify: `app/Services/AuthService.php` (log login/logout)
- Test: `tests/services/ActivityLoggerTest.php`

**Interfaces:**
- Produces: `ActivityLogger::log(?int $userId, string $action, string $description, string $ip = ''): void` — inserts into `activity_logs`, strips any substring matching common secret-shaped patterns before storing (defense in depth on top of never passing secrets in).
- Consumes: `activity_logs` table (Task 2).

- [ ] **Step 1: Write the failing test**

```php
<?php
// tests/services/ActivityLoggerTest.php
namespace Tests\Services;

use App\Services\ActivityLogger;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

final class ActivityLoggerTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $refresh = true;

    public function testLogInsertsRow(): void
    {
        ActivityLogger::log(1, 'login', 'Admin logged in', '127.0.0.1');

        $this->seeInDatabase('activity_logs', [
            'user_id' => 1,
            'action'  => 'login',
        ]);
    }

    public function testLogRedactsPasswordLikeContent(): void
    {
        ActivityLogger::log(1, 'smtp.updated', 'SMTP saved password=Secret123!');

        $row = $this->db->table('activity_logs')->where('action', 'smtp.updated')->get()->getRowArray();
        $this->assertStringNotContainsString('Secret123!', $row['description']);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php spark test tests/services/ActivityLoggerTest.php`
Expected: FAIL — class not found.

- [ ] **Step 3: Implement**

```php
<?php
// app/Services/ActivityLogger.php
namespace App\Services;

class ActivityLogger
{
    public static function log(?int $userId, string $action, string $description, string $ip = ''): void
    {
        $description = preg_replace('/(password|pass|secret|token)\s*[:=]\s*\S+/i', '$1=[redacted]', $description);

        db_connect()->table('activity_logs')->insert([
            'user_id'     => $userId,
            'action'      => $action,
            'description' => $description,
            'ip_address'  => $ip ?: (service('request')->getIPAddress() ?? ''),
            'created_at'  => date('Y-m-d H:i:s'),
        ]);
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php spark test tests/services/ActivityLoggerTest.php`
Expected: PASS.

- [ ] **Step 5: Wire into `AuthService`**

In `app/Services/AuthService.php`, add `use App\Services\ActivityLogger;` at top; after the successful-login `session()->set(...)` block add:

```php
ActivityLogger::log($user['id'], 'login', 'User logged in', $ip);
```

and in `logout()`, before `session()->destroy();` add:

```php
ActivityLogger::log(session()->get('user_id'), 'logout', 'User logged out');
```

- [ ] **Step 6: Run full auth test suite to confirm no regression**

Run: `php spark test tests/services/AuthServiceTest.php tests/controllers/AuthControllerTest.php`
Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add app/Services/ActivityLogger.php app/Services/AuthService.php tests/services/ActivityLoggerTest.php
git commit -m "feat: add activity logging with secret redaction"
```

---

### Task 6: App shell layout + Dashboard

**Files:**
- Create: `app/Views/layout/main.php`
- Create: `app/Views/layout/partials/sidebar.php`
- Create: `app/Views/layout/partials/header.php`
- Create: `app/Views/layout/partials/toast.php`
- Create: `app/Views/layout/partials/confirm_dialog.php`
- Create: `app/Views/dashboard/index.php`
- Modify: `app/Controllers/DashboardController.php` (replace placeholder from Task 4)
- Modify: `public/assets/js/app.js` (toast + confirm dialog JS helpers)
- Test: `tests/controllers/DashboardControllerTest.php`

**Interfaces:**
- Produces: `layout/main` view accepting `$title`, `$breadcrumb`, `$content` (via CI4 `<?= $this->include(...) ?>` sections or `view_cell`/sections API — use CI4 native `<?= $this->renderSection('content') ?>` pattern with `extend`/`section`). `window.showToast(message, type)` and `window.confirmAction(message, onConfirm)` JS globals for reuse by every later page.

- [ ] **Step 1: Write the failing test**

```php
<?php
// tests/controllers/DashboardControllerTest.php
namespace Tests\Controllers;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

final class DashboardControllerTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $refresh = true;

    public function testDashboardShowsKpis(): void
    {
        $this->db->table('users')->insert([
            'id' => 1, 'name' => 'Admin', 'email' => 'admin@test.com',
            'password_hash' => password_hash('x', PASSWORD_DEFAULT),
            'role' => 'owner', 'status' => 'active',
            'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $this->db->table('recipients')->insert([
            'name' => 'Jane', 'email' => 'jane@test.com', 'status' => 'active',
            'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $result = $this->withSession(['isLoggedIn' => true, 'user_id' => 1, 'user_role' => 'owner', 'user_name' => 'Admin'])
            ->get('/dashboard');

        $result->assertStatus(200);
        $result->assertSee('Total Recipients');
        $result->assertSee('1');
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php spark test tests/controllers/DashboardControllerTest.php`
Expected: FAIL — placeholder body doesn't contain "Total Recipients".

- [ ] **Step 3: Implement the layout partials**

```php
<?php
// app/Views/layout/partials/sidebar.php
$nav = [
    ['label' => 'Dashboard', 'icon' => 'layout-dashboard', 'href' => '/dashboard'],
    ['label' => 'Recipients', 'icon' => 'users', 'href' => '/recipients'],
    ['label' => 'Email Templates', 'icon' => 'file-text', 'href' => '/templates'],
    ['label' => 'Compose Email', 'icon' => 'send', 'href' => '/compose'],
    ['label' => 'Email History', 'icon' => 'history', 'href' => '/emails'],
    ['label' => 'SMTP Settings', 'icon' => 'server', 'href' => '/smtp'],
    ['label' => 'Settings', 'icon' => 'settings', 'href' => '/settings'],
];
$current = uri_string();
// Rendered twice: once as the static desktop sidebar, once inside the mobile offcanvas (see layout/main.php).
?>
<div class="d-flex flex-column h-100">
    <div class="fw-bold fs-5 mb-4 px-2">Email Manager</div>
    <div class="flex-grow-1">
        <?php foreach ($nav as $item) : ?>
            <a href="<?= esc($item['href']) ?>"
               class="d-flex align-items-center gap-2 px-2 py-2 rounded text-decoration-none mb-1 <?= str_starts_with($current, ltrim($item['href'], '/')) ? 'bg-primary-subtle text-primary fw-medium' : 'text-body' ?>">
                <i data-lucide="<?= esc($item['icon']) ?>" width="18" height="18"></i>
                <span><?= esc($item['label']) ?></span>
            </a>
        <?php endforeach ?>
    </div>
    <hr>
    <a href="#" class="d-flex align-items-center gap-2 px-2 py-2 text-body text-decoration-none">
        <i data-lucide="help-circle" width="18" height="18"></i> Help
    </a>
    <div class="px-2 py-2 text-truncate small text-muted"><?= esc(session()->get('user_name')) ?></div>
    <form method="post" action="/logout">
        <?= csrf_field() ?>
        <button type="submit" class="btn btn-sm btn-outline-secondary w-100 d-flex align-items-center justify-content-center gap-2">
            <i data-lucide="log-out" width="16" height="16"></i> Logout
        </button>
    </form>
</div>
```

```php
<?php
// app/Views/layout/partials/header.php
?>
<header class="d-flex justify-content-between align-items-center border-bottom bg-white px-4 py-3">
    <div class="d-flex align-items-center gap-3">
        <button class="btn btn-outline-secondary d-md-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#mobileSidebar">
            <i data-lucide="menu" width="18" height="18"></i>
        </button>
        <div>
            <h1 class="h5 mb-0"><?= esc($title ?? 'Dashboard') ?></h1>
            <?php if (! empty($breadcrumb)) : ?>
                <nav class="small text-muted"><?= esc($breadcrumb) ?></nav>
            <?php endif ?>
        </div>
    </div>
    <div class="d-flex align-items-center gap-3">
        <div class="dropdown">
            <button class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                <?= esc(session()->get('user_name')) ?>
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                <li><a class="dropdown-item" href="/settings">Profile</a></li>
                <li><form method="post" action="/logout"><?= csrf_field() ?><button class="dropdown-item">Logout</button></form></li>
            </ul>
        </div>
    </div>
</header>
```

```php
<?php
// app/Views/layout/partials/toast.php
?>
<div class="toast-container position-fixed bottom-0 end-0 p-3" id="toastContainer" style="z-index:1080;"></div>
```

```php
<?php
// app/Views/layout/partials/confirm_dialog.php
?>
<div class="modal fade" id="confirmDialog" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-body py-4">
                <p id="confirmDialogMessage" class="mb-0"></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirmDialogConfirmBtn">Delete</button>
            </div>
        </div>
    </div>
</div>
```

```php
<?php
// app/Views/layout/main.php
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= esc($title ?? 'Email Manager') ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/lucide-static/0.454.0/lucide.min.js" defer></script>
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
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="/assets/js/app.js"></script>
<script>if (window.lucide) lucide.createIcons();</script>
</body>
</html>
```

```js
// public/assets/js/app.js
window.showToast = function (message, type) {
    type = type || 'success';
    const bg = { success: 'text-bg-success', danger: 'text-bg-danger', warning: 'text-bg-warning' }[type] || 'text-bg-success';
    const el = document.createElement('div');
    el.className = 'toast align-items-center ' + bg + ' border-0';
    el.setAttribute('role', 'alert');
    el.innerHTML = '<div class="d-flex"><div class="toast-body">' + message + '</div>' +
        '<button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button></div>';
    document.getElementById('toastContainer').appendChild(el);
    new bootstrap.Toast(el, { delay: 4000 }).show();
};

window.confirmAction = function (message, onConfirm) {
    const modalEl = document.getElementById('confirmDialog');
    document.getElementById('confirmDialogMessage').textContent = message;
    const modal = new bootstrap.Modal(modalEl);
    const btn = document.getElementById('confirmDialogConfirmBtn');
    const handler = function () {
        modal.hide();
        btn.removeEventListener('click', handler);
        onConfirm();
    };
    btn.addEventListener('click', handler);
    modal.show();
};

document.addEventListener('DOMContentLoaded', function () {
    const flash = document.body.dataset.flashToast;
    if (flash) window.showToast(flash);
});
```

- [ ] **Step 4: Implement `DashboardController`**

```php
<?php
// app/Controllers/DashboardController.php
namespace App\Controllers;

use CodeIgniter\Controller;

class DashboardController extends Controller
{
    public function index()
    {
        $db = db_connect();

        $totalRecipients = $db->table('recipients')->countAllResults();
        $sent = $db->table('emails')->where('status', 'sent')->countAllResults();
        $failed = $db->table('emails')->where('status', 'failed')->countAllResults();
        $pending = $db->table('emails')->where('status', 'pending')->countAllResults();
        $totalEmails = $sent + $failed + $pending;
        $successRate = $totalEmails > 0 ? round(($sent / $totalEmails) * 100, 1) : 0;

        $recent = $db->table('activity_logs')
            ->orderBy('created_at', 'DESC')
            ->limit(8)
            ->get()
            ->getResultArray();

        return view('dashboard/index', [
            'title'            => 'Dashboard',
            'totalRecipients'  => $totalRecipients,
            'sent'             => $sent,
            'failed'           => $failed,
            'pending'          => $pending,
            'successRate'      => $successRate,
            'recent'           => $recent,
        ]);
    }
}
```

```php
<?php
// app/Views/dashboard/index.php
?>
<?= $this->extend('layout/main') ?>
<?= $this->section('content') ?>

<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card border-0 shadow-sm"><div class="card-body">
            <div class="text-muted small">Total Recipients</div>
            <div class="fs-3 fw-semibold"><?= esc((string) $totalRecipients) ?></div>
        </div></div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm"><div class="card-body">
            <div class="text-muted small">Emails Sent</div>
            <div class="fs-3 fw-semibold text-success"><?= esc((string) $sent) ?></div>
        </div></div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm"><div class="card-body">
            <div class="text-muted small">Emails Failed</div>
            <div class="fs-3 fw-semibold text-danger"><?= esc((string) $failed) ?></div>
        </div></div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm"><div class="card-body">
            <div class="text-muted small">Success Rate</div>
            <div class="fs-3 fw-semibold"><?= esc((string) $successRate) ?>%</div>
        </div></div>
    </div>
</div>

<div class="row g-3">
    <div class="col-md-7">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-medium">Recent Activity</div>
            <div class="list-group list-group-flush">
                <?php if (empty($recent)) : ?>
                    <div class="list-group-item text-muted">No activity yet.</div>
                <?php endif ?>
                <?php foreach ($recent as $item) : ?>
                    <div class="list-group-item small"><?= esc($item['description']) ?>
                        <span class="text-muted float-end"><?= esc($item['created_at']) ?></span>
                    </div>
                <?php endforeach ?>
            </div>
        </div>
    </div>
    <div class="col-md-5">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-medium">Quick Actions</div>
            <div class="card-body d-grid gap-2">
                <a href="/recipients/create" class="btn btn-outline-primary text-start">Add Recipient</a>
                <a href="/recipients" class="btn btn-outline-primary text-start">Import Recipients</a>
                <a href="/compose" class="btn btn-outline-primary text-start">Compose Email</a>
                <a href="/templates/create" class="btn btn-outline-primary text-start">Create Template</a>
                <a href="/smtp" class="btn btn-outline-primary text-start">SMTP Settings</a>
            </div>
        </div>
    </div>
</div>

<?= $this->endSection() ?>
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php spark test tests/controllers/DashboardControllerTest.php`
Expected: PASS.

- [ ] **Step 6: Manual smoke check**

Run: `php spark serve`, log in with the seeded admin, visually confirm sidebar/header/KPI cards render and no console errors.

- [ ] **Step 7: Commit**

```bash
git add app/Views/layout app/Views/dashboard app/Controllers/DashboardController.php public/assets/js/app.js tests/controllers/DashboardControllerTest.php
git commit -m "feat: add app shell layout and dashboard KPIs"
```

---

### Task 7: Recipients CRUD

**Files:**
- Create: `app/Models/RecipientModel.php`
- Create: `app/Controllers/RecipientController.php`
- Create: `app/Views/recipients/index.php`
- Create: `app/Views/recipients/form.php`
- Modify: `app/Config/Routes.php`
- Test: `tests/controllers/RecipientControllerTest.php`

**Interfaces:**
- Produces: `RecipientModel` (CI4 Model, table `recipients`, validation rules for name/email/company/phone/notes, `allowedFields` explicit list — no mass-assignment of `id`/timestamps).
- Consumes: `auth` filter (Task 4), layout (Task 6), `ActivityLogger` (Task 5).

- [ ] **Step 1: Write the failing test**

```php
<?php
// tests/controllers/RecipientControllerTest.php
namespace Tests\Controllers;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

final class RecipientControllerTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $refresh = true;

    private function loggedIn(): self
    {
        $this->db->table('users')->insert([
            'id' => 1, 'name' => 'Admin', 'email' => 'admin@test.com',
            'password_hash' => password_hash('x', PASSWORD_DEFAULT),
            'role' => 'owner', 'status' => 'active',
            'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);
        return $this->withSession(['isLoggedIn' => true, 'user_id' => 1, 'user_role' => 'owner', 'user_name' => 'Admin']);
    }

    public function testCreateRecipient(): void
    {
        $result = $this->loggedIn()->post('/recipients/create', [
            'name' => 'Jane Doe', 'email' => 'jane@example.com', 'company' => 'Acme',
        ]);

        $result->assertRedirect();
        $this->seeInDatabase('recipients', ['email' => 'jane@example.com']);
    }

    public function testDuplicateEmailRejected(): void
    {
        $this->db->table('recipients')->insert([
            'name' => 'Jane', 'email' => 'jane@example.com', 'status' => 'active',
            'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $result = $this->loggedIn()->post('/recipients/create', [
            'name' => 'Jane Two', 'email' => 'jane@example.com',
        ]);

        $result->assertOK();
        $this->assertSame(1, $this->db->table('recipients')->where('email', 'jane@example.com')->countAllResults());
    }

    public function testInvalidEmailRejected(): void
    {
        $result = $this->loggedIn()->post('/recipients/create', [
            'name' => 'Bad Email', 'email' => 'not-an-email',
        ]);

        $result->assertOK();
        $this->dontSeeInDatabase('recipients', ['name' => 'Bad Email']);
    }

    public function testUpdateRecipient(): void
    {
        $this->db->table('recipients')->insert([
            'id' => 1, 'name' => 'Jane', 'email' => 'jane@example.com', 'status' => 'active',
            'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $result = $this->loggedIn()->post('/recipients/edit/1', [
            'name' => 'Jane Updated', 'email' => 'jane@example.com',
        ]);

        $result->assertRedirect();
        $this->seeInDatabase('recipients', ['id' => 1, 'name' => 'Jane Updated']);
    }

    public function testDeleteRecipient(): void
    {
        $this->db->table('recipients')->insert([
            'id' => 1, 'name' => 'Jane', 'email' => 'jane@example.com', 'status' => 'active',
            'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $result = $this->loggedIn()->post('/recipients/delete/1');

        $result->assertRedirect();
        $this->dontSeeInDatabase('recipients', ['id' => 1]);
    }

    public function testBulkDeleteRemovesSelectedRecipients(): void
    {
        $this->db->table('recipients')->insert([
            'id' => 1, 'name' => 'Jane', 'email' => 'jane@example.com', 'status' => 'active',
            'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $this->db->table('recipients')->insert([
            'id' => 2, 'name' => 'John', 'email' => 'john@example.com', 'status' => 'active',
            'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $this->db->table('recipients')->insert([
            'id' => 3, 'name' => 'Keep', 'email' => 'keep@example.com', 'status' => 'active',
            'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $result = $this->loggedIn()->post('/recipients/bulk-delete', ['ids' => [1, 2]]);

        $result->assertRedirect();
        $this->dontSeeInDatabase('recipients', ['id' => 1]);
        $this->dontSeeInDatabase('recipients', ['id' => 2]);
        $this->seeInDatabase('recipients', ['id' => 3]);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php spark test tests/controllers/RecipientControllerTest.php`
Expected: FAIL — controller/routes don't exist.

- [ ] **Step 3: Implement the model**

```php
<?php
// app/Models/RecipientModel.php
namespace App\Models;

use CodeIgniter\Model;

class RecipientModel extends Model
{
    protected $table            = 'recipients';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $allowedFields    = ['name', 'email', 'company', 'phone', 'status', 'notes'];
    protected $useTimestamps    = true;

    protected $validationRules = [
        'name'    => 'required|max_length[150]',
        'email'   => 'required|valid_email|max_length[191]|is_unique[recipients.email,id,{id}]',
        'company' => 'permit_empty|max_length[150]',
        'phone'   => 'permit_empty|max_length[30]',
        'notes'   => 'permit_empty|max_length[2000]',
    ];
}
```

- [ ] **Step 4: Implement the controller**

```php
<?php
// app/Controllers/RecipientController.php
namespace App\Controllers;

use App\Models\RecipientModel;
use App\Services\ActivityLogger;
use CodeIgniter\Controller;

class RecipientController extends Controller
{
    public function index()
    {
        $model = new RecipientModel();
        $search = $this->request->getGet('q');

        $query = $model->orderBy('created_at', 'DESC');
        if ($search) {
            $query->groupStart()->like('name', $search)->orLike('email', $search)->orLike('company', $search)->groupEnd();
        }

        $recipients = $query->paginate(15);

        return view('recipients/index', [
            'title'      => 'Recipients',
            'recipients' => $recipients,
            'pager'      => $model->pager,
            'search'     => $search,
        ]);
    }

    public function create()
    {
        if ($this->request->getMethod() === 'GET') {
            return view('recipients/form', ['title' => 'Add Recipient', 'recipient' => null]);
        }

        $model = new RecipientModel();
        $data = $this->request->getPost(['name', 'email', 'company', 'phone', 'notes']);

        if (! $model->insert($data)) {
            return view('recipients/form', ['title' => 'Add Recipient', 'recipient' => $data, 'errors' => $model->errors()]);
        }

        ActivityLogger::log(session()->get('user_id'), 'recipient.created', 'Recipient created: ' . $data['email']);
        session()->setFlashdata('success', 'Recipient added successfully.');
        return redirect()->to('/recipients');
    }

    public function edit($id)
    {
        $model = new RecipientModel();
        $recipient = $model->find($id);
        if (! $recipient) {
            return redirect()->to('/recipients')->with('error', 'Recipient not found.');
        }

        if ($this->request->getMethod() === 'GET') {
            return view('recipients/form', ['title' => 'Edit Recipient', 'recipient' => $recipient]);
        }

        $data = $this->request->getPost(['name', 'email', 'company', 'phone', 'notes']);
        $model->setValidationRule('email', "required|valid_email|max_length[191]|is_unique[recipients.email,id,{$id}]");

        if (! $model->update($id, $data)) {
            return view('recipients/form', ['title' => 'Edit Recipient', 'recipient' => array_merge(['id' => $id], $data), 'errors' => $model->errors()]);
        }

        ActivityLogger::log(session()->get('user_id'), 'recipient.updated', 'Recipient updated: ' . $data['email']);
        session()->setFlashdata('success', 'Recipient updated successfully.');
        return redirect()->to('/recipients');
    }

    public function delete($id)
    {
        $model = new RecipientModel();
        $recipient = $model->find($id);
        if ($recipient) {
            $model->delete($id);
            ActivityLogger::log(session()->get('user_id'), 'recipient.deleted', 'Recipient deleted: ' . $recipient['email']);
        }
        session()->setFlashdata('success', 'Recipient deleted.');
        return redirect()->to('/recipients');
    }

    public function bulkDelete()
    {
        $ids = array_filter(array_map('intval', $this->request->getPost('ids') ?? []));
        if (empty($ids)) {
            session()->setFlashdata('error', 'No recipients selected.');
            return redirect()->to('/recipients');
        }

        $model = new RecipientModel();
        $model->whereIn('id', $ids)->delete();

        ActivityLogger::log(session()->get('user_id'), 'recipient.bulk_deleted', count($ids) . ' recipients deleted');
        session()->setFlashdata('success', count($ids) . ' recipient(s) deleted.');
        return redirect()->to('/recipients');
    }
}
```

- [ ] **Step 5: Implement views**

```php
<?php
// app/Views/recipients/form.php
?>
<?= $this->extend('layout/main') ?>
<?= $this->section('content') ?>
<div class="card border-0 shadow-sm" style="max-width:600px;">
    <div class="card-body">
        <?php if (! empty($errors)) : ?>
            <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $e) : ?><li><?= esc($e) ?></li><?php endforeach ?></ul></div>
        <?php endif ?>
        <form method="post" action="<?= $recipient['id'] ?? null ? '/recipients/edit/' . $recipient['id'] : '/recipients/create' ?>">
            <?= csrf_field() ?>
            <div class="mb-3">
                <label class="form-label">Name</label>
                <input type="text" name="name" class="form-control" value="<?= esc($recipient['name'] ?? old('name')) ?>" required maxlength="150">
            </div>
            <div class="mb-3">
                <label class="form-label">Email</label>
                <input type="email" name="email" class="form-control" value="<?= esc($recipient['email'] ?? old('email')) ?>" required maxlength="191">
            </div>
            <div class="mb-3">
                <label class="form-label">Company</label>
                <input type="text" name="company" class="form-control" value="<?= esc($recipient['company'] ?? old('company')) ?>" maxlength="150">
            </div>
            <div class="mb-3">
                <label class="form-label">Phone</label>
                <input type="text" name="phone" class="form-control" value="<?= esc($recipient['phone'] ?? old('phone')) ?>" maxlength="30">
            </div>
            <div class="mb-3">
                <label class="form-label">Notes</label>
                <textarea name="notes" class="form-control" rows="3" maxlength="2000"><?= esc($recipient['notes'] ?? old('notes')) ?></textarea>
            </div>
            <button type="submit" class="btn btn-primary">Save</button>
            <a href="/recipients" class="btn btn-outline-secondary">Cancel</a>
        </form>
    </div>
</div>
<?= $this->endSection() ?>
```

```php
<?php
// app/Views/recipients/index.php
?>
<?= $this->extend('layout/main') ?>
<?= $this->section('content') ?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <form method="get" class="d-flex gap-2">
        <input type="search" name="q" class="form-control" placeholder="Search recipients..." value="<?= esc($search ?? '') ?>">
        <button class="btn btn-outline-secondary">Search</button>
    </form>
    <div class="d-flex gap-2">
        <button type="button" id="bulkDeleteBtn" class="btn btn-outline-danger" style="display:none;" onclick="bulkDeleteRecipients()">Delete Selected</button>
        <a href="/recipients/export" class="btn btn-outline-secondary">Export CSV</a>
        <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#importModal">Import CSV</button>
        <a href="/recipients/create" class="btn btn-primary">Add Recipient</a>
    </div>
</div>

<?php if (session()->getFlashdata('success')) : ?>
    <div class="alert alert-success"><?= esc(session()->getFlashdata('success')) ?></div>
<?php endif ?>
<?php $summary = session()->getFlashdata('importSummary'); ?>
<?php if ($summary) : ?>
    <div class="alert alert-info">
        Imported: <?= (int) $summary['imported'] ?> &middot;
        Skipped: <?= (int) $summary['skipped'] ?> &middot;
        Invalid: <?= (int) $summary['invalid'] ?> &middot;
        Duplicates: <?= (int) $summary['duplicates'] ?>
    </div>
<?php endif ?>

<div class="card border-0 shadow-sm">
<?php if (empty($recipients)) : ?>
    <div class="card-body text-center text-muted py-5">No recipients yet. Add your first recipient.</div>
<?php else : ?>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead><tr><th style="width:32px;"><input type="checkbox" id="selectAll" onclick="toggleAll(this)"></th><th>Name</th><th>Email</th><th>Company</th><th>Status</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($recipients as $r) : ?>
                <tr>
                    <td><input type="checkbox" class="rowCheck" value="<?= (int) $r['id'] ?>" onclick="updateBulkButton()"></td>
                    <td><?= esc($r['name']) ?></td>
                    <td><?= esc($r['email']) ?></td>
                    <td><?= esc($r['company'] ?? '') ?></td>
                    <td><span class="badge <?= $r['status'] === 'active' ? 'text-bg-success' : 'text-bg-secondary' ?>"><?= esc($r['status']) ?></span></td>
                    <td class="text-end">
                        <a href="/recipients/edit/<?= (int) $r['id'] ?>" class="btn btn-sm btn-outline-secondary">Edit</a>
                        <button type="button" class="btn btn-sm btn-outline-danger" onclick="deleteRecipient(<?= (int) $r['id'] ?>)">Delete</button>
                    </td>
                </tr>
            <?php endforeach ?>
            </tbody>
        </table>
    </div>
<?php endif ?>
</div>
<div class="mt-3"><?= $pager->links() ?></div>

<div class="modal fade" id="importModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post" action="/recipients/import" enctype="multipart/form-data">
                <?= csrf_field() ?>
                <div class="modal-header"><h5 class="modal-title">Import Recipients</h5></div>
                <div class="modal-body">
                    <p class="small text-muted">CSV columns: Name, Email, Company, Phone. Max 2MB.</p>
                    <input type="file" name="csv" accept=".csv" class="form-control" required>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Import</button>
                </div>
            </form>
        </div>
    </div>
</div>

<form id="deleteForm" method="post" style="display:none;"><?= csrf_field() ?></form>
<form id="bulkDeleteForm" method="post" action="/recipients/bulk-delete" style="display:none;"><?= csrf_field() ?></form>
<script>
function deleteRecipient(id) {
    confirmAction('Delete this recipient? This action cannot be undone.', function () {
        const form = document.getElementById('deleteForm');
        form.action = '/recipients/delete/' + id;
        form.submit();
    });
}
function toggleAll(source) {
    document.querySelectorAll('.rowCheck').forEach(cb => cb.checked = source.checked);
    updateBulkButton();
}
function updateBulkButton() {
    const checked = document.querySelectorAll('.rowCheck:checked').length;
    document.getElementById('bulkDeleteBtn').style.display = checked > 0 ? 'inline-block' : 'none';
}
function bulkDeleteRecipients() {
    const ids = Array.from(document.querySelectorAll('.rowCheck:checked')).map(cb => cb.value);
    confirmAction('Delete ' + ids.length + ' selected recipient(s)? This action cannot be undone.', function () {
        const form = document.getElementById('bulkDeleteForm');
        ids.forEach(id => {
            const input = document.createElement('input');
            input.type = 'hidden'; input.name = 'ids[]'; input.value = id;
            form.appendChild(input);
        });
        form.submit();
    });
}
</script>

<?= $this->endSection() ?>
```

Note: this replaces the CSV-import modal markup that Task 8 previously added inline in Task 8's own instructions — the modal now lives here since Task 7 ships the full recipients index view. When executing Task 8, skip re-adding the modal (already present) and only add the `RecipientImportService` + controller `import`/`export` actions + routes.

- [ ] **Step 6: Add routes**

In `app/Config/Routes.php`, inside the existing `auth` filter group:

```php
$routes->get('recipients', 'RecipientController::index');
$routes->match(['get', 'post'], 'recipients/create', 'RecipientController::create');
$routes->match(['get', 'post'], 'recipients/edit/(:num)', 'RecipientController::edit/$1');
$routes->post('recipients/delete/(:num)', 'RecipientController::delete/$1');
$routes->post('recipients/bulk-delete', 'RecipientController::bulkDelete');
```

- [ ] **Step 7: Run test to verify it passes**

Run: `php spark test tests/controllers/RecipientControllerTest.php`
Expected: PASS.

- [ ] **Step 8: Commit**

```bash
git add app/Models/RecipientModel.php app/Controllers/RecipientController.php app/Views/recipients app/Config/Routes.php tests/controllers/RecipientControllerTest.php
git commit -m "feat: add recipient CRUD with search and pagination"
```

---

### Task 8: CSV import + export

**Files:**
- Create: `app/Services/RecipientImportService.php`
- Modify: `app/Controllers/RecipientController.php` (add `import`/`export` actions)
- Modify: `app/Views/recipients/index.php` (import modal + summary display)
- Modify: `app/Config/Routes.php`
- Test: `tests/services/RecipientImportServiceTest.php`

**Interfaces:**
- Produces: `RecipientImportService::import(string $csvPath): array{imported:int, skipped:int, invalid:int, duplicates:int, errors: array}` — expects header row `Name,Email,Company,Phone`.
- Consumes: `RecipientModel` (Task 7).

- [ ] **Step 1: Write the failing test**

```php
<?php
// tests/services/RecipientImportServiceTest.php
namespace Tests\Services;

use App\Services\RecipientImportService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

final class RecipientImportServiceTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $refresh = true;

    private function writeCsv(string $content): string
    {
        $path = WRITEPATH . 'uploads/test_' . uniqid() . '.csv';
        @mkdir(dirname($path), 0755, true);
        file_put_contents($path, $content);
        return $path;
    }

    public function testImportsValidRows(): void
    {
        $csv = "Name,Email,Company,Phone\nJane Doe,jane@example.com,Acme,555-1234\nJohn Roe,john@example.com,Acme,555-5678\n";
        $result = (new RecipientImportService())->import($this->writeCsv($csv));

        $this->assertSame(2, $result['imported']);
        $this->assertSame(0, $result['invalid']);
        $this->assertSame(0, $result['duplicates']);
        $this->seeInDatabase('recipients', ['email' => 'jane@example.com']);
    }

    public function testSkipsInvalidEmails(): void
    {
        $csv = "Name,Email,Company,Phone\nBad Row,not-an-email,Acme,\n";
        $result = (new RecipientImportService())->import($this->writeCsv($csv));

        $this->assertSame(0, $result['imported']);
        $this->assertSame(1, $result['invalid']);
    }

    public function testDetectsDuplicatesAgainstDbAndWithinFile(): void
    {
        $this->db->table('recipients')->insert([
            'name' => 'Existing', 'email' => 'jane@example.com', 'status' => 'active',
            'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $csv = "Name,Email,Company,Phone\nJane Dup,jane@example.com,Acme,\nJohn New,john@example.com,Acme,\nJohn Again,john@example.com,Acme,\n";
        $result = (new RecipientImportService())->import($this->writeCsv($csv));

        $this->assertSame(1, $result['imported']);
        $this->assertSame(2, $result['duplicates']);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php spark test tests/services/RecipientImportServiceTest.php`
Expected: FAIL — class not found.

- [ ] **Step 3: Implement**

```php
<?php
// app/Services/RecipientImportService.php
namespace App\Services;

use App\Models\RecipientModel;

class RecipientImportService
{
    public function import(string $csvPath): array
    {
        $model = new RecipientModel();
        $summary = ['imported' => 0, 'skipped' => 0, 'invalid' => 0, 'duplicates' => 0, 'errors' => []];

        $handle = fopen($csvPath, 'r');
        if ($handle === false) {
            $summary['errors'][] = 'Could not read the uploaded file.';
            return $summary;
        }

        $header = fgetcsv($handle);
        if ($header === false || ! in_array('Email', $header, true)) {
            $summary['errors'][] = 'CSV must include a header row with an Email column.';
            fclose($handle);
            return $summary;
        }
        $header = array_map('trim', $header);

        $seenInFile = [];
        $rowNum = 1;

        while (($row = fgetcsv($handle)) !== false) {
            $rowNum++;
            if (count(array_filter($row, fn ($v) => $v !== '' && $v !== null)) === 0) {
                continue;
            }
            $data = array_combine($header, array_pad($row, count($header), ''));

            $email = trim($data['Email'] ?? '');
            $name = trim($data['Name'] ?? '');

            if ($email === '' || $name === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($name) > 150) {
                $summary['invalid']++;
                $summary['errors'][] = "Row {$rowNum}: invalid name or email.";
                continue;
            }

            $emailLower = strtolower($email);
            if (isset($seenInFile[$emailLower])) {
                $summary['duplicates']++;
                continue;
            }
            $seenInFile[$emailLower] = true;

            if ($model->where('email', $email)->first()) {
                $summary['duplicates']++;
                continue;
            }

            $inserted = $model->insert([
                'name'    => $name,
                'email'   => $email,
                'company' => trim($data['Company'] ?? '') ?: null,
                'phone'   => trim($data['Phone'] ?? '') ?: null,
            ], false);

            if ($inserted) {
                $summary['imported']++;
            } else {
                $summary['skipped']++;
                $summary['errors'][] = "Row {$rowNum}: " . implode('; ', $model->errors());
            }
        }

        fclose($handle);
        return $summary;
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php spark test tests/services/RecipientImportServiceTest.php`
Expected: PASS.

- [ ] **Step 5: Wire the controller import/export actions**

Add to `app/Controllers/RecipientController.php`:

```php
public function import()
{
    $file = $this->request->getFile('csv');

    if (! $file || ! $file->isValid()) {
        session()->setFlashdata('error', 'Please choose a valid CSV file.');
        return redirect()->to('/recipients');
    }

    if ($file->getSize() > 2 * 1024 * 1024) {
        session()->setFlashdata('error', 'CSV file must be smaller than 2MB.');
        return redirect()->to('/recipients');
    }

    $mime = $file->getMimeType();
    $ext = strtolower($file->getClientExtension());
    if ($ext !== 'csv' || ! in_array($mime, ['text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel'], true)) {
        session()->setFlashdata('error', 'Only CSV files are allowed.');
        return redirect()->to('/recipients');
    }

    $newName = $file->getRandomName();
    $file->move(WRITEPATH . 'uploads', $newName);
    $path = WRITEPATH . 'uploads/' . $newName;

    $summary = (new \App\Services\RecipientImportService())->import($path);
    @unlink($path);

    \App\Services\ActivityLogger::log(session()->get('user_id'), 'recipients.imported',
        "CSV import: {$summary['imported']} imported, {$summary['duplicates']} duplicates, {$summary['invalid']} invalid");

    session()->setFlashdata('importSummary', $summary);
    return redirect()->to('/recipients');
}

public function export()
{
    $model = new RecipientModel();
    $rows = $model->orderBy('created_at', 'DESC')->findAll();

    $this->response->setHeader('Content-Type', 'text/csv');
    $this->response->setHeader('Content-Disposition', 'attachment; filename="recipients.csv"');

    $out = fopen('php://temp', 'w');
    fputcsv($out, ['Name', 'Email', 'Company', 'Phone', 'Status']);
    foreach ($rows as $r) {
        fputcsv($out, [$r['name'], $r['email'], $r['company'], $r['phone'], $r['status']]);
    }
    rewind($out);
    $csv = stream_get_contents($out);
    fclose($out);

    return $this->response->setBody($csv);
}
```

Add routes:

```php
$routes->post('recipients/import', 'RecipientController::import');
$routes->get('recipients/export', 'RecipientController::export');
```

The import summary alert, "Export CSV"/"Import CSV" buttons, and the import modal were already added to `app/Views/recipients/index.php` in Task 7 (its view ships the full recipients page including CSV UI up front) — no view changes needed in this task.

- [ ] **Step 6: Run tests to confirm no regression**

Run: `php spark test tests/controllers/RecipientControllerTest.php tests/services/RecipientImportServiceTest.php`
Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add app/Services/RecipientImportService.php app/Controllers/RecipientController.php app/Views/recipients/index.php app/Config/Routes.php tests/services/RecipientImportServiceTest.php
git commit -m "feat: add CSV import with validation summary and CSV export"
```

---

### Task 9: Email templates (model, renderer, CRUD, preview)

**Files:**
- Create: `app/Models/EmailTemplateModel.php`
- Create: `app/Services/TemplateRenderer.php`
- Create: `app/Controllers/TemplateController.php`
- Create: `app/Views/templates/index.php`
- Create: `app/Views/templates/form.php`
- Create: `app/Views/templates/preview.php`
- Modify: `app/Config/Routes.php`
- Test: `tests/services/TemplateRendererTest.php`
- Test: `tests/controllers/TemplateControllerTest.php`

**Interfaces:**
- Produces: `TemplateRenderer::render(string $body, array $recipient): string` — replaces `{{name}}`, `{{email}}`, `{{company}}` only (whitelist), leaves unknown `{{...}}` tokens untouched, never executes PHP/HTML as code beyond what the browser renders in a sandboxed iframe.

- [ ] **Step 1: Write the failing renderer test**

```php
<?php
// tests/services/TemplateRendererTest.php
namespace Tests\Services;

use App\Services\TemplateRenderer;
use CodeIgniter\Test\CIUnitTestCase;

final class TemplateRendererTest extends CIUnitTestCase
{
    public function testReplacesKnownPlaceholders(): void
    {
        $out = (new TemplateRenderer())->render('Hi {{name}}, from {{company}} ({{email}})', [
            'name' => 'Jane', 'email' => 'jane@example.com', 'company' => 'Acme',
        ]);

        $this->assertSame('Hi Jane, from Acme (jane@example.com)', $out);
    }

    public function testLeavesUnknownTokensUntouched(): void
    {
        $out = (new TemplateRenderer())->render('Hi {{name}}, code {{php_eval}}', ['name' => 'Jane', 'email' => '', 'company' => '']);
        $this->assertStringContainsString('{{php_eval}}', $out);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php spark test tests/services/TemplateRendererTest.php`
Expected: FAIL — class not found.

- [ ] **Step 3: Implement `TemplateRenderer`**

```php
<?php
// app/Services/TemplateRenderer.php
namespace App\Services;

class TemplateRenderer
{
    public function render(string $body, array $recipient): string
    {
        $replacements = [
            '{{name}}'    => $recipient['name'] ?? '',
            '{{email}}'   => $recipient['email'] ?? '',
            '{{company}}' => $recipient['company'] ?? '',
        ];

        return strtr($body, $replacements);
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php spark test tests/services/TemplateRendererTest.php`
Expected: PASS.

- [ ] **Step 5: Write the failing controller test**

```php
<?php
// tests/controllers/TemplateControllerTest.php
namespace Tests\Controllers;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

final class TemplateControllerTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $refresh = true;

    private function loggedIn(): self
    {
        $this->db->table('users')->insert([
            'id' => 1, 'name' => 'Admin', 'email' => 'admin@test.com',
            'password_hash' => password_hash('x', PASSWORD_DEFAULT),
            'role' => 'owner', 'status' => 'active',
            'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);
        return $this->withSession(['isLoggedIn' => true, 'user_id' => 1, 'user_role' => 'owner', 'user_name' => 'Admin']);
    }

    public function testCreateTemplate(): void
    {
        $result = $this->loggedIn()->post('/templates/create', [
            'name' => 'Welcome', 'subject' => 'Hi {{name}}', 'html_body' => '<p>Hello {{name}}</p>', 'text_body' => 'Hello {{name}}',
        ]);

        $result->assertRedirect();
        $this->seeInDatabase('email_templates', ['name' => 'Welcome']);
    }

    public function testDuplicateTemplate(): void
    {
        $this->db->table('email_templates')->insert([
            'id' => 1, 'name' => 'Welcome', 'subject' => 'Hi', 'html_body' => '<p>Hi</p>', 'status' => 'active',
            'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $result = $this->loggedIn()->post('/templates/duplicate/1');

        $result->assertRedirect();
        $this->assertSame(2, $this->db->table('email_templates')->countAllResults());
    }

    public function testDeleteTemplate(): void
    {
        $this->db->table('email_templates')->insert([
            'id' => 1, 'name' => 'Welcome', 'subject' => 'Hi', 'html_body' => '<p>Hi</p>', 'status' => 'active',
            'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $result = $this->loggedIn()->post('/templates/delete/1');

        $result->assertRedirect();
        $this->dontSeeInDatabase('email_templates', ['id' => 1]);
    }
}
```

- [ ] **Step 6: Run test to verify it fails**

Run: `php spark test tests/controllers/TemplateControllerTest.php`
Expected: FAIL.

- [ ] **Step 7: Implement model + controller + views**

```php
<?php
// app/Models/EmailTemplateModel.php
namespace App\Models;

use CodeIgniter\Model;

class EmailTemplateModel extends Model
{
    protected $table         = 'email_templates';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = ['name', 'subject', 'html_body', 'text_body', 'status'];
    protected $useTimestamps = true;

    protected $validationRules = [
        'name'    => 'required|max_length[150]',
        'subject' => 'required|max_length[255]',
        'html_body' => 'required',
    ];
}
```

```php
<?php
// app/Controllers/TemplateController.php
namespace App\Controllers;

use App\Models\EmailTemplateModel;
use App\Services\ActivityLogger;
use CodeIgniter\Controller;

class TemplateController extends Controller
{
    public function index()
    {
        $model = new EmailTemplateModel();
        return view('templates/index', ['title' => 'Email Templates', 'templates' => $model->orderBy('created_at', 'DESC')->paginate(15), 'pager' => $model->pager]);
    }

    public function create()
    {
        if ($this->request->getMethod() === 'GET') {
            return view('templates/form', ['title' => 'Create Template', 'template' => null]);
        }

        $model = new EmailTemplateModel();
        $data = $this->request->getPost(['name', 'subject', 'html_body', 'text_body']);

        if (! $model->insert($data)) {
            return view('templates/form', ['title' => 'Create Template', 'template' => $data, 'errors' => $model->errors()]);
        }

        ActivityLogger::log(session()->get('user_id'), 'template.created', 'Template created: ' . $data['name']);
        session()->setFlashdata('success', 'Template created successfully.');
        return redirect()->to('/templates');
    }

    public function edit($id)
    {
        $model = new EmailTemplateModel();
        $template = $model->find($id);
        if (! $template) {
            return redirect()->to('/templates')->with('error', 'Template not found.');
        }

        if ($this->request->getMethod() === 'GET') {
            return view('templates/form', ['title' => 'Edit Template', 'template' => $template]);
        }

        $data = $this->request->getPost(['name', 'subject', 'html_body', 'text_body']);
        if (! $model->update($id, $data)) {
            return view('templates/form', ['title' => 'Edit Template', 'template' => array_merge(['id' => $id], $data), 'errors' => $model->errors()]);
        }

        ActivityLogger::log(session()->get('user_id'), 'template.updated', 'Template updated: ' . $data['name']);
        session()->setFlashdata('success', 'Template updated successfully.');
        return redirect()->to('/templates');
    }

    public function delete($id)
    {
        $model = new EmailTemplateModel();
        $template = $model->find($id);
        if ($template) {
            $model->delete($id);
            ActivityLogger::log(session()->get('user_id'), 'template.deleted', 'Template deleted: ' . $template['name']);
        }
        session()->setFlashdata('success', 'Template deleted.');
        return redirect()->to('/templates');
    }

    public function duplicate($id)
    {
        $model = new EmailTemplateModel();
        $template = $model->find($id);
        if ($template) {
            unset($template['id']);
            $template['name'] .= ' (Copy)';
            $model->insert($template);
        }
        session()->setFlashdata('success', 'Template duplicated.');
        return redirect()->to('/templates');
    }

    public function preview($id)
    {
        $model = new EmailTemplateModel();
        $template = $model->find($id);
        if (! $template) {
            return redirect()->to('/templates');
        }

        $rendered = (new \App\Services\TemplateRenderer())->render($template['html_body'], [
            'name' => 'Sample Name', 'email' => 'sample@example.com', 'company' => 'Sample Co',
        ]);

        return view('templates/preview', ['title' => 'Preview', 'rendered' => $rendered]);
    }
}
```

```php
<?php
// app/Views/templates/form.php
?>
<?= $this->extend('layout/main') ?>
<?= $this->section('content') ?>
<?php if (! empty($errors)) : ?>
    <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $e) : ?><li><?= esc($e) ?></li><?php endforeach ?></ul></div>
<?php endif ?>
<form method="post" action="<?= $template['id'] ?? null ? '/templates/edit/' . $template['id'] : '/templates/create' ?>">
    <?= csrf_field() ?>
    <div class="row g-3">
        <div class="col-md-8">
            <div class="mb-3">
                <label class="form-label">Template Name</label>
                <input type="text" name="name" class="form-control" value="<?= esc($template['name'] ?? '') ?>" required maxlength="150">
            </div>
            <div class="mb-3">
                <label class="form-label">Subject</label>
                <input type="text" name="subject" class="form-control" value="<?= esc($template['subject'] ?? '') ?>" required maxlength="255">
            </div>
            <div class="mb-3">
                <label class="form-label">HTML Body</label>
                <div id="editor" style="height:250px;background:#fff;"><?= $template['html_body'] ?? '' ?></div>
                <textarea name="html_body" id="htmlBodyInput" style="display:none;"></textarea>
            </div>
            <div class="mb-3">
                <label class="form-label">Plain Text Fallback</label>
                <textarea name="text_body" class="form-control" rows="3"><?= esc($template['text_body'] ?? '') ?></textarea>
            </div>
            <p class="small text-muted">Placeholders: <code>{{name}}</code> <code>{{email}}</code> <code>{{company}}</code></p>
            <button type="submit" class="btn btn-primary">Save Template</button>
            <a href="/templates" class="btn btn-outline-secondary">Cancel</a>
        </div>
    </div>
</form>
<link href="https://cdnjs.cloudflare.com/ajax/libs/quill/1.3.7/quill.snow.min.css" rel="stylesheet">
<script src="https://cdnjs.cloudflare.com/ajax/libs/quill/1.3.7/quill.min.js"></script>
<script>
const quill = new Quill('#editor', { theme: 'snow' });
document.querySelector('form').addEventListener('submit', function () {
    document.getElementById('htmlBodyInput').value = document.querySelector('#editor .ql-editor').innerHTML;
});
</script>
<?= $this->endSection() ?>
```

```php
<?php
// app/Views/templates/index.php
?>
<?= $this->extend('layout/main') ?>
<?= $this->section('content') ?>
<div class="d-flex justify-content-between mb-3">
    <h5 class="mb-0"></h5>
    <a href="/templates/create" class="btn btn-primary">Create Template</a>
</div>
<?php if (session()->getFlashdata('success')) : ?><div class="alert alert-success"><?= esc(session()->getFlashdata('success')) ?></div><?php endif ?>
<div class="card border-0 shadow-sm">
<?php if (empty($templates)) : ?>
    <div class="card-body text-center text-muted py-5">No templates yet. Create your first email template.</div>
<?php else : ?>
    <table class="table table-hover mb-0">
        <thead><tr><th>Name</th><th>Subject</th><th>Status</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($templates as $t) : ?>
            <tr>
                <td><?= esc($t['name']) ?></td>
                <td><?= esc($t['subject']) ?></td>
                <td><span class="badge text-bg-secondary"><?= esc($t['status']) ?></span></td>
                <td class="text-end">
                    <a href="/templates/preview/<?= (int) $t['id'] ?>" class="btn btn-sm btn-outline-secondary" target="_blank">Preview</a>
                    <a href="/templates/edit/<?= (int) $t['id'] ?>" class="btn btn-sm btn-outline-secondary">Edit</a>
                    <form method="post" action="/templates/duplicate/<?= (int) $t['id'] ?>" class="d-inline"><?= csrf_field() ?><button class="btn btn-sm btn-outline-secondary">Duplicate</button></form>
                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="deleteTemplate(<?= (int) $t['id'] ?>)">Delete</button>
                </td>
            </tr>
        <?php endforeach ?>
        </tbody>
    </table>
<?php endif ?>
</div>
<div class="mt-3"><?= $pager->links() ?></div>
<form id="deleteForm" method="post" style="display:none;"><?= csrf_field() ?></form>
<script>
function deleteTemplate(id) {
    confirmAction('Delete this template? This action cannot be undone.', function () {
        const form = document.getElementById('deleteForm');
        form.action = '/templates/delete/' + id;
        form.submit();
    });
}
</script>
<?= $this->endSection() ?>
```

```php
<?php
// app/Views/templates/preview.php
?>
<!doctype html>
<html><head><meta charset="utf-8"><title>Template Preview</title></head>
<body style="margin:0;padding:20px;font-family:sans-serif;">
<?= $rendered ?>
</body></html>
```

Note: `preview.php` intentionally renders outside the main app layout/session-authenticated shell styling (it's opened in a new tab as a sandboxed preview) but the route itself still sits behind the `auth` filter.

- [ ] **Step 8: Add routes**

```php
$routes->get('templates', 'TemplateController::index');
$routes->match(['get', 'post'], 'templates/create', 'TemplateController::create');
$routes->match(['get', 'post'], 'templates/edit/(:num)', 'TemplateController::edit/$1');
$routes->post('templates/delete/(:num)', 'TemplateController::delete/$1');
$routes->post('templates/duplicate/(:num)', 'TemplateController::duplicate/$1');
$routes->get('templates/preview/(:num)', 'TemplateController::preview/$1');
```

- [ ] **Step 9: Run tests to verify they pass**

Run: `php spark test tests/controllers/TemplateControllerTest.php tests/services/TemplateRendererTest.php`
Expected: PASS.

- [ ] **Step 10: Commit**

```bash
git add app/Models/EmailTemplateModel.php app/Services/TemplateRenderer.php app/Controllers/TemplateController.php app/Views/templates app/Config/Routes.php tests/services/TemplateRendererTest.php tests/controllers/TemplateControllerTest.php
git commit -m "feat: add email template CRUD, safe placeholder rendering, and preview"
```

---

### Task 10: SMTP settings (encrypted storage, presets, test connection)

**Files:**
- Create: `app/Services/SmtpConfigService.php`
- Create: `app/Controllers/SmtpController.php`
- Create: `app/Views/smtp/index.php`
- Modify: `app/Config/Routes.php`
- Test: `tests/services/SmtpConfigServiceTest.php`
- Test: `tests/controllers/SmtpControllerTest.php`

**Interfaces:**
- Produces: `SmtpConfigService::save(array $data): int` (encrypts password, deactivates other rows, returns new row id), `SmtpConfigService::getActive(): ?array` (returns row with `password` decrypted, for internal use only — never passed to a view/JSON response), `SmtpConfigService::getActiveMasked(): ?array` (same row but `password` replaced with `'••••••••'`, for display).
- Consumes: `encryption.key` from `.env` (Task 1).

- [ ] **Step 1: Write the failing service test**

```php
<?php
// tests/services/SmtpConfigServiceTest.php
namespace Tests\Services;

use App\Services\SmtpConfigService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

final class SmtpConfigServiceTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $refresh = true;

    private function payload(): array
    {
        return [
            'label' => 'Gmail', 'host' => 'smtp.gmail.com', 'port' => 587, 'encryption' => 'tls',
            'username' => 'me@gmail.com', 'password' => 'app-password-secret', 'from_email' => 'me@gmail.com', 'from_name' => 'Me',
        ];
    }

    public function testSaveEncryptsPasswordAtRest(): void
    {
        (new SmtpConfigService())->save($this->payload());

        $row = $this->db->table('smtp_settings')->get()->getRowArray();
        $this->assertStringNotContainsString('app-password-secret', $row['password_encrypted']);
    }

    public function testGetActiveDecryptsPassword(): void
    {
        (new SmtpConfigService())->save($this->payload());

        $active = (new SmtpConfigService())->getActive();
        $this->assertSame('app-password-secret', $active['password']);
    }

    public function testGetActiveMaskedNeverExposesPassword(): void
    {
        (new SmtpConfigService())->save($this->payload());

        $masked = (new SmtpConfigService())->getActiveMasked();
        $this->assertArrayNotHasKey('password_encrypted', $masked);
        $this->assertSame('••••••••', $masked['password']);
    }

    public function testSavingNewConfigDeactivatesOldOne(): void
    {
        $service = new SmtpConfigService();
        $service->save($this->payload());
        $service->save(array_merge($this->payload(), ['label' => 'Custom', 'host' => 'smtp.other.com']));

        $this->assertSame(1, $this->db->table('smtp_settings')->where('is_active', 1)->countAllResults());
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php spark test tests/services/SmtpConfigServiceTest.php`
Expected: FAIL — class not found.

- [ ] **Step 3: Implement**

```php
<?php
// app/Services/SmtpConfigService.php
namespace App\Services;

use Config\Encryption as EncryptionConfig;

class SmtpConfigService
{
    private function encrypter()
    {
        return \Config\Services::encrypter();
    }

    public function save(array $data): int
    {
        $db = db_connect();
        $db->table('smtp_settings')->where('is_active', 1)->update(['is_active' => 0]);

        $encrypted = base64_encode($this->encrypter()->encrypt($data['password']));

        $db->table('smtp_settings')->insert([
            'label'              => $data['label'],
            'host'               => $data['host'],
            'port'               => (int) $data['port'],
            'encryption'         => $data['encryption'],
            'username'           => $data['username'],
            'password_encrypted' => $encrypted,
            'from_email'         => $data['from_email'],
            'from_name'          => $data['from_name'],
            'is_active'          => 1,
            'created_at'         => date('Y-m-d H:i:s'),
            'updated_at'         => date('Y-m-d H:i:s'),
        ]);

        return (int) $db->insertID();
    }

    public function getActive(): ?array
    {
        $row = db_connect()->table('smtp_settings')->where('is_active', 1)->get()->getRowArray();
        if (! $row) {
            return null;
        }

        $row['password'] = $this->encrypter()->decrypt(base64_decode($row['password_encrypted']));
        unset($row['password_encrypted']);

        return $row;
    }

    public function getActiveMasked(): ?array
    {
        $row = db_connect()->table('smtp_settings')->where('is_active', 1)->get()->getRowArray();
        if (! $row) {
            return null;
        }

        unset($row['password_encrypted']);
        $row['password'] = '••••••••';

        return $row;
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php spark test tests/services/SmtpConfigServiceTest.php`
Expected: PASS.

- [ ] **Step 5: Write the failing controller test**

```php
<?php
// tests/controllers/SmtpControllerTest.php
namespace Tests\Controllers;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

final class SmtpControllerTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $refresh = true;

    private function loggedIn(): self
    {
        $this->db->table('users')->insert([
            'id' => 1, 'name' => 'Admin', 'email' => 'admin@test.com',
            'password_hash' => password_hash('x', PASSWORD_DEFAULT),
            'role' => 'owner', 'status' => 'active',
            'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);
        return $this->withSession(['isLoggedIn' => true, 'user_id' => 1, 'user_role' => 'owner', 'user_name' => 'Admin']);
    }

    public function testSaveSmtpSettingsNeverReturnsPasswordInResponse(): void
    {
        $result = $this->loggedIn()->post('/smtp', [
            'label' => 'Gmail', 'host' => 'smtp.gmail.com', 'port' => 587, 'encryption' => 'tls',
            'username' => 'me@gmail.com', 'password' => 'super-secret-pass', 'from_email' => 'me@gmail.com', 'from_name' => 'Me',
        ]);

        $result->assertRedirect();
        $page = $this->loggedIn()->get('/smtp');
        $page->assertDontSee('super-secret-pass');
    }
}
```

- [ ] **Step 6: Run test to verify it fails**

Run: `php spark test tests/controllers/SmtpControllerTest.php`
Expected: FAIL.

- [ ] **Step 7: Implement controller, view, routes**

```php
<?php
// app/Controllers/SmtpController.php
namespace App\Controllers;

use App\Services\ActivityLogger;
use App\Services\SmtpConfigService;
use CodeIgniter\Controller;
use Config\Services as CoreServices;

class SmtpController extends Controller
{
    public function index()
    {
        $service = new SmtpConfigService();
        return view('smtp/index', ['title' => 'SMTP Settings', 'config' => $service->getActiveMasked()]);
    }

    public function save()
    {
        $rules = [
            'label'      => 'required|max_length[100]',
            'host'       => 'required|max_length[191]',
            'port'       => 'required|integer',
            'encryption' => 'required|in_list[tls,ssl]',
            'username'   => 'required|max_length[191]',
            'password'   => 'required|max_length[255]',
            'from_email' => 'required|valid_email',
            'from_name'  => 'required|max_length[150]',
        ];

        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        (new SmtpConfigService())->save($this->request->getPost([
            'label', 'host', 'port', 'encryption', 'username', 'password', 'from_email', 'from_name',
        ]));

        ActivityLogger::log(session()->get('user_id'), 'smtp.updated', 'SMTP configuration updated (host: ' . $this->request->getPost('host') . ')');
        session()->setFlashdata('success', 'SMTP configuration saved.');
        return redirect()->to('/smtp');
    }

    public function test()
    {
        $testEmail = $this->request->getPost('test_email');
        if (! $testEmail || ! filter_var($testEmail, FILTER_VALIDATE_EMAIL)) {
            return $this->response->setJSON(['success' => false, 'message' => 'Enter a valid email address to send the test to.']);
        }

        $config = (new SmtpConfigService())->getActive();
        if (! $config) {
            return $this->response->setJSON(['success' => false, 'message' => 'SMTP is not configured yet.']);
        }

        $email = CoreServices::email();
        $email->setFrom($config['from_email'], $config['from_name']);
        $email->setTo($testEmail);
        $email->setSubject('SMTP Test — Email Manager');
        $email->setMessage('This is a test email confirming your SMTP configuration works.');

        $email->initialize([
            'protocol'   => 'smtp',
            'SMTPHost'   => $config['host'],
            'SMTPPort'   => $config['port'],
            'SMTPCrypto' => $config['encryption'],
            'SMTPUser'   => $config['username'],
            'SMTPPass'   => $config['password'],
        ]);

        $sent = $email->send();

        if (! $sent) {
            log_message('error', 'SMTP test failed: {debug}', ['debug' => $email->printDebugger(['headers'])]);
            return $this->response->setJSON(['success' => false, 'message' => 'Unable to connect to the SMTP server. Please check your SMTP configuration.']);
        }

        return $this->response->setJSON(['success' => true, 'message' => 'Test email sent successfully.']);
    }
}
```

```php
<?php
// app/Views/smtp/index.php
?>
<?= $this->extend('layout/main') ?>
<?= $this->section('content') ?>
<?php if (session()->getFlashdata('success')) : ?><div class="alert alert-success"><?= esc(session()->getFlashdata('success')) ?></div><?php endif ?>
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
    fetch('/smtp/test', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-TOKEN': '<?= csrf_hash() ?>' },
        body: 'test_email=' + encodeURIComponent(email) + '&<?= csrf_token() ?>=<?= csrf_hash() ?>',
    }).then(r => r.json()).then(data => {
        resultEl.textContent = data.message;
        resultEl.className = 'mt-2 small ' + (data.success ? 'text-success' : 'text-danger');
        showToast(data.message, data.success ? 'success' : 'danger');
    });
}
</script>
<?= $this->endSection() ?>
```

Add routes:

```php
$routes->get('smtp', 'SmtpController::index');
$routes->post('smtp', 'SmtpController::save');
$routes->post('smtp/test', 'SmtpController::test');
```

- [ ] **Step 8: Run tests to verify they pass**

Run: `php spark test tests/controllers/SmtpControllerTest.php tests/services/SmtpConfigServiceTest.php`
Expected: PASS.

- [ ] **Step 9: Commit**

```bash
git add app/Services/SmtpConfigService.php app/Controllers/SmtpController.php app/Views/smtp app/Config/Routes.php tests/services/SmtpConfigServiceTest.php tests/controllers/SmtpControllerTest.php
git commit -m "feat: add encrypted SMTP settings with presets and test-connection"
```

---

### Task 11: EmailSenderService (one-by-one send + recording)

**Files:**
- Create: `app/Services/EmailSenderService.php`
- Test: `tests/services/EmailSenderServiceTest.php`

**Interfaces:**
- Produces: `EmailSenderService::send(int $recipientId, string $subject, string $bodyHtml, ?int $templateId, int $userId): array{email_id:int, status:string, error:?string}` — validates recipient exists and is active, loads active SMTP config via `SmtpConfigService::getActive()`, sends via CI4 `Email` service, writes an `emails` row either way, increments `attempt_count`.
- Consumes: `RecipientModel` (Task 7), `SmtpConfigService` (Task 10).

- [ ] **Step 1: Write the failing test**

```php
<?php
// tests/services/EmailSenderServiceTest.php
namespace Tests\Services;

use App\Services\EmailSenderService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

final class EmailSenderServiceTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $refresh = true;

    protected function setUp(): void
    {
        parent::setUp();
        $this->db->table('users')->insert([
            'id' => 1, 'name' => 'Admin', 'email' => 'admin@test.com',
            'password_hash' => password_hash('x', PASSWORD_DEFAULT), 'role' => 'owner', 'status' => 'active',
            'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $this->db->table('recipients')->insert([
            'id' => 1, 'name' => 'Jane', 'email' => 'jane@example.com', 'status' => 'active',
            'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function testMissingSmtpConfigRecordsFailedEmail(): void
    {
        $result = (new EmailSenderService())->send(1, 'Hello', '<p>Hi</p>', null, 1);

        $this->assertSame('failed', $result['status']);
        $this->seeInDatabase('emails', ['recipient_id' => 1, 'status' => 'failed']);
    }

    public function testUnknownRecipientRecordsNothingAndReturnsError(): void
    {
        $result = (new EmailSenderService())->send(999, 'Hello', '<p>Hi</p>', null, 1);

        $this->assertSame('failed', $result['status']);
        $this->assertSame('Recipient not found.', $result['error']);
        $this->assertSame(0, $this->db->table('emails')->countAllResults());
    }

    public function testUnsubscribedRecipientIsRejected(): void
    {
        $this->db->table('recipients')->where('id', 1)->update(['status' => 'unsubscribed']);

        $result = (new EmailSenderService())->send(1, 'Hello', '<p>Hi</p>', null, 1);

        $this->assertSame('failed', $result['status']);
        $this->assertSame('Recipient has unsubscribed.', $result['error']);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php spark test tests/services/EmailSenderServiceTest.php`
Expected: FAIL — class not found.

- [ ] **Step 3: Implement**

```php
<?php
// app/Services/EmailSenderService.php
namespace App\Services;

use Config\Services as CoreServices;

class EmailSenderService
{
    public function send(int $recipientId, string $subject, string $bodyHtml, ?int $templateId, int $userId): array
    {
        $db = db_connect();
        $recipient = $db->table('recipients')->where('id', $recipientId)->get()->getRowArray();

        if (! $recipient) {
            return ['email_id' => 0, 'status' => 'failed', 'error' => 'Recipient not found.'];
        }

        if ($recipient['status'] !== 'active') {
            return ['email_id' => 0, 'status' => 'failed', 'error' => 'Recipient has unsubscribed.'];
        }

        $config = (new SmtpConfigService())->getActive();

        $emailId = (int) $db->table('emails')->insert([
            'recipient_id'  => $recipientId,
            'template_id'   => $templateId,
            'user_id'       => $userId,
            'subject'       => $subject,
            'body_html'     => $bodyHtml,
            'status'        => 'pending',
            'attempt_count' => 1,
            'created_at'    => date('Y-m-d H:i:s'),
            'updated_at'    => date('Y-m-d H:i:s'),
        ]) ? $db->insertID() : 0;

        if (! $config) {
            $this->markFailed($emailId, 'SMTP is not configured. Please configure SMTP settings first.');
            return ['email_id' => $emailId, 'status' => 'failed', 'error' => 'SMTP is not configured. Please configure SMTP settings first.'];
        }

        $rendered = (new TemplateRenderer())->render($bodyHtml, $recipient);

        $email = CoreServices::email(null, false);
        $email->initialize([
            'protocol'   => 'smtp',
            'SMTPHost'   => $config['host'],
            'SMTPPort'   => $config['port'],
            'SMTPCrypto' => $config['encryption'],
            'SMTPUser'   => $config['username'],
            'SMTPPass'   => $config['password'],
            'mailType'   => 'html',
        ]);
        $email->setFrom($config['from_email'], $config['from_name']);
        $email->setTo($recipient['email']);
        $email->setSubject($subject);
        $email->setMessage($rendered);

        $sent = $email->send();

        if (! $sent) {
            $debug = $email->printDebugger(['headers']);
            log_message('error', 'Email send failed for recipient {id}: {debug}', ['id' => $recipientId, 'debug' => $debug]);
            $this->markFailed($emailId, 'Unable to connect to the SMTP server. Please check your SMTP configuration.');
            return ['email_id' => $emailId, 'status' => 'failed', 'error' => 'Unable to connect to the SMTP server. Please check your SMTP configuration.'];
        }

        $db->table('emails')->where('id', $emailId)->update([
            'status'     => 'sent',
            'sent_at'    => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return ['email_id' => $emailId, 'status' => 'sent', 'error' => null];
    }

    private function markFailed(int $emailId, string $message): void
    {
        if ($emailId === 0) {
            return;
        }
        db_connect()->table('emails')->where('id', $emailId)->update([
            'status'        => 'failed',
            'error_message' => $message,
            'updated_at'    => date('Y-m-d H:i:s'),
        ]);
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php spark test tests/services/EmailSenderServiceTest.php`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Services/EmailSenderService.php tests/services/EmailSenderServiceTest.php
git commit -m "feat: add one-by-one email sender service with result recording"
```

---

### Task 12: Compose email

**Files:**
- Create: `app/Controllers/ComposeController.php`
- Create: `app/Views/compose/index.php`
- Modify: `app/Config/Routes.php`
- Test: `tests/controllers/ComposeControllerTest.php`

**Interfaces:**
- Consumes: `RecipientModel` (Task 7), `EmailTemplateModel` (Task 9), `EmailSenderService` (Task 11).

- [ ] **Step 1: Write the failing test**

```php
<?php
// tests/controllers/ComposeControllerTest.php
namespace Tests\Controllers;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

final class ComposeControllerTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $refresh = true;

    private function loggedIn(): self
    {
        $this->db->table('users')->insert([
            'id' => 1, 'name' => 'Admin', 'email' => 'admin@test.com',
            'password_hash' => password_hash('x', PASSWORD_DEFAULT), 'role' => 'owner', 'status' => 'active',
            'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);
        return $this->withSession(['isLoggedIn' => true, 'user_id' => 1, 'user_role' => 'owner', 'user_name' => 'Admin']);
    }

    public function testComposePageLoads(): void
    {
        $result = $this->loggedIn()->get('/compose');
        $result->assertStatus(200);
        $result->assertSee('Compose Email');
    }

    public function testSendWithoutSmtpRecordsFailure(): void
    {
        $this->db->table('recipients')->insert([
            'id' => 1, 'name' => 'Jane', 'email' => 'jane@example.com', 'status' => 'active',
            'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $result = $this->loggedIn()->post('/compose/send', [
            'recipient_id' => 1, 'subject' => 'Hi', 'body_html' => '<p>Hi</p>',
        ]);

        $result->assertOK();
        $this->seeInDatabase('emails', ['recipient_id' => 1, 'status' => 'failed']);
    }

    public function testSaveDraftStoresWithoutSending(): void
    {
        $this->db->table('recipients')->insert([
            'id' => 1, 'name' => 'Jane', 'email' => 'jane@example.com', 'status' => 'active',
            'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $result = $this->loggedIn()->post('/compose/draft', [
            'recipient_id' => 1, 'subject' => 'Draft subject', 'body_html' => '<p>Draft</p>',
        ]);

        $result->assertOK();
        $this->seeInDatabase('emails', ['recipient_id' => 1, 'status' => 'draft', 'subject' => 'Draft subject']);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php spark test tests/controllers/ComposeControllerTest.php`
Expected: FAIL.

- [ ] **Step 3: Implement**

```php
<?php
// app/Controllers/ComposeController.php
namespace App\Controllers;

use App\Models\EmailTemplateModel;
use App\Models\RecipientModel;
use App\Services\EmailSenderService;
use CodeIgniter\Controller;

class ComposeController extends Controller
{
    public function index()
    {
        return view('compose/index', [
            'title'      => 'Compose Email',
            'recipients' => (new RecipientModel())->where('status', 'active')->orderBy('name')->findAll(),
            'templates'  => (new EmailTemplateModel())->where('status', 'active')->orderBy('name')->findAll(),
        ]);
    }

    public function send()
    {
        $rules = [
            'recipient_id' => 'required|integer',
            'subject'      => 'required|max_length[255]',
            'body_html'    => 'required',
        ];

        if (! $this->validate($rules)) {
            return $this->response->setJSON(['success' => false, 'message' => 'Please fill in all required fields.'])->setStatusCode(200);
        }

        $result = (new EmailSenderService())->send(
            (int) $this->request->getPost('recipient_id'),
            $this->request->getPost('subject'),
            $this->request->getPost('body_html'),
            $this->request->getPost('template_id') ?: null,
            (int) session()->get('user_id')
        );

        \App\Services\ActivityLogger::log(session()->get('user_id'),
            $result['status'] === 'sent' ? 'email.sent' : 'email.failed',
            'Email ' . $result['status'] . ' (recipient #' . $this->request->getPost('recipient_id') . ')');

        return $this->response->setJSON([
            'success' => $result['status'] === 'sent',
            'message' => $result['status'] === 'sent' ? 'Email sent successfully.' : ($result['error'] ?? 'Email delivery failed.'),
        ]);
    }

    public function saveDraft()
    {
        $rules = [
            'recipient_id' => 'required|integer',
            'subject'      => 'required|max_length[255]',
            'body_html'    => 'required',
        ];

        if (! $this->validate($rules)) {
            return $this->response->setJSON(['success' => false, 'message' => 'Please fill in all required fields.']);
        }

        db_connect()->table('emails')->insert([
            'recipient_id'  => (int) $this->request->getPost('recipient_id'),
            'template_id'   => $this->request->getPost('template_id') ?: null,
            'user_id'       => (int) session()->get('user_id'),
            'subject'       => $this->request->getPost('subject'),
            'body_html'     => $this->request->getPost('body_html'),
            'status'        => 'draft',
            'attempt_count' => 0,
            'created_at'    => date('Y-m-d H:i:s'),
            'updated_at'    => date('Y-m-d H:i:s'),
        ]);

        \App\Services\ActivityLogger::log(session()->get('user_id'), 'email.draft_saved', 'Draft saved (recipient #' . $this->request->getPost('recipient_id') . ')');

        return $this->response->setJSON(['success' => true, 'message' => 'Draft saved.']);
    }
}
```

```php
<?php
// app/Views/compose/index.php
?>
<?= $this->extend('layout/main') ?>
<?= $this->section('content') ?>
<div class="row g-4">
    <div class="col-md-7">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <form id="composeForm">
                    <div class="mb-3">
                        <label class="form-label">Recipient</label>
                        <select name="recipient_id" id="recipientSelect" class="form-select" required>
                            <option value="">Select recipient...</option>
                            <?php foreach ($recipients as $r) : ?>
                                <option value="<?= (int) $r['id'] ?>" data-email="<?= esc($r['email']) ?>"><?= esc($r['name']) ?> (<?= esc($r['email']) ?>)</option>
                            <?php endforeach ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Template</label>
                        <select id="templateSelect" class="form-select">
                            <option value="">Blank</option>
                            <?php foreach ($templates as $t) : ?>
                                <option value="<?= (int) $t['id'] ?>" data-subject="<?= esc($t['subject']) ?>" data-body="<?= esc($t['html_body']) ?>"><?= esc($t['name']) ?></option>
                            <?php endforeach ?>
                        </select>
                        <input type="hidden" name="template_id" id="templateIdInput">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Subject</label>
                        <input type="text" name="subject" id="subjectInput" class="form-control" required maxlength="255">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Message</label>
                        <div id="composeEditor" style="height:200px;background:#fff;"></div>
                        <input type="hidden" name="body_html" id="bodyHtmlInput">
                    </div>
                    <button type="button" class="btn btn-primary" onclick="confirmSend()">Send Email</button>
                    <button type="button" class="btn btn-outline-secondary" onclick="saveDraft()">Save Draft</button>
                    <button type="reset" class="btn btn-outline-secondary">Clear</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-md-5">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white">Preview</div>
            <div class="card-body" id="previewPane" style="min-height:200px;">Select a recipient and write a message to preview it here.</div>
        </div>
    </div>
</div>
<link href="https://cdnjs.cloudflare.com/ajax/libs/quill/1.3.7/quill.snow.min.css" rel="stylesheet">
<script src="https://cdnjs.cloudflare.com/ajax/libs/quill/1.3.7/quill.min.js"></script>
<script>
const quill = new Quill('#composeEditor', { theme: 'snow' });

document.getElementById('templateSelect').addEventListener('change', function (e) {
    const opt = e.target.selectedOptions[0];
    document.getElementById('templateIdInput').value = opt.value;
    if (opt.value) {
        document.getElementById('subjectInput').value = opt.dataset.subject;
        quill.root.innerHTML = opt.dataset.body;
    }
    updatePreview();
});
quill.on('text-change', updatePreview);
document.getElementById('subjectInput').addEventListener('input', updatePreview);

function updatePreview() {
    document.getElementById('previewPane').innerHTML = quill.root.innerHTML;
}

function confirmSend() {
    const select = document.getElementById('recipientSelect');
    const email = select.selectedOptions[0] ? select.selectedOptions[0].dataset.email : '';
    if (!select.value) { showToast('Please select a recipient.', 'warning'); return; }

    confirmAction('Send this email to ' + email + '?', function () {
        document.getElementById('bodyHtmlInput').value = quill.root.innerHTML;
        const form = document.getElementById('composeForm');
        const formData = new FormData(form);
        fetch('/compose/send', {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: formData,
        }).then(r => r.json()).then(data => {
            showToast(data.message, data.success ? 'success' : 'danger');
        });
    });
}

function saveDraft() {
    document.getElementById('bodyHtmlInput').value = quill.root.innerHTML;
    const formData = new FormData(document.getElementById('composeForm'));
    fetch('/compose/draft', {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: formData,
    }).then(r => r.json()).then(data => {
        showToast(data.message, data.success ? 'success' : 'danger');
    });
}
</script>
<?= $this->endSection() ?>
```

Note: the compose form must also carry the CSRF token in `FormData` — add `<?= csrf_field() ?>` as a hidden input inside `#composeForm` (immediately after `<form id="composeForm">`).

- [ ] **Step 4: Add routes**

```php
$routes->get('compose', 'ComposeController::index');
$routes->post('compose/send', 'ComposeController::send');
$routes->post('compose/draft', 'ComposeController::saveDraft');
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php spark test tests/controllers/ComposeControllerTest.php`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add app/Controllers/ComposeController.php app/Views/compose app/Config/Routes.php tests/controllers/ComposeControllerTest.php
git commit -m "feat: add compose email interface with live preview and confirm-to-send"
```

---

### Task 13: Email history, detail, retry

**Files:**
- Create: `app/Controllers/EmailController.php`
- Create: `app/Views/emails/index.php`
- Create: `app/Views/emails/detail.php`
- Modify: `app/Config/Routes.php`
- Test: `tests/controllers/EmailControllerTest.php`

**Interfaces:**
- Consumes: `EmailSenderService` (Task 11) for retry.

- [ ] **Step 1: Write the failing test**

```php
<?php
// tests/controllers/EmailControllerTest.php
namespace Tests\Controllers;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

final class EmailControllerTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $refresh = true;

    protected function setUp(): void
    {
        parent::setUp();
        $this->db->table('users')->insert([
            'id' => 1, 'name' => 'Admin', 'email' => 'admin@test.com',
            'password_hash' => password_hash('x', PASSWORD_DEFAULT), 'role' => 'owner', 'status' => 'active',
            'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $this->db->table('recipients')->insert([
            'id' => 1, 'name' => 'Jane', 'email' => 'jane@example.com', 'status' => 'active',
            'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    private function loggedIn(): self
    {
        return $this->withSession(['isLoggedIn' => true, 'user_id' => 1, 'user_role' => 'owner', 'user_name' => 'Admin']);
    }

    public function testHistoryListsEmailsWithFilter(): void
    {
        $this->db->table('emails')->insert([
            'recipient_id' => 1, 'user_id' => 1, 'subject' => 'Hi', 'body_html' => '<p>Hi</p>',
            'status' => 'failed', 'error_message' => 'boom',
            'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $result = $this->loggedIn()->get('/emails?status=failed');
        $result->assertStatus(200);
        $result->assertSee('Hi');
    }

    public function testDetailPageShowsErrorMessage(): void
    {
        $this->db->table('emails')->insert([
            'id' => 1, 'recipient_id' => 1, 'user_id' => 1, 'subject' => 'Hi', 'body_html' => '<p>Hi</p>',
            'status' => 'failed', 'error_message' => 'SMTP connection refused',
            'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $result = $this->loggedIn()->get('/emails/1');
        $result->assertSee('SMTP connection refused');
    }

    public function testRetryReSendsAndIncrementsAttempt(): void
    {
        $this->db->table('emails')->insert([
            'id' => 1, 'recipient_id' => 1, 'user_id' => 1, 'subject' => 'Hi', 'body_html' => '<p>Hi</p>',
            'status' => 'failed', 'attempt_count' => 1,
            'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $result = $this->loggedIn()->post('/emails/retry/1');

        $result->assertRedirect();
        $row = $this->db->table('emails')->where('id', 1)->get()->getRowArray();
        $this->assertSame('failed', $row['status']);
        $this->assertGreaterThan(1, $row['attempt_count']);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php spark test tests/controllers/EmailControllerTest.php`
Expected: FAIL.

- [ ] **Step 3: Implement**

```php
<?php
// app/Controllers/EmailController.php
namespace App\Controllers;

use App\Services\EmailSenderService;
use CodeIgniter\Controller;

class EmailController extends Controller
{
    public function index()
    {
        $db = db_connect();
        $builder = $db->table('emails e')
            ->select('e.*, r.name as recipient_name, r.email as recipient_email, u.name as user_name')
            ->join('recipients r', 'r.id = e.recipient_id')
            ->join('users u', 'u.id = e.user_id')
            ->orderBy('e.created_at', 'DESC');

        if ($status = $this->request->getGet('status')) {
            $builder->where('e.status', $status);
        } else {
            $builder->where('e.status !=', 'draft');
        }
        if ($recipient = $this->request->getGet('recipient')) {
            $builder->like('r.email', $recipient);
        }
        if ($date = $this->request->getGet('date')) {
            $builder->where('DATE(e.created_at)', $date);
        }

        $emails = $builder->paginate(20, 'emails');

        return view('emails/index', [
            'title'  => 'Email History',
            'emails' => $emails,
            'pager'  => $builder,
            'status' => $status ?? '',
        ]);
    }

    public function show($id)
    {
        $db = db_connect();
        $email = $db->table('emails e')
            ->select('e.*, r.name as recipient_name, r.email as recipient_email, u.name as user_name')
            ->join('recipients r', 'r.id = e.recipient_id')
            ->join('users u', 'u.id = e.user_id')
            ->where('e.id', $id)
            ->get()->getRowArray();

        if (! $email) {
            return redirect()->to('/emails')->with('error', 'Email not found.');
        }

        return view('emails/detail', ['title' => 'Email Detail', 'email' => $email]);
    }

    public function retry($id)
    {
        $db = db_connect();
        $email = $db->table('emails')->where('id', $id)->get()->getRowArray();

        if (! $email || $email['status'] !== 'failed') {
            session()->setFlashdata('error', 'Only failed emails can be retried.');
            return redirect()->to('/emails');
        }

        $result = (new EmailSenderService())->send(
            $email['recipient_id'], $email['subject'], $email['body_html'], $email['template_id'], (int) session()->get('user_id')
        );

        $db->table('emails')->where('id', $id)->delete();
        $db->table('emails')->where('id', $result['email_id'])->update(['attempt_count' => $email['attempt_count'] + 1]);

        \App\Services\ActivityLogger::log(session()->get('user_id'), 'email.retried', "Retried email #{$id}, result: {$result['status']}");

        session()->setFlashdata('success', $result['status'] === 'sent' ? 'Email resent successfully.' : 'Retry failed: ' . $result['error']);
        return redirect()->to('/emails');
    }
}
```

- [ ] **Step 4: Implement views**

```php
<?php
// app/Views/emails/index.php
?>
<?= $this->extend('layout/main') ?>
<?= $this->section('content') ?>
<form method="get" class="d-flex gap-2 mb-3">
    <select name="status" class="form-select" style="max-width:160px;" onchange="this.form.submit()">
        <option value="">All statuses</option>
        <option value="sent" <?= $status === 'sent' ? 'selected' : '' ?>>Sent</option>
        <option value="failed" <?= $status === 'failed' ? 'selected' : '' ?>>Failed</option>
        <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>Pending</option>
        <option value="draft" <?= $status === 'draft' ? 'selected' : '' ?>>Draft</option>
    </select>
    <input type="text" name="recipient" class="form-control" placeholder="Filter by recipient email" style="max-width:260px;">
    <input type="date" name="date" class="form-control" style="max-width:180px;">
    <button class="btn btn-outline-secondary">Filter</button>
</form>
<?php if (session()->getFlashdata('success')) : ?><div class="alert alert-success"><?= esc(session()->getFlashdata('success')) ?></div><?php endif ?>
<?php if (session()->getFlashdata('error')) : ?><div class="alert alert-danger"><?= esc(session()->getFlashdata('error')) ?></div><?php endif ?>
<div class="card border-0 shadow-sm">
<?php if (empty($emails)) : ?>
    <div class="card-body text-center text-muted py-5">No emails have been sent.</div>
<?php else : ?>
    <table class="table table-hover mb-0">
        <thead><tr><th>Recipient</th><th>Subject</th><th>Status</th><th>Sent At</th><th>User</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($emails as $e) : ?>
            <?php $badge = ['sent' => 'text-bg-success', 'failed' => 'text-bg-danger', 'pending' => 'text-bg-warning', 'draft' => 'text-bg-secondary'][$e['status']]; ?>
            <tr>
                <td><?= esc($e['recipient_name']) ?> <span class="text-muted">(<?= esc($e['recipient_email']) ?>)</span></td>
                <td><?= esc($e['subject']) ?></td>
                <td><span class="badge <?= $badge ?>"><?= esc($e['status']) ?></span></td>
                <td><?= esc($e['sent_at'] ?? '—') ?></td>
                <td><?= esc($e['user_name']) ?></td>
                <td class="text-end">
                    <a href="/emails/<?= (int) $e['id'] ?>" class="btn btn-sm btn-outline-secondary">View</a>
                    <?php if ($e['status'] === 'failed') : ?>
                        <form method="post" action="/emails/retry/<?= (int) $e['id'] ?>" class="d-inline"><?= csrf_field() ?><button class="btn btn-sm btn-outline-warning">Retry</button></form>
                    <?php endif ?>
                </td>
            </tr>
        <?php endforeach ?>
        </tbody>
    </table>
<?php endif ?>
</div>
<?= $this->endSection() ?>
```

```php
<?php
// app/Views/emails/detail.php
?>
<?= $this->extend('layout/main') ?>
<?= $this->section('content') ?>
<?php $badge = ['sent' => 'text-bg-success', 'failed' => 'text-bg-danger', 'pending' => 'text-bg-warning', 'draft' => 'text-bg-secondary'][$email['status']]; ?>
<div class="card border-0 shadow-sm">
    <div class="card-body">
        <span class="badge <?= $badge ?> mb-3"><?= esc($email['status']) ?></span>
        <dl class="row">
            <dt class="col-sm-3">Recipient</dt><dd class="col-sm-9"><?= esc($email['recipient_name']) ?> (<?= esc($email['recipient_email']) ?>)</dd>
            <dt class="col-sm-3">Subject</dt><dd class="col-sm-9"><?= esc($email['subject']) ?></dd>
            <dt class="col-sm-3">Sent At</dt><dd class="col-sm-9"><?= esc($email['sent_at'] ?? '—') ?></dd>
            <dt class="col-sm-3">Sent By</dt><dd class="col-sm-9"><?= esc($email['user_name']) ?></dd>
            <dt class="col-sm-3">Attempts</dt><dd class="col-sm-9"><?= (int) $email['attempt_count'] ?></dd>
            <?php if ($email['error_message']) : ?>
                <dt class="col-sm-3">Error</dt><dd class="col-sm-9 text-danger"><?= esc($email['error_message']) ?></dd>
            <?php endif ?>
        </dl>
        <h6>Message Preview</h6>
        <div class="border rounded p-3 bg-white"><?= $email['body_html'] ?></div>
    </div>
</div>
<?= $this->endSection() ?>
```

- [ ] **Step 5: Add routes**

```php
$routes->get('emails', 'EmailController::index');
$routes->get('emails/(:num)', 'EmailController::show/$1');
$routes->post('emails/retry/(:num)', 'EmailController::retry/$1');
```

- [ ] **Step 6: Run test to verify it passes**

Run: `php spark test tests/controllers/EmailControllerTest.php`
Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add app/Controllers/EmailController.php app/Views/emails app/Config/Routes.php tests/controllers/EmailControllerTest.php
git commit -m "feat: add email history, detail view, and retry-failed flow"
```

---

### Task 14: Settings page (profile + password change)

**Files:**
- Create: `app/Controllers/SettingsController.php`
- Create: `app/Views/settings/index.php`
- Modify: `app/Config/Routes.php`
- Test: `tests/controllers/SettingsControllerTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php
// tests/controllers/SettingsControllerTest.php
namespace Tests\Controllers;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

final class SettingsControllerTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $refresh = true;

    public function testChangePasswordRequiresCorrectCurrentPassword(): void
    {
        $this->db->table('users')->insert([
            'id' => 1, 'name' => 'Admin', 'email' => 'admin@test.com',
            'password_hash' => password_hash('OldPass123!', PASSWORD_DEFAULT), 'role' => 'owner', 'status' => 'active',
            'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $result = $this->withSession(['isLoggedIn' => true, 'user_id' => 1, 'user_role' => 'owner', 'user_name' => 'Admin'])
            ->post('/settings/password', [
                'current_password' => 'wrong', 'new_password' => 'NewPass123!', 'confirm_password' => 'NewPass123!',
            ]);

        $result->assertRedirect();
        $row = $this->db->table('users')->where('id', 1)->get()->getRowArray();
        $this->assertTrue(password_verify('OldPass123!', $row['password_hash']));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php spark test tests/controllers/SettingsControllerTest.php`
Expected: FAIL.

- [ ] **Step 3: Implement**

```php
<?php
// app/Controllers/SettingsController.php
namespace App\Controllers;

use App\Services\ActivityLogger;
use CodeIgniter\Controller;

class SettingsController extends Controller
{
    public function index()
    {
        $user = db_connect()->table('users')->where('id', session()->get('user_id'))->get()->getRowArray();
        unset($user['password_hash']);
        return view('settings/index', ['title' => 'Settings', 'user' => $user]);
    }

    public function updatePassword()
    {
        $rules = [
            'current_password' => 'required',
            'new_password'     => 'required|min_length[8]',
            'confirm_password' => 'required|matches[new_password]',
        ];

        if (! $this->validate($rules)) {
            return redirect()->to('/settings')->with('error', 'Please correct the errors below.');
        }

        $db = db_connect();
        $user = $db->table('users')->where('id', session()->get('user_id'))->get()->getRowArray();

        if (! password_verify($this->request->getPost('current_password'), $user['password_hash'])) {
            return redirect()->to('/settings')->with('error', 'Current password is incorrect.');
        }

        $db->table('users')->where('id', $user['id'])->update([
            'password_hash' => password_hash($this->request->getPost('new_password'), PASSWORD_DEFAULT),
            'updated_at'    => date('Y-m-d H:i:s'),
        ]);

        ActivityLogger::log($user['id'], 'user.password_changed', 'Password changed');
        return redirect()->to('/settings')->with('success', 'Password updated successfully.');
    }
}
```

```php
<?php
// app/Views/settings/index.php
?>
<?= $this->extend('layout/main') ?>
<?= $this->section('content') ?>
<?php if (session()->getFlashdata('success')) : ?><div class="alert alert-success"><?= esc(session()->getFlashdata('success')) ?></div><?php endif ?>
<?php if (session()->getFlashdata('error')) : ?><div class="alert alert-danger"><?= esc(session()->getFlashdata('error')) ?></div><?php endif ?>
<div class="card border-0 shadow-sm" style="max-width:500px;">
    <div class="card-body">
        <h6>Account</h6>
        <p class="text-muted"><?= esc($user['name']) ?> — <?= esc($user['email']) ?> — role: <?= esc($user['role']) ?></p>
        <hr>
        <h6>Change Password</h6>
        <form method="post" action="/settings/password">
            <?= csrf_field() ?>
            <div class="mb-3"><label class="form-label">Current Password</label><input type="password" name="current_password" class="form-control" required></div>
            <div class="mb-3"><label class="form-label">New Password</label><input type="password" name="new_password" class="form-control" required minlength="8"></div>
            <div class="mb-3"><label class="form-label">Confirm New Password</label><input type="password" name="confirm_password" class="form-control" required minlength="8"></div>
            <button type="submit" class="btn btn-primary">Update Password</button>
        </form>
    </div>
</div>
<?= $this->endSection() ?>
```

- [ ] **Step 4: Add routes**

```php
$routes->get('settings', 'SettingsController::index');
$routes->post('settings/password', 'SettingsController::updatePassword');
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php spark test tests/controllers/SettingsControllerTest.php`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add app/Controllers/SettingsController.php app/Views/settings app/Config/Routes.php tests/controllers/SettingsControllerTest.php
git commit -m "feat: add account settings with password change"
```

---

### Task 15: Full test suite, manual QA pass, and documentation

**Files:**
- Modify: `app/Config/App.php` (confirm `CI_ENVIRONMENT` guidance documented, not hardcoded)
- Create: `README.md` (overwrite the current 1-line stub)
- Create: `SECURITY_AUDIT.md`
- Modify: `.gitignore` (confirm `writable/uploads/*`, `.env` excluded)

- [ ] **Step 1: Run the full automated test suite**

Run: `php spark test`
Expected: all tests from Tasks 2–14 PASS.

- [ ] **Step 2: Manual QA pass**

Run: `php spark serve`. Walk through: login (valid/invalid/rate-limited after 6 attempts), dashboard KPIs, recipient CRUD + duplicate/invalid email + CSV import (valid/invalid/oversized/dup file) + export, template CRUD + preview + placeholder substitution, SMTP save (password never visible on reload) + test-connection against real or intentionally-wrong credentials, compose + send + confirm dialog + toast, email history filters + detail + retry, settings password change, logout, unauthorized route access while logged out, browser console clean of errors, responsive check at mobile width (375px) for sidebar/tables/forms.

- [ ] **Step 3: Write `README.md`**

Cover: requirements (PHP 8.2+, MySQL, Composer), install (`composer install`), DB setup (`CREATE DATABASE`, `.env` config, `php spark migrate`, `php spark db:seed AdminUserSeeder`), running locally (`php spark serve`), SMTP setup notes — Gmail requires an App Password (2FA account) or OAuth2, never the raw account password; Microsoft 365 uses `smtp.office365.com` port 587 TLS with an app password if MFA is enabled; generic SMTP just needs host/port/encryption/credentials — and production notes (set `CI_ENVIRONMENT=production`, serve over HTTPS so `Secure` cookies activate, rotate `encryption.key` only with a migration plan since it invalidates existing encrypted SMTP passwords, back up the MySQL database regularly, put `.env` secrets in your host's secret manager rather than committing them).

- [ ] **Step 4: Write `SECURITY_AUDIT.md`**

Document, per section 19 of the original brief: controls implemented (list each control from the design spec's Section 5), checks performed (the manual QA pass from Step 2, plus which automated tests cover which OWASP-adjacent concern), known limitations (single-server session storage, no 2FA, no automated dependency scanning configured), items requiring external penetration testing, and an explicit statement: **this document records an internal development-time review, not a certified VAPT — engage a qualified external security assessor before any production deployment handling real recipient data.**

- [ ] **Step 5: Final secret-exposure check**

Run: `git status` and `git diff --stat` to confirm `.env` was never staged; run `grep -r "Root@123456" --include="*.php" --include="*.md" .` (expected: no matches) to confirm the real DB password never leaked into a committed file.

- [ ] **Step 6: Commit**

```bash
git add README.md SECURITY_AUDIT.md .gitignore
git commit -m "docs: add README with SMTP setup notes and internal security audit"
```
