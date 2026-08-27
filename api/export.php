<?php
/**
 * SpareStack Auto Parts OS - Financial & Inventory CSV Export API
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/settings.php';

$db = Database::getConnection();

requireAuth(['Owner', 'Manager']);

$type = $_GET['type'] ?? 'sales';
$branchId = (int)($_GET['branch_id'] ?? 1);

if ($type === 'sales') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="SpareStack_Sales_Report_' . date('Y-m-d') . '.csv"');

    $output = fopen('php://output', 'w');
    fputcsv($output, ['Invoice Number', 'Date', 'Branch', 'Cashier', 'Customer', 'Subtotal (GHS)', 'VAT 15% (GHS)', 'Grand Total (GHS)', 'Payment Method', 'Status']);

    $stmt = $db->prepare("SELECT s.invoice_number, s.created_at, b.name as branch_name, u.name as cashier_name, 
                                 COALESCE(c.name, 'Walk-in') as customer_name, s.subtotal, s.vat_amount, s.grand_total, s.payment_method, s.status
                          FROM sales s
                          JOIN branches b ON s.branch_id = b.id
                          LEFT JOIN users u ON s.user_id = u.id
                          LEFT JOIN customers c ON s.customer_id = c.id
                          ORDER BY s.id DESC");
    $stmt->execute();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($output, [
            $row['invoice_number'],
            $row['created_at'],
            $row['branch_name'],
            $row['cashier_name'] ?? 'Staff',
            $row['customer_name'],
            number_format($row['subtotal'], 2),
            number_format($row['vat_amount'], 2),
            number_format($row['grand_total'], 2),
            $row['payment_method'],
            $row['status']
        ]);
    }
    fclose($output);
    exit;
}

if ($type === 'inventory') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="SpareStack_Inventory_Valuation_' . date('Y-m-d') . '.csv"');

    $output = fopen('php://output', 'w');
    fputcsv($output, ['Part Name', 'SKU', 'OEM Number', 'Vehicle Fitment', 'In Stock', 'Unit Cost (GHS)', 'Unit Selling Price (GHS)', 'Total Cost Value (GHS)', 'Total Retail Value (GHS)', 'Projected Profit Margin (GHS)']);

    $stmt = $db->prepare("SELECT p.name, p.sku, p.oem_number, p.fits_vehicles, 
                                 COALESCE(bs.quantity, p.stock_quantity) as stock, 
                                 p.cost_price, p.selling_price
                          FROM products p
                          LEFT JOIN branch_stock bs ON bs.product_id = p.id AND bs.branch_id = ?
                          ORDER BY p.name ASC");
    $stmt->execute([$branchId]);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $costVal = $row['stock'] * $row['cost_price'];
        $retailVal = $row['stock'] * $row['selling_price'];
        $margin = $retailVal - $costVal;

        fputcsv($output, [
            $row['name'],
            $row['sku'],
            $row['oem_number'] ?? 'N/A',
            $row['fits_vehicles'] ?? 'Universal',
            $row['stock'],
            number_format($row['cost_price'], 2),
            number_format($row['selling_price'], 2),
            number_format($costVal, 2),
            number_format($retailVal, 2),
            number_format($margin, 2)
        ]);
    }
    fclose($output);
    exit;
}
