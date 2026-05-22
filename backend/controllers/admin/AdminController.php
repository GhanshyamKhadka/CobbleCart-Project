<?php
// Admin management endpoints. Covers D1-*, D2-*, D3-*, D4-*.
// Routes:
//   GET    /backend/admin/users[?role=customer|trader]    (D1-04, D2-01)
//   GET    /backend/admin/shops[?status=pending]
//   POST   /backend/admin/shops/{id}/approve              (C1-05)
//   POST   /backend/admin/shops/{id}/suspend              (D1-03)
//   GET    /backend/admin/products?pending=1
//   POST   /backend/admin/products/{id}/approve
//   POST   /backend/admin/products/{id}/reject
//   GET    /backend/admin/reports/overview                (D3-01)
//   GET    /backend/admin/reports/payments                (D4-01)

class AdminController
{
    public static function listUsers(): void
    {
        require_admin();
        $role = $_GET['role'] ?? null;
        respond_ok(['users' => User::listAll($role)]);
    }

    public static function listShops(): void
    {
        require_admin();
        $status = $_GET['status'] ?? null;
        respond_ok(['shops' => Shop::listAll($status)]);
    }

    public static function approveShop(int $shopId): void
    {
        require_admin();
        Shop::setStatus($shopId, 'APPROVED');
        respond_ok(null, 'Shop approved');
    }

    public static function suspendShop(int $shopId): void
    {
        require_admin();
        Shop::setStatus($shopId, 'SUSPENDED');
        respond_ok(null, 'Shop suspended');
    }

    public static function listProducts(): void
    {
        require_admin();
        respond_ok([
            'products' => Product::search($_GET, 'admin', null),
        ]);
    }

    public static function approveProduct(int $productId): void
    {
        require_admin();
        Product::setApprovalStatus($productId, 'APPROVED');
        respond_ok(null, 'Product approved');
    }

    public static function rejectProduct(int $productId): void
    {
        require_admin();
        Product::setApprovalStatus($productId, 'REJECTED');
        respond_ok(null, 'Product rejected');
    }

    public static function overviewReport(): void
    {
        require_admin();
        respond_ok(['overview' => Report::platformOverview()]);
    }

    public static function paymentsReport(): void
    {
        require_admin();
        $status = $_GET['status'] ?? null;
        respond_ok(['payments' => Report::paymentAudit($status)]);
    }
}
