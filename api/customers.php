<?php
/**
 * SpareStack Auto Parts OS - Customers & Mechanic Accounts API
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/settings.php';

header('Content-Type: application/json');
$db = Database::getConnection();
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';
$user = requireAuth();

if ($method === 'GET') {
    $search = trim($_GET['search'] ?? '');
    $sql = "SELECT * FROM customers WHERE 1=1";
    $params = [];

    if (!empty($search)) {
        $sql .= " AND (name LIKE ? OR workshop_name LIKE ? OR phone LIKE ?)";
        $params = ["%{$search}%", "%{$search}%", "%{$search}%"];
    }

    $sql .= " ORDER BY name ASC";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    jsonResponse(['success' => true, 'customers' => $stmt->fetchAll()]);
}

if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    if ($user['role'] !== 'Owner') {
        jsonError('Forbidden. Only the business owner can manage garage accounts.', 403);
    }

    if ($action === 'create') {
        $name = trim($input['name'] ?? '');
        $phone = trim($input['phone'] ?? '');
        $workshop = trim($input['workshop_name'] ?? '');
        $limit = (float)($input['credit_limit'] ?? 2000.00);

        if (empty($name) || empty($phone)) {
            jsonError('Customer name and phone number are required.');
        }

        $stmt = $db->prepare("INSERT INTO customers (name, phone, workshop_name, credit_limit, credit_balance) VALUES (?, ?, ?, ?, 0.00)");
        $stmt->execute([$name, $phone, $workshop, $limit]);

        jsonResponse(['success' => true, 'customer_id' => $db->lastInsertId(), 'message' => 'Customer created.']);
    }

    if ($action === 'pay_credit') {
        $customerId = (int)($input['customer_id'] ?? 0);
        $amount = (float)($input['amount'] ?? 0);

        if (!$customerId || $amount <= 0) {
            jsonError('Customer and positive payment amount required.');
        }

        $stmt = $db->prepare("UPDATE customers SET credit_balance = CASE WHEN credit_balance - ? < 0 THEN 0 ELSE credit_balance - ? END WHERE id = ?");
        $stmt->execute([$amount, $amount, $customerId]);

        jsonResponse(['success' => true, 'message' => 'Credit payment recorded.']);
    }
}

jsonError('Invalid customer request.');
