<?php $pageTitle = 'Products'; require_once __DIR__ . '/includes/header.php'; ?>
<?php
$db = getDB();
$action = $_GET['action'] ?? 'list';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// --- STORE (POST) ---
if ($action === 'store' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = trim($_POST['code'] ?? '');
    $name = trim($_POST['name'] ?? '');
    $category_id = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;
    $description = trim($_POST['description'] ?? '');
    $unit = trim($_POST['unit'] ?? 'pcs');
    $purchase_price = (float)($_POST['purchase_price'] ?? 0);
    $selling_price = (float)($_POST['selling_price'] ?? 0);
    $stock = (int)($_POST['stock'] ?? 0);
    $min_stock = (int)($_POST['min_stock'] ?? 0);
    $max_stock = (int)($_POST['max_stock'] ?? 0);
    $location = trim($_POST['location'] ?? '');
    $status = trim($_POST['status'] ?? 'active');

    if (empty($code) || empty($name)) {
        setFlash('error', 'Product code and name are required.');
        header('Location: ' . BASE_URL . 'products.php?action=create');
        exit;
    }

    try {
        $stmt = $db->prepare("INSERT INTO products (code, name, category_id, description, unit, purchase_price, selling_price, stock, min_stock, max_stock, location, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$code, $name, $category_id, $description, $unit, $purchase_price, $selling_price, $stock, $min_stock, $max_stock, $location, $status]);

        $productId = $db->lastInsertId();

        // Record initial stock movement if stock > 0
        if ($stock > 0) {
            $stmt2 = $db->prepare("INSERT INTO stock_movements (product_id, type, quantity, reference_type, notes, created_by) VALUES (?, 'in', ?, 'initial', 'Initial stock on product creation', ?)");
            $stmt2->execute([$productId, $stock, $_SESSION['user_id']]);
        }

        setFlash('success', 'Product created successfully.');
        header('Location: ' . BASE_URL . 'products.php');
        exit;
    } catch (PDOException $e) {
        setFlash('error', 'Failed to create product: ' . $e->getMessage());
        header('Location: ' . BASE_URL . 'products.php?action=create');
        exit;
    }
}

// --- UPDATE (POST) ---
if ($action === 'update' && $_SERVER['REQUEST_METHOD'] === 'POST' && $id > 0) {
    $code = trim($_POST['code'] ?? '');
    $name = trim($_POST['name'] ?? '');
    $category_id = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;
    $description = trim($_POST['description'] ?? '');
    $unit = trim($_POST['unit'] ?? 'pcs');
    $purchase_price = (float)($_POST['purchase_price'] ?? 0);
    $selling_price = (float)($_POST['selling_price'] ?? 0);
    $min_stock = (int)($_POST['min_stock'] ?? 0);
    $max_stock = (int)($_POST['max_stock'] ?? 0);
    $location = trim($_POST['location'] ?? '');
    $status = trim($_POST['status'] ?? 'active');

    if (empty($code) || empty($name)) {
        setFlash('error', 'Product code and name are required.');
        header('Location: ' . BASE_URL . 'products.php?action=edit&id=' . $id);
        exit;
    }

    try {
        $stmt = $db->prepare("UPDATE products SET code=?, name=?, category_id=?, description=?, unit=?, purchase_price=?, selling_price=?, min_stock=?, max_stock=?, location=?, status=? WHERE id=?");
        $stmt->execute([$code, $name, $category_id, $description, $unit, $purchase_price, $selling_price, $min_stock, $max_stock, $location, $status, $id]);
        setFlash('success', 'Product updated successfully.');
        header('Location: ' . BASE_URL . 'products.php?action=view&id=' . $id);
        exit;
    } catch (PDOException $e) {
        setFlash('error', 'Failed to update product: ' . $e->getMessage());
        header('Location: ' . BASE_URL . 'products.php?action=edit&id=' . $id);
        exit;
    }
}

// --- DELETE (POST) ---
if ($action === 'delete' && $_SERVER['REQUEST_METHOD'] === 'POST' && $id > 0) {
    // Check for order items referencing this product
    $stmt = $db->prepare("SELECT COUNT(*) FROM sales_order_items WHERE product_id = ?");
    $stmt->execute([$id]);
    $soCount = (int)$stmt->fetchColumn();

    $stmt = $db->prepare("SELECT COUNT(*) FROM purchase_order_items WHERE product_id = ?");
    $stmt->execute([$id]);
    $poCount = (int)$stmt->fetchColumn();

    if ($soCount > 0 || $poCount > 0) {
        setFlash('error', 'Cannot delete product. It is referenced in ' . ($soCount + $poCount) . ' order item(s).');
        header('Location: ' . BASE_URL . 'products.php');
        exit;
    }

    // Delete related stock movements first
    $stmt = $db->prepare("DELETE FROM stock_movements WHERE product_id = ?");
    $stmt->execute([$id]);

    $stmt = $db->prepare("DELETE FROM products WHERE id = ?");
    $stmt->execute([$id]);
    setFlash('success', 'Product deleted successfully.');
    header('Location: ' . BASE_URL . 'products.php');
    exit;
}

// --- VIEW ---
if ($action === 'view' && $id > 0):
    $stmt = $db->prepare("SELECT p.*, pc.name as category_name FROM products p LEFT JOIN product_categories pc ON p.category_id = pc.id WHERE p.id = ?");
    $stmt->execute([$id]);
    $product = $stmt->fetch();
    if (!$product) { setFlash('error', 'Product not found.'); header('Location: ' . BASE_URL . 'products.php'); exit; }

    $stmt = $db->prepare("SELECT sm.*, u.full_name as user_name FROM stock_movements sm LEFT JOIN users u ON sm.created_by = u.id WHERE sm.product_id = ? ORDER BY sm.created_at DESC LIMIT 50");
    $stmt->execute([$id]);
    $movements = $stmt->fetchAll();
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h5 class="mb-0"><i class="fas fa-box me-2"></i>Product Details</h5>
    <div>
        <a href="<?= BASE_URL ?>products.php?action=edit&id=<?= $product['id'] ?>" class="btn btn-warning btn-sm"><i class="fas fa-edit me-1"></i>Edit</a>
        <a href="<?= BASE_URL ?>products.php" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i>Back</a>
    </div>
</div>
<div class="row">
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header"><i class="fas fa-info-circle me-2"></i>Product Information</div>
            <div class="card-body">
                <table class="table table-borderless mb-0">
                    <tr><th width="35%">Code</th><td><?= sanitize($product['code']) ?></td></tr>
                    <tr><th>Name</th><td><?= sanitize($product['name']) ?></td></tr>
                    <tr><th>Category</th><td><?= sanitize($product['category_name'] ?? '-') ?></td></tr>
                    <tr><th>Description</th><td><?= sanitize($product['description'] ?: '-') ?></td></tr>
                    <tr><th>Unit</th><td><?= sanitize($product['unit']) ?></td></tr>
                    <tr><th>Location</th><td><?= sanitize($product['location'] ?: '-') ?></td></tr>
                    <tr><th>Status</th><td><span class="badge status-<?= $product['status'] ?>"><?= ucfirst($product['status']) ?></span></td></tr>
                    <tr><th>Created</th><td><?= formatDate($product['created_at']) ?></td></tr>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header"><i class="fas fa-money-bill me-2"></i>Pricing &amp; Stock</div>
            <div class="card-body">
                <table class="table table-borderless mb-0">
                    <tr><th width="35%">Purchase Price</th><td><?= formatCurrency($product['purchase_price']) ?></td></tr>
                    <tr><th>Selling Price</th><td><?= formatCurrency($product['selling_price']) ?></td></tr>
                    <tr><th>Current Stock</th><td><span class="fw-bold fs-5"><?= number_format($product['stock']) ?></span> <?= sanitize($product['unit']) ?></td></tr>
                    <tr><th>Min Stock</th><td><?= number_format($product['min_stock']) ?></td></tr>
                    <tr><th>Max Stock</th><td><?= number_format($product['max_stock']) ?></td></tr>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="card mt-3">
    <div class="card-header"><i class="fas fa-exchange-alt me-2"></i>Stock Movement History</div>
    <div class="card-body p-0">
        <?php if (empty($movements)): ?>
            <div class="empty-state"><i class="fas fa-inbox"></i><p>No stock movements recorded</p></div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead><tr><th>Date</th><th>Type</th><th>Quantity</th><th>Reference</th><th>Notes</th><th>By</th></tr></thead>
                <tbody>
                <?php foreach ($movements as $m): ?>
                <tr>
                    <td><?= formatDate($m['created_at']) ?></td>
                    <td>
                        <?php if ($m['type'] === 'in'): ?>
                            <span class="badge bg-success">IN</span>
                        <?php elseif ($m['type'] === 'out'): ?>
                            <span class="badge bg-danger">OUT</span>
                        <?php else: ?>
                            <span class="badge bg-primary">ADJUSTMENT</span>
                        <?php endif; ?>
                    </td>
                    <td><?= number_format($m['quantity']) ?></td>
                    <td><?= sanitize($m['reference_type'] ?? '-') ?></td>
                    <td><?= sanitize($m['notes'] ?? '-') ?></td>
                    <td><?= sanitize($m['user_name'] ?? '-') ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php
// --- CREATE / EDIT FORM ---
elseif ($action === 'create' || $action === 'edit'):
    $product = null;
    if ($action === 'edit' && $id > 0) {
        $stmt = $db->prepare("SELECT * FROM products WHERE id = ?");
        $stmt->execute([$id]);
        $product = $stmt->fetch();
        if (!$product) { setFlash('error', 'Product not found.'); header('Location: ' . BASE_URL . 'products.php'); exit; }
    }

    // Auto-generate code for new products
    $nextCode = '';
    if ($action === 'create') {
        $lastId = $db->query("SELECT COALESCE(MAX(id), 0) + 1 FROM products")->fetchColumn();
        $nextCode = 'PRD-' . str_pad($lastId, 4, '0', STR_PAD_LEFT);
    }

    $categories = $db->query("SELECT id, name FROM product_categories ORDER BY name")->fetchAll();
    $formAction = $action === 'create' ? 'store' : 'update';
    $formUrl = BASE_URL . 'products.php?action=' . $formAction . ($action === 'edit' ? '&id=' . $id : '');
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h5 class="mb-0"><i class="fas fa-<?= $action === 'create' ? 'plus' : 'edit' ?> me-2"></i><?= $action === 'create' ? 'Add New Product' : 'Edit Product' ?></h5>
    <a href="<?= BASE_URL ?>products.php" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i>Back</a>
</div>
<div class="card">
    <div class="card-body">
        <form method="POST" action="<?= $formUrl ?>">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Product Code <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" name="code" value="<?= sanitize($product['code'] ?? $nextCode) ?>" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Product Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" name="name" value="<?= sanitize($product['name'] ?? '') ?>" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Category</label>
                    <select class="form-select" name="category_id">
                        <option value="">-- Select Category --</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>" <?= ($product['category_id'] ?? '') == $cat['id'] ? 'selected' : '' ?>><?= sanitize($cat['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-12">
                    <label class="form-label">Description</label>
                    <textarea class="form-control" name="description" rows="2"><?= sanitize($product['description'] ?? '') ?></textarea>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Unit</label>
                    <select class="form-select" name="unit">
                        <?php $units = ['pcs','kg','ltr','box','set','unit']; foreach ($units as $u): ?>
                            <option value="<?= $u ?>" <?= ($product['unit'] ?? 'pcs') === $u ? 'selected' : '' ?>><?= $u ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Purchase Price</label>
                    <input type="number" class="form-control" name="purchase_price" step="0.01" min="0" value="<?= $product['purchase_price'] ?? 0 ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Selling Price</label>
                    <input type="number" class="form-control" name="selling_price" step="0.01" min="0" value="<?= $product['selling_price'] ?? 0 ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Status</label>
                    <select class="form-select" name="status">
                        <option value="active" <?= ($product['status'] ?? 'active') === 'active' ? 'selected' : '' ?>>Active</option>
                        <option value="inactive" <?= ($product['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                    </select>
                </div>
                <?php if ($action === 'create'): ?>
                <div class="col-md-3">
                    <label class="form-label">Initial Stock</label>
                    <input type="number" class="form-control" name="stock" min="0" value="0">
                </div>
                <?php endif; ?>
                <div class="col-md-3">
                    <label class="form-label">Min Stock</label>
                    <input type="number" class="form-control" name="min_stock" min="0" value="<?= $product['min_stock'] ?? 0 ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Max Stock</label>
                    <input type="number" class="form-control" name="max_stock" min="0" value="<?= $product['max_stock'] ?? 0 ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Location</label>
                    <input type="text" class="form-control" name="location" value="<?= sanitize($product['location'] ?? '') ?>">
                </div>
            </div>
            <div class="mt-4">
                <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i><?= $action === 'create' ? 'Create Product' : 'Update Product' ?></button>
                <a href="<?= BASE_URL ?>products.php" class="btn btn-secondary ms-2">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php
// --- LIST ---
else:
    $search = trim($_GET['search'] ?? '');
    $filterCategory = $_GET['category_id'] ?? '';
    $filterStatus = $_GET['status'] ?? '';
    $page = max(1, (int)($_GET['page'] ?? 1));
    $perPage = 20;
    $offset = ($page - 1) * $perPage;

    $where = [];
    $params = [];

    if ($search !== '') {
        $where[] = "(p.name LIKE ? OR p.code LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    if ($filterCategory !== '') {
        $where[] = "p.category_id = ?";
        $params[] = (int)$filterCategory;
    }
    if ($filterStatus !== '') {
        $where[] = "p.status = ?";
        $params[] = $filterStatus;
    }

    $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    // Count
    $stmt = $db->prepare("SELECT COUNT(*) FROM products p $whereSql");
    $stmt->execute($params);
    $totalRows = (int)$stmt->fetchColumn();
    $totalPages = max(1, ceil($totalRows / $perPage));

    // Fetch
    $stmt = $db->prepare("SELECT p.*, pc.name as category_name FROM products p LEFT JOIN product_categories pc ON p.category_id = pc.id $whereSql ORDER BY p.id DESC LIMIT $perPage OFFSET $offset");
    $stmt->execute($params);
    $products = $stmt->fetchAll();

    $categories = $db->query("SELECT id, name FROM product_categories ORDER BY name")->fetchAll();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h5 class="mb-0"><i class="fas fa-boxes-stacked me-2"></i>Products <span class="badge bg-secondary"><?= $totalRows ?></span></h5>
    <a href="<?= BASE_URL ?>products.php?action=create" class="btn btn-primary btn-sm"><i class="fas fa-plus me-1"></i>Add Product</a>
</div>

<!-- Filters -->
<div class="card mb-3">
    <div class="card-body py-3">
        <form method="GET" class="row g-2 align-items-end">
            <input type="hidden" name="action" value="list">
            <div class="col-md-4">
                <input type="text" class="form-control form-control-sm" name="search" placeholder="Search by name or code..." value="<?= sanitize($search) ?>">
            </div>
            <div class="col-md-3">
                <select class="form-select form-select-sm" name="category_id">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['id'] ?>" <?= $filterCategory == $cat['id'] ? 'selected' : '' ?>><?= sanitize($cat['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <select class="form-select form-select-sm" name="status">
                    <option value="">All Status</option>
                    <option value="active" <?= $filterStatus === 'active' ? 'selected' : '' ?>>Active</option>
                    <option value="inactive" <?= $filterStatus === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                </select>
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-search me-1"></i>Filter</button>
                <a href="<?= BASE_URL ?>products.php" class="btn btn-outline-secondary btn-sm">Reset</a>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body p-0">
        <?php if (empty($products)): ?>
            <div class="empty-state"><i class="fas fa-box-open"></i><p>No products found</p></div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Name</th>
                        <th>Category</th>
                        <th>Unit</th>
                        <th>Purchase Price</th>
                        <th>Selling Price</th>
                        <th>Stock</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($products as $p): ?>
                <tr>
                    <td><code><?= sanitize($p['code']) ?></code></td>
                    <td><a href="<?= BASE_URL ?>products.php?action=view&id=<?= $p['id'] ?>"><?= sanitize($p['name']) ?></a></td>
                    <td><?= sanitize($p['category_name'] ?? '-') ?></td>
                    <td><?= sanitize($p['unit']) ?></td>
                    <td><?= formatCurrency($p['purchase_price']) ?></td>
                    <td><?= formatCurrency($p['selling_price']) ?></td>
                    <td>
                        <?php if ($p['min_stock'] > 0 && $p['stock'] <= $p['min_stock']): ?>
                            <span class="text-danger fw-bold"><?= number_format($p['stock']) ?></span>
                        <?php else: ?>
                            <?= number_format($p['stock']) ?>
                        <?php endif; ?>
                    </td>
                    <td><span class="badge status-<?= $p['status'] ?>"><?= ucfirst($p['status']) ?></span></td>
                    <td>
                        <a href="<?= BASE_URL ?>products.php?action=view&id=<?= $p['id'] ?>" class="btn btn-info btn-sm" title="View"><i class="fas fa-eye"></i></a>
                        <a href="<?= BASE_URL ?>products.php?action=edit&id=<?= $p['id'] ?>" class="btn btn-warning btn-sm" title="Edit"><i class="fas fa-edit"></i></a>
                        <form method="POST" action="<?= BASE_URL ?>products.php?action=delete&id=<?= $p['id'] ?>" class="d-inline" onsubmit="return confirm('Delete this product?')">
                            <button type="submit" class="btn btn-danger btn-sm" title="Delete"><i class="fas fa-trash"></i></button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php if ($totalPages > 1): ?>
<nav class="mt-3">
    <ul class="pagination pagination-sm justify-content-center">
        <?php
        $qs = $_GET;
        for ($i = 1; $i <= $totalPages; $i++):
            $qs['page'] = $i;
            $qstr = http_build_query($qs);
        ?>
            <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                <a class="page-link" href="<?= BASE_URL ?>products.php?<?= $qstr ?>"><?= $i ?></a>
            </li>
        <?php endfor; ?>
    </ul>
</nav>
<?php endif; ?>

<?php endif; ?>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
