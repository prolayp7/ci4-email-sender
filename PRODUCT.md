# Product

<!-- impeccable:product-schema 1 -->

## Platform

web

## Users

An internal administrator (and, as the team grows, operator/viewer teammates) at a small organization who needs to manage a recipient list and send individually-confirmed emails — not bulk marketing blasts — while keeping a full delivery audit trail for troubleshooting. Four roles are modeled (owner, admin, operator, viewer) with route-level enforcement already in place; only one owner account is seeded today.

## Product Purpose

A lightweight, self-hosted email management tool: import/manage recipients, author reusable templates with placeholders, configure SMTP (Gmail, Microsoft 365, or generic), and send emails one at a time with an explicit per-recipient confirmation step, recording every send attempt (sent/failed/pending, error detail, retry) for troubleshooting.

## Positioning

Unlike bulk email marketing SaaS platforms, this tool deliberately never auto-blasts a list — every send is a single, confirmed, individually-tracked action. It is self-hosted (localhost-first, production-capable), stores SMTP credentials encrypted at rest, and treats auditability (who sent what, when, and the exact failure reason) as a first-class feature rather than an afterthought.

## Operating Context

Runs on localhost during development (CodeIgniter 4 dev server + local MySQL) with a documented path to production deployment. Core workflows:
- Import recipients via CSV (server-validated: type/size/dedup/invalid-row reporting) or add/edit/delete individually, with bulk-delete and CSV export.
- Author HTML/plain-text templates with `{{name}}` / `{{email}}` / `{{company}}` placeholders (safe string substitution, never executed as code); preview, duplicate, delete.
- Configure SMTP via presets (Gmail, Microsoft 365) or custom host/port/encryption; test-connection before relying on it.
- Compose an email against a recipient + optional template, preview, confirm, send one at a time, or save as a draft.
- Review email history with status filters, per-email detail (error message, attempt count, timestamps), and retry-failed.
- Dashboard surfaces KPIs (recipient count, sent/failed/pending, success rate) and a recent-activity feed.

## Capabilities and Constraints

- Stack: CodeIgniter 4 (PHP 8.5) + MySQL, Bootstrap 5 via CDN, vanilla JS/fetch, Quill (CDN) for HTML editing, Lucide (CDN) icons — no Node build step, no SPA framework, no queues.
- Security already enforced: CSRF, per-page CSP (with a stricter script-blocking CSP on the template-preview page), security headers, encrypted-at-rest SMTP credentials (never returned in any response), rate-limited login, role-gated routes (owner/admin/operator/viewer split across read/write/SMTP-admin tiers).
- Visual design today is Bootstrap's own defaults with no established identity — this is the known gap the current design work addresses, matching a shared reference theme (external demo at html.aqlova.com/conca-demo).
- Responsive requirement: sidebar collapses to an off-canvas panel on mobile; tables/forms must remain usable at mobile widths.

## Brand Commitments

None fixed yet. Internal working name so far is "Email Manager" — a placeholder, not a confirmed brand name. No logo or brand asset library exists.

## Evidence on Hand

- Full functional and security specification: `PROMPT.md.md` at the repo root (40-section brief covering every feature, security control, and UI-state requirement already implemented).
- Working implementation: `app/Controllers`, `app/Views`, `app/Services`, `app/Models`, `app/Database/Migrations` — auth, recipients, templates, SMTP settings, compose, email history all built and tested (PHPUnit feature/unit tests under `tests/`).
- No real customer/testimonial/case-study content exists; none should be fabricated.

## Product Principles

1. One recipient, one confirmed send, every time — never an automatic bulk blast.
2. Security and auditability are features, not afterthoughts: encrypted credentials, activity log, role-gated access, legible error messages.
3. Stay lightweight and self-hostable — no dependency or infrastructure the stated scope doesn't need.
4. Operational status must be legible at a glance (dashboard KPIs, status badges, clear empty/error states) since this is a tool people rely on to know whether their emails actually went out.

## Accessibility & Inclusion

No product-specific requirement established beyond standard semantic HTML and keyboard operability; treat as a baseline expectation for an internal admin tool rather than a documented mandate.
