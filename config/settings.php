<?php
/**
 * Torque Auto Parts OS - Global Settings & Helpers
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

class Settings {
    public static function get($key, $default = null) {
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        $val = $stmt->fetchColumn();
        return $val !== false ? $val : $default;
    }

    public static function set($key, $value) {
        $db = Database::getConnection();
        $stmt = $db->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) 
                              ON CONFLICT(setting_key) DO UPDATE SET setting_value = excluded.setting_value");
        if (Database::getDriver() === 'mysql') {
            $stmt = $db->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) 
                                  ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
        }
        return $stmt->execute([$key, $value]);
    }

    public static function getAll() {
        $db = Database::getConnection();
        $stmt = $db->query("SELECT setting_key, setting_value FROM settings");
        $rows = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        return array_merge([
            'dealership_name' => 'Torque Auto Parts OS',
            'dealership_tagline' => 'Quality Parts & Workshop Supplies',
            'phone' => '+233 24 000 0000',
            'email' => 'sales@torqueautoparts.com',
            'address' => 'Plot 14, Commercial District, Kumasi',
            'currency_symbol' => 'GHS',
            'currency_name' => 'Ghanaian Cedi',
            'vat_rate' => '15.00',
            'receipt_footer' => 'Thank you for your business! Electrical parts non-refundable once installed.',
        ], $rows);
    }
}

function jsonResponse($data, $status = 200) {
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

function jsonError($message, $status = 400) {
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => $message]);
    exit;
}

function getAuthUser() {
    return isset($_SESSION['user']) ? $_SESSION['user'] : null;
}

function requireAuth($allowedRoles = []) {
    $user = getAuthUser();
    if (!$user) {
        jsonError('Unauthorized. Please log in.', 401);
    }
    if (!empty($allowedRoles) && !in_array($user['role'], $allowedRoles)) {
        jsonError('Forbidden. Insufficient permissions for this action.', 403);
    }
    return $user;
}
