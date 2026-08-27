<?php
/**
 * Torque Auto Parts OS - Sales & POS Transactions API
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/settings.php';

header('Content-Type: application/json');
$db = Database::getConnection();
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';
$user = getAuthUser() ?? ['id' => 1, 'name' => 'Cashier Demo', 'role' => 'Owner'];

if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

    if ($action === 'checkout') {
        $items = $input['items'] ?? [];
        $paymentMethod = $input['payment_method'] ?? 'Cash';
        $branchId = (int)($input['branch_id'] ?? ($user['branch_id'] ?? 1));
        $customerId = !empty($input['customer_id']) ? (int)$input['customer_id'] : null;
        $discount = (float)($input['discount_amount'] ?? 0);
        $paymentRef = trim($input['payment_ref'] ?? '');

        if (empty($items)) {
            jsonError('Cart is empty.');
        }

        try {
            $db->beginTransaction();

            $subtotal = 0;
            $saleItemsData = [];

            // Calculate totals and verify inventory
            foreach ($items as $item) {
                $prodId = (int)$item['id'];
                $qty = (int)$item['qty'];
                if ($qty <= 0) continue;

                $stmt = $db->prepare("SELECT p.*, COALESCE(bs.quantity, p.stock_quantity) as current_stock 
                                      FROM products p 
                                      LEFT JOIN branch_stock bs ON bs.product_id = p.id AND bs.branch_id = ? 
                                      WHERE p.id = ?");
                $stmt->execute([$branchId, $prodId]);
                $prod = $stmt->fetch();

                if (!$prod) {
                    throw new Exception("Product ID {$prodId} not found.");
                }

                $lineTotal = $prod['selling_price'] * $qty;
                $subtotal += $lineTotal;

                $saleItemsData[] = [
                    'product_id' => $prod['id'],
                    'product_name' => $prod['name'],
                    'sku' => $prod['sku'],
                    'unit_price' => $prod['selling_price'],
                    'cost_price' => $prod['cost_price'],
                    'quantity' => $qty,
                    'total_price' => $lineTotal,
                    'prev_stock' => (int)$prod['current_stock'],
                    'new_stock' => max(0, (int)$prod['current_stock'] - $qty)
                ];
            }

            $vatRate = (float)Settings::get('vat_rate', 15.00);
            $vatAmount = round($subtotal * ($vatRate / 100), 2);
            $grandTotal = round($subtotal + $vatAmount - $discount, 2);

            // Generate unique invoice number: INV-YYMMDD-XXXX
            $invNumber = 'INV-' . date('ymd') . '-' . strtoupper(substr(uniqid(), -4));

            // Insert into sales table
            $stmt = $db->prepare("INSERT INTO sales (invoice_number, branch_id, user_id, customer_id, subtotal, vat_amount, discount_amount, grand_total, payment_method, payment_ref, status) 
                                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Completed')");
            $stmt->execute([$invNumber, $branchId, $user['id'], $customerId, $subtotal, $vatAmount, $discount, $grandTotal, $paymentMethod, $paymentRef]);
            $saleId = $db->lastInsertId();

            // Insert sale items and deduct stock
            $itemStmt = $db->prepare("INSERT INTO sale_items (sale_id, product_id, product_name, sku, unit_price, cost_price, quantity, total_price) 
                                      VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            
            $stockUpdate = $db->prepare("UPDATE branch_stock SET quantity = ? WHERE branch_id = ? AND product_id = ?");
            $mainStockUpdate = $db->prepare("UPDATE products SET stock_quantity = ? WHERE id = ?");
            
            $movStmt = $db->prepare("INSERT INTO stock_movements (product_id, branch_id, user_id, change_qty, previous_qty, new_qty, reason, notes) 
                                     VALUES (?, ?, ?, ?, ?, ?, 'POS Sale', ?)");

            foreach ($saleItemsData as $si) {
                $itemStmt->execute([
                    $saleId, $si['product_id'], $si['product_name'], $si['sku'],
                    $si['unit_price'], $si['cost_price'], $si['quantity'], $si['total_price']
                ]);

                // Update branch stock
                $stockUpdate->execute([$si['new_stock'], $branchId, $si['product_id']]);
                $mainStockUpdate->execute([$si['new_stock'], $si['product_id']]);

                // Record stock movement audit
                $movStmt->execute([
                    $si['product_id'], $branchId, $user['id'], -$si['quantity'],
                    $si['prev_stock'], $si['new_stock'], "Sale #{$invNumber}"
                ]);
            }

            // If payment is Credit, update customer credit balance
            if ($paymentMethod === 'Credit' && $customerId) {
                $db->prepare("UPDATE customers SET credit_balance = credit_balance + ? WHERE id = ?")
                   ->execute([$grandTotal, $customerId]);
            }

            $db->commit();

            // Return full receipt payload
            $dealership = Settings::getAll();
            jsonResponse([
                'success' => true,
                'sale_id' => $saleId,
                'invoice_number' => $invNumber,
                'date' => date('Y-m-d H:i:s'),
                'cashier' => $user['name'],
                'subtotal' => $subtotal,
                'vat_amount' => $vatAmount,
                'discount_amount' => $discount,
                'grand_total' => $grandTotal,
                'payment_method' => $paymentMethod,
                'items' => $saleItemsData,
                'dealership' => $dealership
            ]);

        } catch (Exception $e) {
            $db->rollBack();
            jsonError('Checkout failed: ' . $e->getMessage());
        }
    }

    if ($action === 'return_sale') {
        $invoiceNumber = trim($input['invoice_number'] ?? '');
        $reason = trim($input['reason'] ?? 'Customer Return');

        if (empty($invoiceNumber)) {
            jsonError('Invoice number is required.');
        }

        $stmt = $db->prepare("SELECT * FROM sales WHERE invoice_number = ?");
        $stmt->execute([$invoiceNumber]);
        $sale = $stmt->fetch();

        if (!$sale) {
            jsonError("Sale '{$invoiceNumber}' not found.");
        }
        if ($sale['status'] === 'Refunded') {
            jsonError("Invoice '{$invoiceNumber}' has already been refunded.");
        }

        $db->beginTransaction();
        try {
            // Mark sale as Refunded
            $db->prepare("UPDATE sales SET status = 'Refunded' WHERE id = ?")->execute([$sale['id']]);

            // Get items
            $itemsStmt = $db->prepare("SELECT * FROM sale_items WHERE sale_id = ?");
            $itemsStmt->execute([$sale['id']]);
            $items = $itemsStmt->fetchAll();

            $stockUpdate = $db->prepare("UPDATE branch_stock SET quantity = quantity + ? WHERE branch_id = ? AND product_id = ?");
            $mainStockUpdate = $db->prepare("UPDATE products SET stock_quantity = stock_quantity + ? WHERE id = ?");
            $movStmt = $db->prepare("INSERT INTO stock_movements (product_id, branch_id, user_id, change_qty, previous_qty, new_qty, reason, notes) 
                                     VALUES (?, ?, ?, ?, 0, 0, 'Sales Return / Refund', ?)");

            foreach ($items as $it) {
                $stockUpdate->execute([$it['quantity'], $sale['branch_id'], $it['product_id']]);
                $mainStockUpdate->execute([$it['quantity'], $it['product_id']]);
                $movStmt->execute([
                    $it['product_id'], $sale['branch_id'], $user['id'], $it['quantity'], "Refund on #{$invoiceNumber}: {$reason}"
                ]);
            }

            // If credit sale, deduct balance from customer
            if ($sale['payment_method'] === 'Credit' && !empty($sale['customer_id'])) {
                $db->prepare("UPDATE customers SET credit_balance = CASE WHEN credit_balance - ? < 0 THEN 0 ELSE credit_balance - ? END WHERE id = ?")
                   ->execute([$sale['grand_total'], $sale['grand_total'], $sale['customer_id']]);
            }

            $db->commit();
            jsonResponse(['success' => true, 'message' => "Sale #{$invoiceNumber} refunded successfully and items restocked."]);
        } catch (Exception $e) {
            $db->rollBack();
            jsonError('Return failed: ' . $e->getMessage());
        }
    }
}

if ($method === 'GET') {
    // List sales history
    $branchId = (int)($_GET['branch_id'] ?? 1);
    $limit = (int)($_GET['limit'] ?? 20);

    $stmt = $db->prepare("SELECT s.*, u.name as cashier_name, b.name as branch_name, c.name as customer_name
                          FROM sales s
                          LEFT JOIN users u ON s.user_id = u.id
                          LEFT JOIN branches b ON s.branch_id = b.id
                          LEFT JOIN customers c ON s.customer_id = c.id
                          WHERE s.branch_id = ?
                          ORDER BY s.id DESC LIMIT ?");
    $stmt->execute([$branchId, $limit]);
    $sales = $stmt->fetchAll();

    jsonResponse(['success' => true, 'sales' => $sales]);
}

jsonError('Invalid sales action.');
