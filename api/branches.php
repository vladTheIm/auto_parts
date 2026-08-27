<?php
/**
 * Torque Auto Parts OS - Branches & Staff Management API
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/settings.php';

header('Content-Type: application/json');
$db = Database::getConnection();
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';
$user = requireAuth();

if ($method === 'GET') {
    // List all branches with staff and today's sales
    $today = date('Y-m-d 00:00:00');
    
    $branches = $db->query("SELECT * FROM branches ORDER BY id ASC")->fetchAll();
    
    $userStmt = $db->prepare("SELECT id, name, email, role, is_online FROM users WHERE branch_id = ? ORDER BY role DESC");
    $salesStmt = $db->prepare("SELECT COALESCE(SUM(grand_total), 0) FROM sales WHERE branch_id = ? AND created_at >= ?");

    foreach ($branches as &$b) {
        $userStmt->execute([$b['id']]);
        $b['staff'] = $userStmt->fetchAll();

        $salesStmt->execute([$b['id'], $today]);
        $b['sales_today'] = (float)$salesStmt->fetchColumn();
    }

    jsonResponse(['success' => true, 'branches' => $branches]);
}

if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    if ($user['role'] !== 'Owner') {
        jsonError('Forbidden. Only the business owner can manage branches and staff.', 403);
    }

    if ($action === 'create_branch') {
        $name = trim($input['name'] ?? '');
        $location = trim($input['location'] ?? '');
        $phone = trim($input['phone'] ?? '');

        if (empty($name) || empty($location)) {
            jsonError('Branch name and location are required.');
        }

        $stmt = $db->prepare("INSERT INTO branches (name, location, phone, is_active) VALUES (?, ?, ?, 1)");
        $stmt->execute([$name, $location, $phone]);
        $newBranchId = $db->lastInsertId();

        // Seed stock from existing products
        $products = $db->query("SELECT id, stock_quantity, reorder_level FROM products")->fetchAll();
        $stockInsert = $db->prepare("INSERT INTO branch_stock (branch_id, product_id, quantity, reorder_level) VALUES (?, ?, ?, ?)");
        foreach ($products as $p) {
            $stockInsert->execute([$newBranchId, $p['id'], 10, $p['reorder_level']]);
        }

        jsonResponse(['success' => true, 'branch_id' => $newBranchId, 'message' => 'Branch created.']);
    }

    if ($action === 'add_staff') {
        $branchId = (int)($input['branch_id'] ?? 1);
        $name = trim($input['name'] ?? '');
        $email = trim($input['email'] ?? strtolower(str_replace(' ', '.', $name)) . '@torque.com');
        $role = $input['role'] ?? 'Cashier';
        $password = 'password123';

        if (empty($name)) {
            jsonError('Staff name is required.');
        }

        $hash = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $db->prepare("INSERT INTO users (branch_id, name, email, password_hash, role, is_online) VALUES (?, ?, ?, ?, ?, 0)");
        $stmt->execute([$branchId, $name, $email, $hash, $role]);

        jsonResponse(['success' => true, 'user_id' => $db->lastInsertId(), 'message' => 'Staff added.']);
    }

    if ($action === 'toggle_staff_online') {
        $userId = (int)($input['user_id'] ?? 0);
        $status = (int)($input['is_online'] ?? 0);
        $db->prepare("UPDATE users SET is_online = ? WHERE id = ?")->execute([$status, $userId]);
        jsonResponse(['success' => true]);
    }
}

jsonError('Invalid branch request.');
