<?php
require 'config.php';
require_role('admin');
$sort = $_GET['sort'] ?? 'name';
$order = $sort === 'orders' ? 'total_orders DESC' : ($sort === 'date' ? 'u.CREATED_DATE DESC' : 'u.FIRST_NAME ASC, u.LAST_NAME ASC');
$sql = "SELECT u.USER_ID,
               u.FIRST_NAME || ' ' || u.LAST_NAME AS NAME,
               u.EMAIL,
               u.CREATED_DATE,
               COUNT(o.ORDER_ID) AS TOTAL_ORDERS,
               NVL(SUM(o.TOTAL_AMOUNT),0) AS TOTAL_SPENT
        FROM USERS u
        LEFT JOIN CUSTOMER_ORDER o ON u.USER_ID = o.USER_ID
        WHERE u.ROLE = 'CUSTOMER'
        GROUP BY u.USER_ID, u.FIRST_NAME, u.LAST_NAME, u.EMAIL, u.CREATED_DATE
        ORDER BY $order";
$stmt = oci_execute_stmt($sql);
json_response(['success' => true, 'customers' => oci_fetch_assoc_all($stmt)]);
?>