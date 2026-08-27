<?php
/**
 * Torque Auto Parts OS - Purchase Orders & Suppliers API
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/settings.php';

header('Content-Type: application/json');
$db = Database::getConnection();
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';
$branchId = (int)($_GET['branch_id'] ?? ($_SESSION['user']['branch_id'] ?? 1));

if ($method === 'GET') {
    if ($action === 'suppliers') {
        $stmt = $db->query("SELECT * FROM suppliers ORDER BY name ASC");
        jsonResponse(['success' => true, 'suppliers' => $stmt->fetchAll()]);
    }

    // Default: List Purchase Orders
    $stmt = $db->prepare("SELECT po.*, s.name as supplier_name, p.name as product_name, p.sku, b.name as branch_name 
                          FROM purchase_orders po
                          JOIN suppliers s ON po.supplier_id = s.id
                          JOIN products p ON po.product_id = p.id
                          JOIN branches b ON po.branch_id = b.id
                          WHERE po.branch_id = ?
                          ORDER BY po.id DESC");
    $stmt->execute([$branchId]);
    jsonResponse(['success' => true, 'purchase_orders' => $stmt->fetchAll()]);
}

if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

    if ($action === 'create') {
        $supplierId = (int)($input['supplier_id'] ?? 0);
        $productId = (int)($input['product_id'] ?? 0);
        $qty = (int)($input['quantity'] ?? 0);
        $unitCost = (float)($input['unit_cost'] ?? 0);

        if (!$supplierId || !$productId || $qty <= 0) {
            jsonError('Supplier, product, and valid quantity are required.');
        }

        // If unitCost not supplied, lookup product cost
        if ($unitCost <= 0) {
            $costStmt = $db->prepare("SELECT cost_price FROM products WHERE id = ?");
            $costStmt->execute([$productId]);
            $unitCost = (float)$costStmt->fetchColumn();
        }

        $totalCost = $qty * $unitCost;
        $poNumber = 'PO-' . date('Y') . '-' . strtoupper(substr(uniqid(), -4));

        $stmt = $db->prepare("INSERT INTO purchase_orders (po_number, supplier_id, branch_id, product_id, quantity, unit_cost, total_cost, status) 
                              VALUES (?, ?, ?, ?, ?, ?, ?, 'Ordered')");
        $stmt->execute([$poNumber, $supplierId, $branchId, $productId, $qty, $unitCost, $totalCost]);

        jsonResponse(['success' => true, 'po_number' => $poNumber, 'message' => 'Purchase order created.']);
    }

    if ($action === 'receive') {
        $poId = (int)($input['po_id'] ?? 0);
        $stmt = $db->prepare("SELECT * FROM purchase_orders WHERE id = ?");
        $stmt->execute([$poId]);
        $po = $stmt->fetch();

        if (!$po || $po['status'] === 'Received') {
            jsonError('Invalid purchase order or order already marked received.');
        }

        $db->beginTransaction();
        try {
            // Update PO status
            $db->prepare("UPDATE purchase_orders SET status = 'Received', received_at = CURRENT_TIMESTAMP WHERE id = ?")->execute([$poId]);

            // Increment stock in branch_stock
            $db->prepare("UPDATE branch_stock SET quantity = quantity + ? WHERE branch_id = ? AND product_id = ?")
               ->execute([$po['quantity'], $po['branch_id'], $po['product_id']]);

            // Increment master stock
            $db->prepare("UPDATE products SET stock_quantity = stock_quantity + ? WHERE id = ?")
               ->execute([$po['quantity'], $po['product_id']]);

            // Audit movement
            $user = getAuthUser() ?? ['id' => 1];
            $db->prepare("INSERT INTO stock_movements (product_id, branch_id, user_id, change_qty, previous_qty, new_qty, reason, notes) 
                          VALUES (?, ?, ?, ?, 0, 0, 'PO Received', ?)")
               ->execute([$po['product_id'], $po['branch_id'], $user['id'], $po['quantity'], "Received from PO #{$po['po_number']}"]);

            $db->commit();
            jsonResponse(['success' => true, 'message' => 'PO marked received and stock updated.']);
        } catch (Exception $e) {
            $db->rollBack();
            jsonError('Failed to receive PO: ' . $e->getMessage());
        }
    }
}

jsonError('Invalid purchase order action.');
