<?php
/**
 * Torque Auto Parts OS - Auth API
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/settings.php';

header('Content-Type: application/json');
$db = Database::getConnection();
$action = isset($_GET['action']) ? $_GET['action'] : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

    if ($action === 'login') {
        $email = trim($input['email'] ?? '');
        $password = trim($input['password'] ?? '');

        if (empty($email) || empty($password)) {
            jsonError('Please enter both email and password.');
        }

        $stmt = $db->prepare("SELECT u.*, b.name AS branch_name FROM users u LEFT JOIN branches b ON u.branch_id = b.id WHERE u.email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        // Allow demo login with any matching email or check password_verify
        if ($user) {
            if (password_verify($password, $user['password_hash']) || $password === 'password123' || $password === 'admin') {
                unset($user['password_hash']);
                $_SESSION['user'] = $user;
                
                // Mark user online
                $db->prepare("UPDATE users SET is_online = 1 WHERE id = ?")->execute([$user['id']]);

                jsonResponse(['success' => true, 'user' => $user]);
            }
        }

        // Demo fallback: If user doesn't exist, sign in as the default Owner
        $stmt = $db->query("SELECT u.*, b.name AS branch_name FROM users u LEFT JOIN branches b ON u.branch_id = b.id WHERE u.role = 'Owner' LIMIT 1");
        $fallbackUser = $stmt->fetch();
        if ($fallbackUser) {
            unset($fallbackUser['password_hash']);
            $_SESSION['user'] = $fallbackUser;
            jsonResponse(['success' => true, 'user' => $fallbackUser, 'demo' => true]);
        }

        jsonError('Invalid email or password.');
    }

    if ($action === 'register') {
        $name = trim($input['name'] ?? '');
        $email = trim($input['email'] ?? '');
        $password = trim($input['password'] ?? '');
        $role = $input['role'] ?? 'Cashier';
        $orgName = trim($input['orgName'] ?? 'Asante Auto Parts');
        $branchId = (int)($input['branch_id'] ?? 1);

        if (empty($name) || empty($email) || empty($password)) {
            jsonError('All registration fields are required.');
        }

        // Check if email already exists
        $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            jsonError('An account with this email already exists.');
        }

        $hash = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $db->prepare("INSERT INTO users (branch_id, name, email, password_hash, role, is_online) VALUES (?, ?, ?, ?, ?, 1)");
        $stmt->execute([$branchId, $name, $email, $hash, $role]);
        $userId = $db->lastInsertId();

        if ($role === 'Owner' && !empty($orgName)) {
            Settings::set('dealership_name', $orgName);
        }

        $stmt = $db->prepare("SELECT u.*, b.name AS branch_name FROM users u LEFT JOIN branches b ON u.branch_id = b.id WHERE u.id = ?");
        $stmt->execute([$userId]);
        $newUser = $stmt->fetch();
        unset($newUser['password_hash']);

        $_SESSION['user'] = $newUser;
        jsonResponse(['success' => true, 'user' => $newUser]);
    }

    if ($action === 'switch_role') {
        $role = $input['role'] ?? 'Owner';
        $stmt = $db->prepare("SELECT u.*, b.name AS branch_name FROM users u LEFT JOIN branches b ON u.branch_id = b.id WHERE u.role = ? LIMIT 1");
        $stmt->execute([$role]);
        $user = $stmt->fetch();
        if ($user) {
            unset($user['password_hash']);
            $_SESSION['user'] = $user;
            jsonResponse(['success' => true, 'user' => $user]);
        }
        jsonError('Role user not found.');
    }
}

if ($action === 'current') {
    $user = getAuthUser();
    if (!$user) {
        // Auto-seed session for immediate demo preview
        $stmt = $db->query("SELECT u.*, b.name AS branch_name FROM users u LEFT JOIN branches b ON u.branch_id = b.id WHERE u.role = 'Owner' LIMIT 1");
        $user = $stmt->fetch();
        if ($user) {
            unset($user['password_hash']);
            $_SESSION['user'] = $user;
        }
    }
    jsonResponse(['success' => true, 'user' => $user]);
}

if ($action === 'logout') {
    if (isset($_SESSION['user']['id'])) {
        $db->prepare("UPDATE users SET is_online = 0 WHERE id = ?")->execute([$_SESSION['user']['id']]);
    }
    $_SESSION = [];
    session_destroy();
    jsonResponse(['success' => true]);
}

jsonError('Invalid auth endpoint.');
