<?php
/**
 * Torque Auto Parts OS - Products & Inventory API
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/settings.php';

header('Content-Type: application/json');
$db = Database::getConnection();
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';
$branchId = (int)($_GET['branch_id'] ?? ($_SESSION['user']['branch_id'] ?? 1));
$user = requireAuth();

// GET: List products, categories, vehicle fitment lookup
if ($method === 'GET') {
    if ($action === 'categories') {
        $stmt = $db->query("SELECT * FROM categories ORDER BY id ASC");
        jsonResponse(['success' => true, 'categories' => $stmt->fetchAll()]);
    }

    // Default: Fetch products with branch stock
    $search = trim($_GET['search'] ?? '');
    $catSlug = trim($_GET['category'] ?? '');

    $sql = "SELECT p.*, c.name AS category_name, c.slug AS category_slug, 
                   COALESCE(bs.quantity, p.stock_quantity) AS branch_stock,
                   COALESCE(bs.reorder_level, p.reorder_level) AS branch_reorder
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.id
            LEFT JOIN branch_stock bs ON bs.product_id = p.id AND bs.branch_id = ?
            WHERE 1=1";
    $params = [$branchId];

    if (!empty($catSlug) && $catSlug !== 'all') {
        $sql .= " AND c.slug = ?";
        $params[] = $catSlug;
    }

    if (!empty($search)) {
        $sql .= " AND (p.name LIKE ? OR p.sku LIKE ? OR p.barcode LIKE ? OR p.oem_number LIKE ? OR p.fits_vehicles LIKE ?)";
        $wildcard = "%{$search}%";
        $params[] = $wildcard;
        $params[] = $wildcard;
        $params[] = $wildcard;
        $params[] = $wildcard;
        $params[] = $wildcard;
    }

    $sql .= " ORDER BY p.id ASC";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $products = $stmt->fetchAll();

    jsonResponse(['success' => true, 'products' => $products]);
}

// POST: Add new product, edit product, or upload photo
if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    if (!in_array($user['role'], ['Owner', 'Manager'])) {
        jsonError('Forbidden. Only managers can modify the catalog.', 403);
    }

    if ($action === 'create') {
        $name = trim($input['name'] ?? '');
        $sku = strtoupper(trim($input['sku'] ?? ''));
        $categoryId = !empty($input['category_id']) ? (int)$input['category_id'] : 1;
        $oem = trim($input['oem_number'] ?? '');
        $fits = trim($input['fits_vehicles'] ?? 'Universal');
        $costPrice = (float)($input['cost_price'] ?? 0);
        $sellingPrice = (float)($input['selling_price'] ?? 0);
        $stock = (int)($input['stock_quantity'] ?? 10);
        $reorder = (int)($input['reorder_level'] ?? 10);
        $imageUrl = $input['image_url'] ?? null;
        $barcode = trim($input['barcode'] ?? $sku);

        if (empty($name) || empty($sku)) {
            jsonError('Part name and SKU are required.');
        }

        // Check if SKU exists
        $stmt = $db->prepare("SELECT id FROM products WHERE sku = ?");
        $stmt->execute([$sku]);
        if ($stmt->fetch()) {
            jsonError("Part with SKU '{$sku}' already exists.");
        }

        $stmt = $db->prepare("INSERT INTO products (category_id, name, sku, barcode, oem_number, fits_vehicles, cost_price, selling_price, stock_quantity, reorder_level, image_url) 
                              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$categoryId, $name, $sku, $barcode, $oem, $fits, $costPrice, $sellingPrice, $stock, $reorder, $imageUrl]);
        $prodId = $db->lastInsertId();

        // Assign to all branches
        $branches = $db->query("SELECT id FROM branches")->fetchAll(PDO::FETCH_COLUMN);
        $stockStmt = $db->prepare("INSERT INTO branch_stock (branch_id, product_id, quantity, reorder_level) VALUES (?, ?, ?, ?)");
        foreach ($branches as $bId) {
            $stockStmt->execute([$bId, $prodId, $stock, $reorder]);
        }

        // Log stock movement
        $userId = $_SESSION['user']['id'] ?? 1;
        $db->prepare("INSERT INTO stock_movements (product_id, branch_id, user_id, change_qty, previous_qty, new_qty, reason, notes) 
                      VALUES (?, ?, ?, ?, 0, ?, 'Initial Catalog Add', 'New part introduced')")
           ->execute([$prodId, $branchId, $userId, $stock, $stock]);

        jsonResponse(['success' => true, 'product_id' => $prodId, 'message' => 'Product created successfully']);
    }

    if ($action === 'set_image') {
        $productId = (int)($input['product_id'] ?? 0);
        $imageUrl = $input['image_url'] ?? null;
        if (!$productId || !$imageUrl) {
            jsonError('Product ID and image are required.');
        }
        $stmt = $db->prepare("UPDATE products SET image_url = ? WHERE id = ?");
        $stmt->execute([$imageUrl, $productId]);
        jsonResponse(['success' => true, 'message' => 'Image updated']);
    }
}

jsonError('Invalid product request.');
