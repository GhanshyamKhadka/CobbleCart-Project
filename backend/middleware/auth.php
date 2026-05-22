<?php
// Authentication & role-based authorization middleware.
// Each "require_*" function exits with 401/403 if access is denied.

function require_login(): void
{
    if (!current_user_id()) {
        respond_error('Authentication required', 401);
    }
}

function require_role($roles): void
{
    require_login();
    $accepted = is_array($roles) ? $roles : [$roles];
    $accepted = array_map('strtolower', $accepted);
    if (!in_array(current_user_role(), $accepted, true)) {
        respond_error('Access denied', 403);
    }
}

function require_customer(): void { require_role('customer'); }
function require_trader(): void   { require_role('trader');   }
function require_admin(): void    { require_role('admin');    }

// C3-03: traders can only operate on their own shop.
function require_own_shop(int $shopId): void
{
    require_trader();
    if (current_shop_id() !== $shopId) {
        respond_error('Traders can only access their own shop', 403);
    }
}
