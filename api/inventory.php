<?php
/**
 * Torque Auto Parts OS - Inventory & Stock Movements API
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/settings.php';

header('Content-Type: application/json');
$db = Database::getConnection();
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';
$branchId = (int)($_GET['branch_id'] ?? ($_SESSION['user']['branch_id'] ?? 1));
$user = getAuthUser() ?? ['id' => 1, 'name' => 'Manager Demo', 'role' => 'Manager'];

if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

    if ($action === 'restock') {
        $productId = (int)($input['product_id'] ?? 0);
        $qty = (int)($input['quantity'] ?? 0);
        $reason = trim($input['reason'] ?? 'Manual Restock');
        $notes = trim($input['notes'] ?? 'Supplier delivery');

        if ($productId <= 0 || $qty <= 0) {
            jsonError('Valid product and positive quantity required.');
        }

        // Get current stock
        $stmt = $db->prepare("SELECT quantity FROM branch_stock WHERE branch_id = ? AND product_id = ?");
        $stmt->execute([$branchId, $productId]);
        $curr = $stmt->fetchColumn();
        $prevQty = $curr !== false ? (int)$curr : 0;
        $newQty = $prevQty + $qty;

        // Upsert branch_stock
        $stmt = $db->prepare("INSERT INTO branch_stock (branch_id, product_id, quantity) VALUES (?, ?, ?)
                              ON CONFLICT(branch_id, product_id) DO UPDATE SET quantity = ?");
        if (Database::getDriver() === 'mysql') {
            $stmt = $db->prepare("INSERT INTO branch_stock (branch_id, product_id, quantity) VALUES (?, ?, ?)
                                  ON DUPLICATE KEY UPDATE quantity = ?");
        }
        $stmt->execute([$branchId, $productId, $newQty, $newQty]);

        // Update product master stock
        $db->prepare("UPDATE products SET stock_quantity = stock_quantity + ? WHERE id = ?")->execute([$qty, $productId]);

        // Audit movement
        $db->prepare("INSERT INTO stock_movements (product_id, branch_id, user_id, change_qty, previous_qty, new_qty, reason, notes) 
                      VALUES (?, ?, ?, ?, ?, ?, ?, ?)")
           ->execute([$productId, $branchId, $user['id'], $qty, $prevQty, $newQty, $reason, $notes]);

        jsonResponse(['success' => true, 'new_quantity' => $newQty, 'message' => "Restocked {$qty} units successfully."]);
    }

    if ($action === 'adjust') {
        $productId = (int)($input['product_id'] ?? 0);
        $targetQty = (int)($input['target_quantity'] ?? 0);
        $reason = trim($input['reason'] ?? 'Audit / Count Adjustment');
        $notes = trim($input['notes'] ?? '');

        if ($productId <= 0 || $targetQty < 0) {
            jsonError('Invalid adjustment parameters.');
        }

        $stmt = $db->prepare("SELECT quantity FROM branch_stock WHERE branch_id = ? AND product_id = ?");
        $stmt->execute([$branchId, $productId]);
        $prevQty = (int)$stmt->fetchColumn();
        $diff = $targetQty - $prevQty;

        $db->prepare("UPDATE branch_stock SET quantity = ? WHERE branch_id = ? AND product_id = ?")
           ->execute([$targetQty, $branchId, $productId]);

        $db->prepare("UPDATE products SET stock_quantity = ? WHERE id = ?")
           ->execute([$targetQty, $productId]);

        $db->prepare("INSERT INTO stock_movements (product_id, branch_id, user_id, change_qty, previous_qty, new_qty, reason, notes) 
                      VALUES (?, ?, ?, ?, ?, ?, ?, ?)")
           ->execute([$productId, $branchId, $user['id'], $diff, $prevQty, $targetQty, $reason, $notes]);

        jsonResponse(['success' => true, 'new_quantity' => $targetQty, 'message' => 'Stock updated.']);
    }

    if ($action === 'transfer') {
        $fromBranchId = (int)($input['from_branch_id'] ?? 1);
        $toBranchId = (int)($input['to_branch_id'] ?? 2);
        $productId = (int)($input['product_id'] ?? 0);
        $qty = (int)($input['quantity'] ?? 0);

        if ($fromBranchId === $toBranchId) {
            jsonError('Source and destination branches must be different.');
        }
        if (!$productId || $qty <= 0) {
            jsonError('Valid product and transfer quantity required.');
        }

        // Check source stock
        $stmt = $db->prepare("SELECT quantity FROM branch_stock WHERE branch_id = ? AND product_id = ?");
        $stmt->execute([$fromBranchId, $productId]);
        $srcQty = (int)$stmt->fetchColumn();

        if ($srcQty < $qty) {
            jsonError("Insufficient stock in source branch (Available: {$srcQty}).");
        }

        // Destination stock
        $stmt->execute([$toBranchId, $productId]);
        $dstQty = (int)$stmt->fetchColumn();

        $db->beginTransaction();
        try {
            // Deduct from source
            $db->prepare("UPDATE branch_stock SET quantity = quantity - ? WHERE branch_id = ? AND product_id = ?")
               ->execute([$qty, $fromBranchId, $productId]);
            
            // Add to destination
            $stmt = $db->prepare("INSERT INTO branch_stock (branch_id, product_id, quantity) VALUES (?, ?, ?)
                                  ON CONFLICT(branch_id, product_id) DO UPDATE SET quantity = quantity + ?");
            if (Database::getDriver() === 'mysql') {
                $stmt = $db->prepare("INSERT INTO branch_stock (branch_id, product_id, quantity) VALUES (?, ?, ?)
                                      ON DUPLICATE KEY UPDATE quantity = quantity + VALUES(quantity)");
            }
            $stmt->execute([$toBranchId, $productId, $qty, $qty]);

            // Audit movements
            $db->prepare("INSERT INTO stock_movements (product_id, branch_id, user_id, change_qty, previous_qty, new_qty, reason, notes) 
                          VALUES (?, ?, ?, ?, ?, ?, 'Inter-Branch Transfer Out', ?)")
               ->execute([$productId, $fromBranchId, $user['id'], -$qty, $srcQty, $srcQty - $qty, "Transferred to Branch #{$toBranchId}"]);

            $db->prepare("INSERT INTO stock_movements (product_id, branch_id, user_id, change_qty, previous_qty, new_qty, reason, notes) 
                          VALUES (?, ?, ?, ?, ?, ?, 'Inter-Branch Transfer In', ?)")
               ->execute([$productId, $toBranchId, $user['id'], $qty, $dstQty, $dstQty + $qty, "Received from Branch #{$fromBranchId}"]);

            $db->commit();
            jsonResponse(['success' => true, 'message' => "Successfully transferred {$qty} units between branches."]);
        } catch (Exception $e) {
            $db->rollBack();
            jsonError('Transfer failed: ' . $e->getMessage());
        }
    }
}

if ($method === 'GET') {
    if ($action === 'movements') {
        $productId = !empty($_GET['product_id']) ? (int)$_GET['product_id'] : null;
        $sql = "SELECT sm.*, p.name as product_name, p.sku, u.name as user_name, b.name as branch_name 
                FROM stock_movements sm
                JOIN products p ON sm.product_id = p.id
                LEFT JOIN users u ON sm.user_id = u.id
                LEFT JOIN branches b ON sm.branch_id = b.id
                WHERE sm.branch_id = ?";
        $params = [$branchId];

        if ($productId) {
            $sql .= " AND sm.product_id = ?";
            $params[] = $productId;
        }

        $sql .= " ORDER BY sm.id DESC LIMIT 50";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        jsonResponse(['success' => true, 'movements' => $stmt->fetchAll()]);
    }

    if ($action === 'low_stock') {
        $stmt = $db->prepare("SELECT p.*, bs.quantity as branch_stock, bs.reorder_level as branch_reorder 
                              FROM products p
                              JOIN branch_stock bs ON bs.product_id = p.id AND bs.branch_id = ?
                              WHERE bs.quantity <= bs.reorder_level
                              ORDER BY bs.quantity ASC");
        $stmt->execute([$branchId]);
        jsonResponse(['success' => true, 'low_stock' => $stmt->fetchAll()]);
    }
}

jsonError('Invalid inventory action.');
