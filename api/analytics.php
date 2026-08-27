<?php
/**
 * SpareStack Auto Parts OS - Executive Dashboard & Analytics API
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/settings.php';

header('Content-Type: application/json');
$db = Database::getConnection();

requireAuth();

// 1. Total revenue today & all-time
$today = date('Y-m-d 00:00:00');
$todaySales = (float)$db->query("SELECT COALESCE(SUM(grand_total), 0) FROM sales WHERE created_at >= '{$today}'")->fetchColumn();
$allTimeSales = (float)$db->query("SELECT COALESCE(SUM(grand_total), 0) FROM sales")->fetchColumn();
$totalOrders = (int)$db->query("SELECT COUNT(*) FROM sales")->fetchColumn();

// 2. Staff online vs total
$totalStaff = (int)$db->query("SELECT COUNT(*) FROM users")->fetchColumn();
$onlineStaff = (int)$db->query("SELECT COUNT(*) FROM users WHERE is_online = 1")->fetchColumn();

// 3. Branches count
$totalBranches = (int)$db->query("SELECT COUNT(*) FROM branches WHERE is_active = 1")->fetchColumn();

// 4. Low stock parts count
$lowStockCount = (int)$db->query("SELECT COUNT(DISTINCT product_id) FROM branch_stock WHERE quantity <= reorder_level")->fetchColumn();

// 5. Payment method breakdown
$payBreakdown = $db->query("SELECT payment_method, COUNT(*) as count, SUM(grand_total) as total FROM sales GROUP BY payment_method")->fetchAll();

// 6. Top selling parts
$topParts = $db->query("SELECT product_name, sku, SUM(quantity) as units_sold, SUM(total_price) as revenue 
                        FROM sale_items 
                        GROUP BY product_id, product_name, sku 
                        ORDER BY units_sold DESC LIMIT 5")->fetchAll();

// 7. Recent Transactions
$recentSales = $db->query("SELECT s.invoice_number, s.grand_total, s.payment_method, s.created_at, b.name as branch_name, u.name as cashier_name
                           FROM sales s
                           JOIN branches b ON s.branch_id = b.id
                           LEFT JOIN users u ON s.user_id = u.id
                           ORDER BY s.id DESC LIMIT 8")->fetchAll();

jsonResponse([
    'success' => true,
    'kpis' => [
        'sales_today' => $todaySales,
        'all_time_sales' => $allTimeSales,
        'total_orders' => $totalOrders,
        'online_staff' => $onlineStaff,
        'total_staff' => $totalStaff,
        'total_branches' => $totalBranches,
        'low_stock_count' => $lowStockCount,
    ],
    'payment_breakdown' => $payBreakdown,
    'top_parts' => $topParts,
    'recent_sales' => $recentSales,
    'settings' => Settings::getAll()
]);
