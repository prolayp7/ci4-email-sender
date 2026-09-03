# MASTER PROMPT — CodeIgniter 4 + MySQL Email Management Application

You are acting as a **Senior CodeIgniter 4 Developer, Full-Stack Engineer, Application Security Engineer, and UI/UX Engineer**.

Build a complete, secure, modern **email management and one-by-one email sending application** using the latest stable CodeIgniter 4 release and MySQL.

The application will primarily run on **localhost**, but the architecture must be suitable for deployment to a production server later.

The target delivery time is **1–2 days**, so prioritize the essential functionality and avoid unnecessary complexity.

---

# 1. FIRST — INSPECT THE ENVIRONMENT

Before writing code:

1. Inspect the existing project/repository.
2. Check PHP version.
3. Check Composer configuration.
4. Check existing CodeIgniter version.
5. Check MySQL configuration.
6. Inspect existing routes, controllers, models and views.
7. Identify reusable components.
8. Check `.env` configuration.
9. Check whether authentication already exists.
10. Do not destroy existing working functionality unnecessarily.

If the project is empty, initialize a clean CodeIgniter 4 application.

Use the **latest stable CodeIgniter 4 version available in the environment**.

Do NOT use deprecated CodeIgniter functionality.

Use PHP 8.2+ where supported by the selected CodeIgniter release.

---

# 2. CORE OBJECTIVE

Build a web-based application where an authenticated administrator can:

* Manage recipients
* Import recipients
* Create email templates
* Compose emails
* Configure SMTP
* Send emails individually
* Track sent/failed emails
* View email history
* Retry failed emails
* Search/filter records
* Manage basic application settings

The application must support:

* Gmail SMTP
* Google Workspace SMTP
* Microsoft 365 SMTP
* Generic/custom SMTP

Do NOT store SMTP passwords or credentials in source code.

---

# 3. TECHNOLOGY STACK

Use:

Backend:

* Latest stable CodeIgniter 4
* PHP 8.2+
* MySQL

Frontend:

* CodeIgniter 4 Views
* Bootstrap 5 or Tailwind CSS
* Vanilla JavaScript where practical
* AJAX/fetch for dynamic interactions where useful
* Lucide or another consistent icon system

Email:

* CodeIgniter Email Library
* SMTP

Database:

* MySQL
* Proper migrations
* Proper indexes
* Foreign keys where appropriate

Do NOT introduce unnecessary frameworks.

Keep the application lightweight.

---

# 4. UI/UX REQUIREMENT

The dashboard must NOT look like a generic old-fashioned CodeIgniter/Bootstrap admin panel.

The UI must be:

* Modern
* Premium
* Professional
* Clean
* Visually impressive
* Responsive
* Fast
* Accessible
* Consistent

Think of the design quality of modern SaaS applications.

Use:

* Clean typography
* Excellent spacing
* Subtle borders
* Professional cards
* Modern tables
* Good empty states
* Clear status badges
* Consistent iconography
* Responsive layouts
* Subtle hover states
* Toast notifications
* Confirmation dialogs

Avoid:

* Excessive gradients
* Excessive shadows
* Giant cards
* Clutter
* Random colors
* Old-style admin templates
* Excessive rounded elements
* Unnecessary animations

Use color semantically.

Example:

SUCCESS → green
FAILED → red
PENDING → amber
ACTIVE → appropriate accent

---

# 5. APPLICATION LAYOUT

Create:

## Sidebar

* Dashboard
* Recipients
* Email Templates
* Compose Email
* Email History
* SMTP Settings
* Settings

Bottom:

* Help
* User Profile
* Logout

## Header

Include:

* Page title
* Breadcrumb
* Search where appropriate
* Notifications
* User profile dropdown

Sidebar must support responsive mobile behavior.

---

# 6. DASHBOARD

Create a visually polished dashboard.

Display KPI cards:

* Total Recipients
* Emails Sent
* Emails Failed
* Emails Pending
* Success Rate

Include a recent activity section.

Example:

✓ Email sent to [customer@example.com](mailto:customer@example.com)
✕ Email failed for [user@example.com](mailto:user@example.com)
✓ Template created
✓ SMTP configuration updated

Include a simple email activity chart if time permits.

Also include:

### Quick Actions

* Add Recipient
* Import Recipients
* Compose Email
* Create Template
* SMTP Settings

Dashboard should immediately communicate application status.

---

# 7. RECIPIENT MANAGEMENT

Create recipient CRUD.

Fields:

* Name
* Email
* Company
* Phone (optional)
* Status
* Notes
* Created date
* Updated date

Features:

* Add recipient
* Edit recipient
* Delete recipient
* Search
* Filter
* Pagination
* Bulk selection
* CSV import
* Export CSV if practical

Validate:

* Name
* Email format
* Duplicate email
* Maximum field lengths

Do not allow malicious HTML/script injection.

---

# 8. CSV IMPORT

Allow CSV upload.

Expected columns:

Name
Email
Company
Phone

Requirements:

* File type validation
* MIME validation
* File size restriction
* Row validation
* Duplicate detection
* Invalid email reporting
* Import summary

Example:

Imported: 95
Skipped: 4
Invalid: 2
Duplicates: 2

Never trust client-side validation alone.

Perform validation server-side.

---

# 9. EMAIL TEMPLATE MANAGEMENT

Create template CRUD.

Fields:

* Template name
* Subject
* HTML body
* Plain text body
* Status

Features:

* Create
* Edit
* Preview
* Delete
* Duplicate template

Support placeholders:

{{name}}
{{email}}
{{company}}

Ensure placeholder replacement is safely handled.

Do not execute arbitrary template code.

---

# 10. COMPOSE EMAIL

Create a premium email composition interface.

Fields:

Recipient:

* Select recipient

Template:

* Select existing template

Subject:

* Editable

Message:

* HTML editor if practical
* Plain text fallback

Preview:

* Desktop email preview

Actions:

* Send Email
* Save Draft
* Clear

Before sending:

Show confirmation:

"Send this email to [customer@example.com](mailto:customer@example.com)?"

After sending:

Display success/failure result.

---

# 11. ONE-BY-ONE SENDING

This is a core requirement.

The application must send emails individually.

Example workflow:

Recipient 1
→ Send
→ Record result
→ Recipient 2
→ Send
→ Record result

Do NOT automatically blast all recipients.

Each send operation must:

1. Validate recipient
2. Validate email content
3. Load SMTP configuration
4. Establish SMTP connection
5. Send email
6. Record result
7. Return clear success/failure status

Database should record:

* Recipient
* Subject
* Template
* Status
* Error message
* Sent timestamp
* User
* Message identifier if available

---

# 12. EMAIL HISTORY

Create a professional email history table.

Columns:

* Recipient
* Subject
* Status
* Sent At
* Template
* User
* Actions

Statuses:

* Sent
* Failed
* Pending

Filters:

* Status
* Date
* Recipient
* Template

Actions:

* View details
* Retry failed
* View error

---

# 13. EMAIL DETAIL

Create an email detail page/drawer.

Show:

Recipient information

Email subject

Template

Message preview

Status

Timestamp

SMTP response/error

User who sent it

Attempt count

This should be useful for troubleshooting.

---

# 14. SMTP SETTINGS

Create a secure SMTP configuration page.

Fields:

* SMTP Host
* SMTP Port
* Encryption:

  * TLS
  * SSL
* Username
* Password
* From Email
* From Name

Presets:

### Gmail

smtp.gmail.com

### Microsoft 365

smtp.office365.com

### Custom SMTP

Manual configuration

Add:

### Test SMTP Connection

The administrator can send a test email to a configured address.

Never expose SMTP credentials in:

* HTML
* JavaScript
* API responses
* logs
* browser network responses

Passwords must never be displayed after saving.

---

# 15. SMTP SECURITY

Use environment variables wherever appropriate.

For example:

SMTP_HOST
SMTP_PORT
SMTP_USER
SMTP_PASS

If database storage of SMTP settings is required:

* Encrypt sensitive credentials at rest.
* Never log passwords.
* Never return passwords through API responses.
* Mask password fields.
* Use secure server-side decryption only when required.

Document the recommended production secret-management approach.

---

# 16. AUTHENTICATION

Create secure administrator authentication if authentication does not already exist.

Requirements:

* Login
* Logout
* Password hashing
* Session management
* Session regeneration
* Login validation
* Brute-force protection/rate limiting
* Secure cookies
* Session expiration

Passwords MUST use secure password hashing such as PHP's password_hash/password_verify.

Never store plaintext passwords.

---

# 17. AUTHORIZATION

Implement authorization even if V1 has only one administrator.

Prepare the architecture for roles.

Example:

Owner
Admin
Operator
Viewer

Ensure protected routes cannot be accessed without authorization.

---

# 18. SECURITY / VAPT REQUIREMENTS

Security is a HIGH PRIORITY.

Implement protection against common OWASP risks.

At minimum address:

### Authentication

* Secure password hashing
* Session fixation prevention
* Session regeneration
* Rate limiting
* Secure logout

### Authorization

* Server-side authorization checks
* No reliance on hidden UI controls

### SQL Injection

Use CodeIgniter Query Builder / prepared statements.

Do NOT concatenate untrusted SQL.

### XSS

Escape all user-generated output.

Use appropriate HTML sanitization where rich HTML is intentionally allowed.

### CSRF

Enable and use CodeIgniter CSRF protection for state-changing requests.

### File Upload

For CSV/PDF/attachment uploads:

* Validate extension
* Validate MIME type
* Restrict file size
* Generate safe filenames
* Never execute uploaded files
* Store outside executable directories where possible

### Input Validation

Validate every server-side input.

### Mass Assignment

Explicitly control accepted fields.

### Security Headers

Implement where appropriate:

* Content-Security-Policy
* X-Content-Type-Options
* X-Frame-Options / frame-ancestors
* Referrer-Policy
* Permissions-Policy

### Cookies

Use:

HttpOnly
Secure in HTTPS production
SameSite

### Error Handling

Production errors must NOT reveal:

* SQL queries
* file paths
* credentials
* stack traces
* internal configuration

### Logging

Log security-relevant events without sensitive secrets.

---

# 19. VAPT READINESS

The application must be developed with VAPT in mind.

Perform an internal security review before completion.

Check:

* Authentication
* Authorization
* SQL injection
* XSS
* CSRF
* File upload
* Session security
* Access control
* Input validation
* Information disclosure
* Security headers
* Sensitive data exposure
* Rate limiting
* Error handling

Where tools are available, perform appropriate static/security checks.

Create:

`SECURITY_AUDIT.md`

Include:

* Security controls implemented
* Security checks performed
* Known limitations
* Items requiring external penetration testing
* Production hardening recommendations

IMPORTANT:

Do NOT falsely claim that the application has passed an independent VAPT assessment.

State clearly that actual VAPT certification requires testing by a qualified security assessor.

---

# 20. DATABASE DESIGN

Create proper migrations.

Suggested tables:

users
recipients
email_templates
emails
smtp_settings
activity_logs

Use:

* Primary keys
* Foreign keys
* Indexes
* Timestamps
* Appropriate data types

Index:

recipients.email
emails.status
emails.sent_at
emails.recipient_id

Avoid unnecessary database complexity.

---

# 21. ACTIVITY LOG

Record important actions:

* Login
* Logout
* Recipient created
* Recipient updated
* Recipient deleted
* CSV imported
* Template created
* Template updated
* Email sent
* Email failed
* SMTP configuration changed

Never log passwords or SMTP credentials.

---

# 22. ERROR HANDLING

Every operation must fail gracefully.

Examples:

SMTP unavailable

Display:

"Unable to connect to the SMTP server. Please check your SMTP configuration."

Do not expose:

* SMTP server internals
* PHP stack traces
* credentials
* database errors

Log technical details securely on the server.

---

# 23. EMAIL PROVIDER COMPATIBILITY

The architecture must not be hard-coded exclusively for Gmail.

Create an SMTP abstraction/configuration layer.

Support:

Gmail
Google Workspace
Microsoft 365
Generic SMTP

Future providers should be addable without rewriting the email management system.

---

# 24. RESPONSIVE DESIGN

Support:

Desktop
Laptop
Tablet
Mobile

The dashboard should remain usable on small screens.

Tables:

* Responsive scrolling
  OR
* Mobile card layout where appropriate.

Forms:

* Full-width on mobile
* Proper spacing
* Clear validation messages

---

# 25. PERFORMANCE

Because the target is a lightweight localhost application:

* Avoid unnecessary dependencies
* Avoid excessive JavaScript
* Avoid huge UI libraries
* Use efficient queries
* Add database indexes
* Paginate large datasets
* Avoid N+1 queries
* Keep frontend assets optimized

---

# 26. ROUTING

Use clean CodeIgniter routes.

Example:

/login
/dashboard

/recipients
/recipients/create
/recipients/edit/{id}

/templates
/templates/create
/templates/edit/{id}

/compose

/emails
/emails/{id}

/smtp
/settings

Do not expose internal implementation details through URLs.

---

# 27. CODE QUALITY

Follow CodeIgniter 4 conventions.

Use:

* Controllers
* Models
* Services
* Filters
* Validation
* Migrations
* Entities where beneficial
* Config classes
* Reusable views/components

Keep business logic out of views.

Do not create giant controllers.

Email sending logic should live in a dedicated service.

Example conceptual structure:

app/
Controllers/
Models/
Services/
Filters/
Entities/
Views/
Database/
Config/

---

# 28. ENVIRONMENT CONFIGURATION

Create a proper `.env` configuration.

Never commit:

* SMTP passwords
* application secrets
* encryption keys
* database passwords

Ensure `.env` is ignored by Git.

Provide:

`.env.example`

with safe placeholders.

---

# 29. TESTING

Before completion test:

### Authentication

* Login
* Invalid login
* Logout
* Unauthorized access

### Recipients

* Create
* Update
* Delete
* Duplicate email
* Invalid email

### CSV

* Valid CSV
* Invalid CSV
* Oversized CSV
* Duplicate records

### Templates

* Create
* Update
* Delete
* Preview
* Placeholder replacement

### SMTP

* Valid SMTP
* Invalid SMTP
* Connection failure
* Authentication failure

### Email

* Successful send
* Failed send
* Retry failed email
* Email history

### Security

* CSRF
* XSS
* SQL injection
* Unauthorized route access
* Session behavior
* File upload validation

---

# 30. UI STATES

Every major page should have:

* Loading state
* Empty state
* Error state
* Success state

Examples:

"No recipients yet. Add your first recipient."

"No emails have been sent."

"No failed emails."

"SMTP configuration is incomplete."

Make these states visually polished.

---

# 31. TOASTS

Use professional toast notifications.

Examples:

"Recipient added successfully."

"Template updated successfully."

"Email sent successfully."

"Email delivery failed."

"SMTP configuration saved."

---

# 32. CONFIRMATION DIALOGS

Use confirmation dialogs for destructive actions.

Example:

Delete recipient?

"This action cannot be undone."

Buttons:

Cancel
Delete

---

# 33. SECURITY-FIRST DEVELOPMENT PROCESS

Before each major feature ask:

1. What input does this feature accept?
2. Can the user manipulate it?
3. Is server-side validation present?
4. Is authorization enforced?
5. Can it expose sensitive information?
6. Can it introduce XSS?
7. Can it introduce SQL injection?
8. Does it require CSRF protection?
9. Is it logged safely?

Do not treat frontend validation as security.

---

# 34. DEVELOPMENT PRIORITY

Because the project should be completed within **1–2 days**, use this priority:

## DAY 1

1. Project/environment inspection
2. Database architecture
3. Authentication
4. SaaS-style dashboard shell
5. Recipient management
6. SMTP configuration
7. Email template management
8. Compose email
9. Individual email sending
10. Email history

## DAY 2

1. Security hardening
2. CSV import
3. Retry failed emails
4. Activity logs
5. UI polish
6. Responsive testing
7. Security audit
8. Bug fixing
9. Documentation
10. Final verification

If time becomes constrained, prioritize:

**Security → Email Sending → SMTP → Recipients → History → UI polish → Secondary features**

---

# 35. DOCUMENTATION

Create:

README.md

Include:

* Requirements
* Installation
* Composer installation
* Database setup
* Migration commands
* `.env` configuration
* SMTP setup
* Gmail configuration
* Microsoft 365 configuration
* Localhost setup
* Running the application
* Production deployment notes

Also create:

SECURITY_AUDIT.md

And:

.env.example

---

# 36. GMAIL NOTES

Do not assume a normal Gmail account password can simply be placed into the application.

Document supported secure authentication approaches.

For applicable Gmail/Google Workspace configurations, use:

* OAuth where appropriate
  OR
* App Password where supported

Do not weaken security simply to make localhost testing easier.

---

# 37. PRODUCTION READINESS

Although V1 is localhost-focused, make it reasonably production-ready.

Include:

* Environment configuration
* Secure secrets
* Production error mode
* HTTPS recommendations
* Secure cookies
* Security headers
* Database backups recommendation
* Logging
* SMTP credential protection

---

# 38. FINAL QA

Before declaring completion:

1. Run the application.
2. Test every route.
3. Test authentication.
4. Test recipient CRUD.
5. Test CSV import.
6. Test templates.
7. Test SMTP configuration.
8. Send a real test email if credentials/environment are available.
9. Test failed email handling.
10. Test retry.
11. Test responsive layouts.
12. Inspect browser console.
13. Check server logs.
14. Perform security review.
15. Fix all obvious errors.
16. Remove debug code.
17. Remove test credentials.
18. Verify `.env` is not committed.
19. Verify no secrets appear in frontend/network responses.
20. Verify no sensitive information appears in logs.

---

# 39. IMPORTANT SCOPE RULE

Do NOT over-engineer this project.

This is a V1 application intended to be completed within 1–2 days.

Do NOT introduce:

* Microservices
* Redis unless genuinely required
* Queues unless required by the final sending workflow
* Kubernetes
* Complex event-driven architecture
* Unnecessary APIs
* Unnecessary frontend frameworks
* Complex permission systems

Build a clean monolithic CodeIgniter 4 application with a clear service layer.

---

# 40. FINAL QUALITY STANDARD

The final application should feel like:

**A modern lightweight SaaS email management product**

—not—

**a basic CodeIgniter CRUD application.**

The most important qualities are:

1. Security
2. Reliable individual email sending
3. SMTP flexibility
4. Excellent UI
5. Clean architecture
6. Easy local installation
7. VAPT readiness
8. Maintainability
9. Use all the installed skills and plugins which are absolutekly required for the application like impaccable,taste skill etc.

Start by inspecting the repository/environment and then implement the application.

Do not spend excessive time explaining what you are going to do.

**Build it. Test it. Secure it. Polish it. Document it.**
