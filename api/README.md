# api/ — Legacy backend (live)

This folder is the **original PHP backend**. It is what the front-end
(`frontend/customer/*.html`, `frontend/trader/*.html`, `frontend/admin/*.html`,
and `frontend/js/main.js`) currently calls — references look like
`fetch('../../api/register.php', ...)`.

Each `.php` file in this directory is a standalone endpoint. The shared
helpers live in [config.php](config.php).

## Status

**Active.** Do not delete. The front-end will break if any of these files
move or rename:

| Caller                                     | Endpoint(s) it depends on                              |
|--------------------------------------------|--------------------------------------------------------|
| `frontend/customer/register.html`          | `register.php`, `verify-email.php`                     |
| `frontend/customer/verify.html`            | `register.php`, `verify-email.php` (XAMPP-relative)    |
| `frontend/trader/dashboard.html`           | `get-trader-report.php`, `get-products.php`, `current-user.php` |
| `frontend/trader/manage-products.html`     | `get-products.php`, `current-user.php`                 |
| `frontend/trader/add-product.html`         | `add-product.php`                                      |
| `frontend/admin/dashboard.html`            | `get-products.php`, `approve-product.php`              |

## Relationship to backend/

The newer [backend/](../backend/) folder is a parallel re-architected layer
(controllers / models / routes) covering the same domain. It is **not** a
drop-in replacement — the URL shape changed (`/api/login.php` → `/backend/auth/login`),
so the front-end would need to be updated to migrate.

Until that migration happens, treat `api/` as the source of truth for any
endpoint the front-end actually hits, and use `backend/` for new work that
benefits from the layered structure.

## Layout

```
api/
├── config.php              # OCI connection + shared helpers (oci_execute_stmt, etc.)
├── login.php / logout.php  # Session auth
├── register.php            # Customer + trader signup
├── verify-email.php        # Email confirmation flow
├── reset-password.php
├── change-password.php
├── update-profile.php
├── current-user.php
│
├── get-products.php        # Catalog browse (role-aware)
├── add-product.php
├── update-product.php
├── delete-product.php
├── approve-product.php     # Admin
│
├── add-to-cart.php
├── get-cart.php
├── place-order.php
├── get-orders.php
│
├── add-trader.php          # Admin
├── get-customers.php
├── get-trader-report.php
│
├── send-email.php          # Mailer wrapper used by registration + reset
├── mail-config.local.php   # Local-only; not in version control if .gitignored
│
├── oracle-network/         # Oracle wallet / TNS config (do not move)
├── pending-verifications/  # Tokens awaiting email confirmation
├── sessions/               # PHP session files
└── *.log                   # OCI + PHP error logs
```
