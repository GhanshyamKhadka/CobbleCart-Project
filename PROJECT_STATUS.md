# Project Status

Snapshot as of 2026-05-13.

## What works today

The frontend in `frontend/` is functional against the procedural `api/` backend.
Pages render, forms post, sessions are tracked via PHP's default file sessions
under `api/sessions/`.

| Area              | State    | Notes                                                  |
|-------------------|----------|--------------------------------------------------------|
| Landing page      | Working  | `frontend/index.html` — pure marketing                 |
| Customer browse   | Working  | `home.html`, `category.html`, `product-details.html`   |
| Customer cart     | Working  | `cart.html`, `checkout.html` (checkout uses ORDS)      |
| Customer auth     | Working  | `register.html` → `register.php` sends real OTP via Gmail SMTP, `verify.html` → `verify-email.php` inserts row into `Users` with `email_verified='YES'`, `login.html` (ORDS) |
| Customer profile  | Working  | `profile.html`, `invoice.html`                         |
| Trader dashboard  | Working  | `dashboard.html`, `add-product.html`, `manage-products.html`, `profile-settings.html` |
| Admin dashboard   | Working  | `dashboard.html` (pending products + approval), `add-trader.html`, `customer-management.html` |
| ORDS endpoints    | Working  | `login`, `logout`, `place-order` via `http://localhost:8080/ords/cobbleuser/` |

## In progress

### `backend/` MVC migration

The new MVC backend in `backend/` is scaffolded but not yet called by any
frontend page. Routes, controllers, and models exist; the frontend still hits
`api/*.php` for everything.

To finish the migration, every `fetch('../../api/X.php', ...)` in the frontend
needs to become `fetch('/backend/<route>', ...)`. The route map is in
`backend/routes/web.php` and the requirement-coverage matrix is in
`backend/REQUIREMENTS.md`.

### Email verification — wired up 2026-05-13

Flow: `register.php` generates a 6-digit OTP → stores it in
`api/pending-verifications/<sha256(email)>.json` with a 15-minute TTL and
60-second resend cooldown → sends it via Gmail SMTP through
`api/lib/SmtpMailer.php` → `verify-email.php` matches the code, INSERTs the row
into `Users` with `EMAIL_VERIFIED='YES'`, then deletes the pending file.

The old session-based + stubbed-email path was replaced. SMTP creds live in
`api/mail-config.local.php` (gitignored).

## Known TODOs / future work

Items marked `future` in `backend/REQUIREMENTS.md`:

- B1-03 — email verification on the new MVC backend (live api/ already does it)
- B2-03 — forgot-password flow
- B7-02 — discount calculation at checkout
- B7-09 — PDF invoice generation
- C3-04 — per-shop discount support
- D2-02 — admin edit-trader endpoint
- E6 — PayPal SDK integration (`PAYMENT.PAYMENT_METHOD` defaults to `PAYPAL`, but no SDK call yet)

## Required setup steps (one-time)

If you've already loaded the schema before 2026-05-13, you need two SQL steps
before registration will work:

```sql
-- As a privileged user (SYS / SYSTEM):
ALTER USER COBBLEUSER IDENTIFIED BY "Oracle#12345@" ACCOUNT UNLOCK;

-- As COBBLEUSER:
@database/migration-email-verified.sql
```

Also: drop your 16-character Gmail App Password into
`api/mail-config.local.php` (the example file has the schema). Without it,
`sendEmail` will return false and registration responds with "We could not
send the verification email."

## Cleanup history

2026-05-13 — flattened repo: removed an outer duplicate copy of the whole
project, deleted runtime junk (`*.log`, `phpinfo.php`, `test-*.php`, stale
sessions, empty `{css,js,...}` folder), moved UI files into `frontend/`,
renamed `schema/` → `database/`, added this status doc and a `.gitignore`.
Frontend fetch paths updated from `../api/` to `../../api/` to reflect the new
depth (11 occurrences across 6 HTML files).

2026-05-13 (later again) — switched PHP backend from `COBBLEUSER` to
`COBBLECART` schema so registrations show up in the same APEX workspace.
Updated `api/config.php`, `backend/config/database.php`, `database/setup-db.sql`,
and docs. Wired `frontend/customer/profile.html` to do real CRUD against
`api/update-profile.php` (rename + change email) and `api/change-password.php`
(verify-current + bcrypt-rehash) instead of the previous "Sarah Jones"
hardcoded mock. Tested end-to-end: register → verify → login → update profile
→ verified row in `COBBLECART.USERS` reflects the change.

2026-05-13 (later) — wired Oracle + email-OTP registration end-to-end:
synced `api/config.php` to `COBBLEUSER / Oracle#12345@`, added
`database/migration-email-verified.sql` to fix a trigger that referenced an
undefined `Users.email_verified` column, added `api/lib/SmtpMailer.php` (custom
SMTP client, no Composer needed), replaced the stubbed `send-email.php` with a
real implementation, and rewrote `register.php` + `verify-email.php` to use
file-based pending tokens (more reliable than `$_SESSION` under XAMPP). Removed
the unused `register-form.php` duplicate.
