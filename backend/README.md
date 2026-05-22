# CobbleCart Backend

Structured PHP + Oracle backend for the CobbleCart marketplace, organized by
responsibility (config / core / middleware / models / controllers / routes).

## How this relates to api/

This folder is **a parallel re-architected layer**, not a replacement.
The live front-end (`frontend/customer/`, `frontend/trader/`, `frontend/admin/`,
`frontend/js/main.js`) still calls the flat scripts in [../api/](../api/) —
e.g. `../../api/register.php`.
Those files remain the source of truth for endpoints the UI hits today.

Use this `backend/` folder for **new endpoints** and any work that benefits
from the layered model/controller/route separation. When you're ready to
migrate the UI, the route map in [routes/web.php](routes/web.php) is the
target shape (`/api/login.php` → `/backend/auth/login`, etc.). See
[../api/README.md](../api/README.md) for which front-end files still depend
on which legacy endpoint.

## Folder layout

```
backend/
├── config/                # Database + app settings
│   ├── app.php            # roles, password policy, CORS, order states
│   └── database.php       # Oracle connection (overridable via env vars)
├── core/                  # Framework-style helpers
│   ├── bootstrap.php      # Session, headers, CORS, loads everything below
│   ├── db.php             # OCI8 wrappers (db_execute, fetch, sequences, commit)
│   ├── request.php        # JSON parsing, validation, method checks
│   ├── response.php       # respond_ok / respond_error / respond_not_found
│   └── auth.php           # session helpers + password_hash_strong
├── middleware/
│   └── auth.php           # require_login, require_role, require_own_shop
├── models/                # Data access; one class per domain entity
│   ├── User.php           # B1, B2, B3, C1, C2, D1, D2
│   ├── Product.php        # A1, A2, B4, C3
│   ├── Shop.php           # A1-07, A2-04, C1, C2, D1
│   ├── Cart.php           # A3, B5
│   ├── Wishlist.php       # A3-02, B6
│   ├── Order.php          # B7, B9, C4-01, D4-01
│   ├── Review.php         # A4, B4-05, B8
│   └── Report.php         # C4, D3, D4
├── controllers/           # HTTP-facing logic, organized by role
│   ├── AuthController.php
│   ├── public/            # No auth required
│   ├── customer/          # require_customer()
│   ├── trader/            # require_trader()
│   └── admin/             # require_admin()
├── routes/
│   └── web.php            # Single source of truth for the route table
├── public/
│   ├── index.php          # Front controller / router
│   └── .htaccess          # Apache rewrite -> index.php
└── storage/
    ├── logs/              # Reserved for log output
    └── sessions/          # Reserved if you move sessions off the default path
```

## Running it

1. **Database** — make sure the Oracle schema in
   [database/oracle-complete-schema.sql](../database/oracle-complete-schema.sql) has
   been applied to `XEPDB1` and that the `COBBLECART` account exists.

2. **Configuration** — set the connection (env vars override the defaults in
   [config/database.php](config/database.php)):

   ```powershell
   $env:COBBLECART_DB_USER     = "COBBLECART"
   $env:COBBLECART_DB_PASSWORD = "<your password>"
   $env:COBBLECART_DB_DSN      = "localhost/FREEPDB1"
   ```

3. **Serve** — point PHP's built-in server at `backend/public`:

   ```powershell
   php -S localhost:8001 -t backend/public
   ```

   Apache / nginx users: mount `backend/public` as the document root, or set up
   a rewrite that sends `/backend/*` to `backend/public/index.php`.

## Routing convention

The router strips a leading `/backend` (or `/backend/public`) from the URL and
matches what remains against the table in [routes/web.php](routes/web.php).
`{id}` placeholders are forwarded to the controller method as integers.

Examples (all return JSON):

| Method | Path                                       | Requirement |
|--------|--------------------------------------------|-------------|
| POST   | `/backend/auth/register-customer`          | B1          |
| POST   | `/backend/auth/login`                      | B2, C1, D1  |
| GET    | `/backend/products?search=apple&max_price=5` | A2-02, A2-03 |
| GET    | `/backend/products/42`                     | B4-02       |
| POST   | `/backend/customer/cart`                   | A3-01, B5-01 |
| POST   | `/backend/customer/orders`                 | B7-04       |
| GET    | `/backend/trader/reports/sales?period=monthly` | C4-02   |
| POST   | `/backend/admin/products/42/approve`       | C1-05       |

Full requirement coverage is in [REQUIREMENTS.md](REQUIREMENTS.md).

## Adding a new endpoint

1. Add (or extend) a method in the relevant model under `models/`.
2. Add a controller action under `controllers/<role>/`.
3. Register the route in [routes/web.php](routes/web.php).

No autoloader configuration needed — `public/index.php` already searches
`models/` and the role-specific `controllers/` folders.

## Non-functional alignment

| Req  | Where it's enforced |
|------|---------------------|
| E1, E5 | JSON-only API, no UI assumptions — front-end can ship any layout |
| E2 | Sequences + parametrized queries; per-request DB connection avoids lock contention |
| E6 | `PAYMENT.PAYMENT_METHOD` defaults to `PAYPAL`; expand `Order::placeFromCart` to call the PayPal SDK |
| E7 | `require_own_shop()` middleware + `WHERE SHOP_ID = :sid` on every trader query |
| E8 | `validate_password_or_fail()` — 8+ chars, letters and digits required |
| E9 | bcrypt cost 12; session cookie httponly + samesite=Lax |
