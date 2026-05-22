# Requirement Coverage Map

Each requirement ID from the catalogue maps to the file(s) that implement it.
"scaffolded" = endpoint or model method exists; "ui-only" = needs front-end
only (no backend work required); "future" = not in this scaffold (Could-have).

## A. Product

| ID    | Description                                | Status     | File / route |
|-------|--------------------------------------------|------------|--------------|
| A1-01 | Clear product name                         | scaffolded | [models/Product.php](models/Product.php) — `NAME` column |
| A1-02 | Price displayed                            | scaffolded | [models/Product.php](models/Product.php) — `PRICE` column |
| A1-03 | Short description                          | scaffolded | [models/Product.php](models/Product.php) — `DESCRIPTION` |
| A1-04 | At least one image                         | scaffolded | [models/Product.php](models/Product.php) — `PRODUCT_IMAGE` |
| A1-05 | Grouped into categories                    | scaffolded | `GET /backend/categories` ([controllers/public/ProductController.php](controllers/public/ProductController.php)) |
| A1-06 | Stock status visible                       | scaffolded | `STOCK_QUANTITY` returned by every product endpoint |
| A1-07 | Shop name visible                          | scaffolded | `SHOP_NAME` joined in `Product::search` / `findById` |
| A2-01 | Browse by category                         | scaffolded | `GET /backend/products?product_type_id=N` |
| A2-02 | Search                                     | scaffolded | `GET /backend/products?search=...` |
| A2-03 | Filters (price, category)                  | scaffolded | `min_price` / `max_price` / `product_type_id` query params |
| A2-04 | View shop details                          | scaffolded | `GET /backend/shops/{id}` |
| A3-01 | Add to cart                                | scaffolded | `POST /backend/customer/cart` |
| A3-02 | Wishlist                                   | scaffolded | `POST /backend/customer/wishlist` |
| A3-04 | Remove from cart / wishlist                | scaffolded | `DELETE /backend/customer/cart/{id}`, `/wishlist/{id}` |
| A4-01 | Rate products                              | scaffolded | `POST /backend/customer/reviews` |
| A4-02 | Write reviews                              | scaffolded | Same endpoint, `comments` field |

## B. Customer

| ID    | Description                                | Status     | File / route |
|-------|--------------------------------------------|------------|--------------|
| B1-01 | Register with basic details                | scaffolded | `POST /backend/auth/register-customer` |
| B1-02 | Create password                            | scaffolded | `validate_password_or_fail` ([core/request.php](core/request.php)) |
| B1-03 | Email verification                         | future     | Hook into [api/send-email.php](../api/send-email.php); add `EMAIL_VERIFIED` column |
| B1-04 | Confirmation message                       | scaffolded | `respond_ok(..., 'Registration successful')` |
| B2-01 | Log in with email + password               | scaffolded | `POST /backend/auth/login` |
| B2-02 | Error on bad credentials                   | scaffolded | 401 from `AuthController::login` |
| B2-03 | Forgot password                            | future     | Reuse [api/reset-password.php](../api/reset-password.php) |
| B3-01 | View profile                               | scaffolded | `GET /backend/customer/profile` |
| B3-02 | Update profile                             | scaffolded | `PUT /backend/customer/profile` |
| B3-03 | Change password                            | scaffolded | `POST /backend/auth/change-password` |
| B4-01 | View all products                          | scaffolded | `GET /backend/products` |
| B4-02 | View product detail                        | scaffolded | `GET /backend/products/{id}` |
| B4-03 | Images visible                             | scaffolded | `PRODUCT_IMAGE` in payload |
| B4-04 | Price + description visible                | scaffolded | Same payload |
| B4-05 | Read reviews                               | scaffolded | `GET /backend/products/{id}/reviews` |
| B4-06 | Availability visible                       | scaffolded | `STOCK_QUANTITY` field |
| B5-01 | Add to cart                                | scaffolded | `POST /backend/customer/cart` |
| B5-02 | Change quantity                            | scaffolded | `PUT /backend/customer/cart/{id}` |
| B5-03 | Remove from cart                           | scaffolded | `DELETE /backend/customer/cart/{id}` |
| B5-04 | Total cost displayed                       | scaffolded | `Cart::getCart()` returns `total` |
| B6-01 | Wishlist                                   | scaffolded | `/backend/customer/wishlist` (GET/POST/DELETE) |
| B7-01 | Pick collection slot                       | scaffolded | `GET /backend/customer/slots`; `slot_id` in order body |
| B7-02 | Display total + discount                   | future     | `total` returned today; add discount column |
| B7-03 | Choose payment method                      | scaffolded | `payment_method` field required in `POST /orders` |
| B7-04 | Confirm before submitting                  | scaffolded | Explicit `POST /backend/customer/orders` step |
| B7-05 | Confirmation message                       | scaffolded | `respond_ok(..., 'Order placed successfully')` |
| B7-06 | Cancel order                               | scaffolded | `POST /backend/customer/orders/{id}/cancel` |
| B7-07 | Track order status                         | scaffolded | `GET /backend/customer/orders/{id}` (`STATUS` field) |
| B7-08 | Order history                              | scaffolded | `GET /backend/customer/orders` |
| B7-09 | Receipt / invoice download                 | future     | Generate PDF from `Order::findById` payload |
| B8-01 | Write product review                       | scaffolded | `POST /backend/customer/reviews` |
| B9-01 | Unique order ID                            | scaffolded | `seq_order` (DB sequence) |
| B9-02 | Pickup slot in order summary               | scaffolded | `Order::findById` joins COLLECTION_SLOT |

## C. Trader

| ID    | Description                                | Status     | File / route |
|-------|--------------------------------------------|------------|--------------|
| C1-01 | Register with unique shop details          | scaffolded | `POST /backend/auth/register-trader` |
| C1-02 | Unique credentials                         | scaffolded | `EMAIL` is `UNIQUE` in `USERS` |
| C1-03 | Error on bad login                         | scaffolded | 401 from `AuthController::login` |
| C1-04 | Trader login distinct from customer        | scaffolded | Role check at login; trader-only middleware on `/trader/*` |
| C1-05 | New trader accounts require approval       | scaffolded | Shop status starts `PENDING`; admin POST `/admin/shops/{id}/approve` |
| C2-01 | Update shop name + details                 | scaffolded | `PUT /backend/trader/shop` |
| C3-01 | Add / update / delete products             | scaffolded | `/backend/trader/products` (POST / PUT / DELETE) |
| C3-02 | Manage product content                     | scaffolded | Same endpoints; trader owns shop_id binding |
| C3-03 | Operate only own shop                      | scaffolded | `require_own_shop` ([middleware/auth.php](middleware/auth.php)); every trader query scopes to `current_shop_id()` |
| C3-04 | Optional discounts                         | future     | Add `DISCOUNT_PERCENT` column + checkout calc |
| C4-01 | View sales                                 | scaffolded | `GET /backend/trader/reports/sales` |
| C4-02 | Daily + monthly reports                    | scaffolded | Same route, `?period=daily\|monthly` |
| C4-03 | Remaining stock view                       | scaffolded | `GET /backend/trader/reports/stock` |

## D. Management (Admin)

| ID    | Description                                | Status     | File / route |
|-------|--------------------------------------------|------------|--------------|
| D1-01 | Secure admin login                         | scaffolded | Same `/auth/login`, role gate on all `/admin/*` |
| D1-02 | Manage user + trader profiles              | scaffolded | `GET /backend/admin/users`, `GET /admin/shops` |
| D1-03 | Deactivate trader accounts                 | scaffolded | `POST /backend/admin/shops/{id}/suspend` |
| D1-04 | View all trader info                       | scaffolded | `GET /backend/admin/shops` (joins USERS) |
| D2-01 | See all account activity                   | scaffolded | `User::listAll` + `Order` queries |
| D2-02 | Edit trader info                           | future     | Add `PUT /backend/admin/shops/{id}` |
| D3-01 | Periodic performance reports               | scaffolded | `GET /backend/admin/reports/overview` |
| D4-01 | Auditable payments                         | scaffolded | `GET /backend/admin/reports/payments` |

## E. Non-functional

| ID  | Description                                  | Where it lives |
|-----|----------------------------------------------|----------------|
| E1  | Responsive design                            | UI-only; backend returns plain JSON |
| E2  | Handle volume without degradation            | Sequences + bound parameters; index on `EMAIL`, `SHOP_ID`, `USER_ID` (schema) |
| E3  | User-friendly UI                             | UI-only |
| E4  | Simple UI for non-technical users            | UI-only |
| E5  | Cross-device / cross-browser                 | UI-only; CORS configured in [core/bootstrap.php](core/bootstrap.php) |
| E6  | PayPal payments                              | `PAYMENT.PAYMENT_METHOD = 'PAYPAL'` default; integrate SDK in `Order::placeFromCart` |
| E7  | Data isolation between traders               | `require_own_shop` + `WHERE SHOP_ID = :sid` on all trader queries |
| E8  | Strong passwords                             | `validate_password_or_fail` ([core/request.php](core/request.php)) |
| E9  | Secure storage of user data                  | `password_hash_strong` (bcrypt cost 12); session cookie httponly + samesite |
| E10 | Smooth page loads                            | UI-only |
