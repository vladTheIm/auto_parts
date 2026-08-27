<?php
/**
 * Torque Auto Parts OS - Dealership Settings API
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/settings.php';

header('Content-Type: application/json');
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    jsonResponse(['success' => true, 'settings' => Settings::getAll()]);
}

if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    
    foreach ($input as $key => $val) {
        Settings::set($key, trim($val));
    }

    jsonResponse(['success' => true, 'message' => 'Settings updated successfully.', 'settings' => Settings::getAll()]);
}

jsonError('Invalid settings request.');
