<?php
require 'config.php';
$data = input_json();
require_fields($data, ['product_id', 'quantity']);
$productId = (int)$data['product_id'];
$quantity = max(1, (int)$data['quantity']);
$cart = $_SESSION['cart'] ?? [];
$cart[$productId] = ($cart[$productId] ?? 0) + $quantity;
$_SESSION['cart'] = $cart;
json_response(['success' => true, 'message' => 'Added to cart', 'cart' => $cart]);
?>