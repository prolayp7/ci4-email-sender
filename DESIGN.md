# Design

<!-- impeccable:design-schema 1 -->

## Source

Visual identity matched to a user-pinned reference: the Conca Bootstrap admin template (`https://html.aqlova.com/conca-demo/conca/index.html`). Tokens below were extracted directly from that theme's compiled CSS, not invented — this app reuses its palette, type, and component language on our own layout/content, not its markup or copy.

## Mode

Operate (admin panel / internal tool). Restrained-to-Committed color use: primary indigo marks the active nav item, primary buttons/links, and selection/state only — never decoration.

## Palette

- Primary (indigo): `#5F4AFE`
- Secondary: `#5A5965`
- Success: `#219653`
- Info: `#0BA5EC`
- Warning: `#F79009` (soft-badge text uses a darker `#ad6607` for contrast on the tint)
- Danger: `#d50100`
- Page background: `#F4F4F7`
- Surface (cards, sidebar, modals, offcanvas): `#FFFFFF`
- Body text: `#57575A` · Headings: `#191822` · Muted: `#939397` / `#716F7E`
- Border: `#E8E8E8`

Dark mode was explicitly deferred (not built).

## Typography

Inter (400/500/600/700), loaded via `@fontsource/inter` from jsdelivr (kept inside the existing CSP's allowed origins). One family for the whole UI, per Operate-mode convention — headings at weight 600, sidebar/nav links at 500.

## Components

- **Cards**: no border, 12px radius, two-layer soft shadow (`0 2px 2px -1px rgba(25,24,34,.1), 0 1px 3px 0 rgba(25,24,34,.08)`).
- **Soft "label" badges** (`.badge-soft-{primary,success,warning,danger,secondary,info}`): 16%-alpha tint background + solid-color text, pill radius. This replaces Bootstrap's solid `text-bg-*` badges everywhere in the app (recipient/template status, email history status, role indicators).
- **Sidebar**: 264px, white background, 6px-radius nav items; active item = solid primary background + white text + soft indigo-tinted shadow; hover = light gray (`#F6F6F9`) background.
- **Header**: white, separated from content by a soft shadow rather than a hard border line.
- **KPI tiles**: a 44px soft-tinted circular icon badge (`.kpi-icon` + a `badge-soft-*` color) above a label and large number — dashboard only.
- **Buttons/inputs**: Bootstrap's own shape system (6px radius) retinted to the new primary; not reinvented.

All of this lives in `public/assets/css/theme.css`, layered on stock Bootstrap 5.3.3 (CDN) purely via CSS custom-property overrides plus a handful of new utility classes — no forked/custom Bootstrap build.

## Known gaps

- Dark mode (deferred, not built).
- Compose page's Quill editor toolbar still uses Quill's own default chrome (not retinted).
