<?php
/**
 * Torque Auto Parts OS - Shifts & Cash Drawer Reconciliation API
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/settings.php';

header('Content-Type: application/json');
$db = Database::getConnection();
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';
$user = getAuthUser() ?? ['id' => 1, 'name' => 'Demo User', 'role' => 'Manager', 'branch_id' => 1];
$branchId = (int)($user['branch_id'] ?? 1);

if ($method === 'GET') {
    // Check current active shift for user
    if ($action === 'current') {
        $stmt = $db->prepare("SELECT * FROM shifts WHERE user_id = ? AND status = 'Open' ORDER BY id DESC LIMIT 1");
        $stmt->execute([$user['id']]);
        $shift = $stmt->fetch();

        if ($shift) {
            // Calculate shift sales totals so far
            $salesStmt = $db->prepare("SELECT 
                COALESCE(SUM(grand_total), 0) AS total_sales,
                COALESCE(SUM(CASE WHEN payment_method = 'Cash' THEN grand_total ELSE 0 END), 0) AS cash_sales,
                COALESCE(SUM(CASE WHEN payment_method = 'MoMo' THEN grand_total ELSE 0 END), 0) AS momo_sales,
                COALESCE(SUM(CASE WHEN payment_method = 'Card' THEN grand_total ELSE 0 END), 0) AS card_sales,
                COUNT(id) AS transactions_count
                FROM sales 
                WHERE user_id = ? AND branch_id = ? AND created_at >= ?");
            $salesStmt->execute([$user['id'], $branchId, $shift['opened_at']]);
            $shift['sales_summary'] = $salesStmt->fetch();
            $shift['expected_cash_drawer'] = (float)$shift['opening_float'] + (float)$shift['sales_summary']['cash_sales'];
        }

        jsonResponse(['success' => true, 'shift' => $shift]);
    }
}

if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

    if ($action === 'clock_in') {
        // Ensure no open shift
        $stmt = $db->prepare("SELECT id FROM shifts WHERE user_id = ? AND status = 'Open'");
        $stmt->execute([$user['id']]);
        if ($stmt->fetch()) {
            jsonError('You already have an open shift.');
        }

        $openingFloat = (float)($input['opening_float'] ?? 300.00);
        $stmt = $db->prepare("INSERT INTO shifts (user_id, branch_id, opening_float, status) VALUES (?, ?, ?, 'Open')");
        $stmt->execute([$user['id'], $branchId, $openingFloat]);
        $shiftId = $db->lastInsertId();

        // Update user online status
        $db->prepare("UPDATE users SET is_online = 1 WHERE id = ?")->execute([$user['id']]);

        jsonResponse(['success' => true, 'shift_id' => $shiftId, 'message' => 'Shift opened with float ' . $openingFloat]);
    }

    if ($action === 'clock_out') {
        $stmt = $db->prepare("SELECT * FROM shifts WHERE user_id = ? AND status = 'Open' ORDER BY id DESC LIMIT 1");
        $stmt->execute([$user['id']]);
        $shift = $stmt->fetch();

        if (!$shift) {
            jsonError('No active shift found to clock out.');
        }

        $cashCounted = (float)($input['cash_counted'] ?? 0);
        
        // Sum cash sales during this shift
        $salesStmt = $db->prepare("SELECT COALESCE(SUM(grand_total), 0) FROM sales WHERE user_id = ? AND branch_id = ? AND payment_method = 'Cash' AND created_at >= ?");
        $salesStmt->execute([$user['id'], $branchId, $shift['opened_at']]);
        $cashSales = (float)$salesStmt->fetchColumn();

        $expectedCash = (float)$shift['opening_float'] + $cashSales;
        $variance = $cashCounted - $expectedCash;

        $stmt = $db->prepare("UPDATE shifts SET closed_at = CURRENT_TIMESTAMP, closing_cash_counted = ?, expected_cash = ?, cash_variance = ?, status = 'Closed' WHERE id = ?");
        $stmt->execute([$cashCounted, $expectedCash, $variance, $shift['id']]);

        jsonResponse([
            'success' => true,
            'message' => 'Shift closed successfully.',
            'summary' => [
                'opening_float' => $shift['opening_float'],
                'cash_sales' => $cashSales,
                'expected_cash' => $expectedCash,
                'cash_counted' => $cashCounted,
                'variance' => $variance
            ]
        ]);
    }
}

jsonError('Invalid shift action.');
