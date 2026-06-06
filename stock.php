<?php $pageTitle = 'Stock Movement'; require_once __DIR__ . '/includes/header.php'; ?>
<?php
$db = getDB();
$action = $_GET['action'] ?? 'list';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'store') {
    $productId = (int)$_POST['product_id'];
    $type = $_POST['type'];
    $qty = (int)$_POST['quantity'];
    $notes = $_POST['notes'] ?? '';

    $stmt = $db->prepare("INSERT INTO stock_movements (product_id, type, quantity, notes, created_by, created_at) VALUES (?,?,?,?,?,NOW())");
    $stmt->execute([$productId, $type, $qty, $notes, $_SESSION['user_id']]);

    if ($type === 'in') {
        $db->prepare("UPDATE products SET stock = stock + ? WHERE id = ?")->execute([$qty, $productId]);
    } elseif ($type === 'out') {
        $db->prepare("UPDATE products SET stock = stock - ? WHERE id = ?")->execute([$qty, $productId]);
    } else {
        $db->prepare("UPDATE products SET stock = ? WHERE id = ?")->execute([$qty, $productId]);
    }

    setFlash('success', 'Stock movement recorded successfully');
    header('Location: ' . BASE_URL . 'stock.php'); exit;
}

if ($action === 'create'):
    $products = $db->query("SELECT id, code, name, stock FROM products WHERE status='active' ORDER BY name")->fetchAll();
?>
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fas fa-plus me-2"></i>New Stock Movement</span>
        <a href="<?= BASE_URL ?>stock.php" class="btn btn-sm btn-outline-secondary">Back</a>
    </div>
    <div class="card-body">
        <form method="POST" action="<?= BASE_URL ?>stock.php?action=store">
            <div class="row g-3">
                <div class="col-md-5">
                    <label class="form-label">Product *</label>
                    <select name="product_id" class="form-select" required>
                        <option value="">Select Product</option>
                        <?php foreach ($products as $p): ?>
                        <option value="<?= $p['id'] ?>"><?= sanitize($p['code'].' - '.$p['name']) ?> (Stock: <?= $p['stock'] ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Type *</label>
                    <select name="type" class="form-select" required>
                        <option value="in">Stock In</option>
                        <option value="out">Stock Out</option>
                        <option value="adjustment">Adjustment</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Quantity *</label>
                    <input type="number" name="quantity" class="form-control" min="0" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Notes</label>
                    <input type="text" name="notes" class="form-control" placeholder="Optional notes">
                </div>
            </div>
            <div class="mt-3">
                <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>Record Movement</button>
            </div>
        </form>
    </div>
</div>

<?php else: // LIST
    $where = []; $params = [];
    if (!empty($_GET['product_id'])) { $where[] = "sm.product_id = ?"; $params[] = $_GET['product_id']; }
    if (!empty($_GET['type'])) { $where[] = "sm.type = ?"; $params[] = $_GET['type']; }
    if (!empty($_GET['date_from'])) { $where[] = "DATE(sm.created_at) >= ?"; $params[] = $_GET['date_from']; }
    if (!empty($_GET['date_to'])) { $where[] = "DATE(sm.created_at) <= ?"; $params[] = $_GET['date_to']; }
    $whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    $total = $db->prepare("SELECT COUNT(*) FROM stock_movements sm $whereSQL"); $total->execute($params); $total = $total->fetchColumn();
    $page = max(1, (int)($_GET['page'] ?? 1)); $perPage = 20; $pages = max(1, ceil($total/$perPage)); $offset = ($page-1)*$perPage;

    $stmt = $db->prepare("SELECT sm.*, p.name as product_name, p.code as product_code, u.full_name as user_name FROM stock_movements sm LEFT JOIN products p ON sm.product_id=p.id LEFT JOIN users u ON sm.created_by=u.id $whereSQL ORDER BY sm.created_at DESC LIMIT $perPage OFFSET $offset");
    $stmt->execute($params); $movements = $stmt->fetchAll();

    $allProducts = $db->query("SELECT id, code, name FROM products WHERE status='active' ORDER BY name")->fetchAll();
?>
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fas fa-warehouse me-2"></i>Stock Movements (<?= $total ?>)</span>
        <a href="<?= BASE_URL ?>stock.php?action=create" class="btn btn-sm btn-primary"><i class="fas fa-plus me-1"></i>New Movement</a>
    </div>
    <div class="card-body">
        <form class="row g-2 mb-3">
            <div class="col-md-3">
                <select name="product_id" class="form-select form-select-sm">
                    <option value="">All Products</option>
                    <?php foreach ($allProducts as $p): ?>
                    <option value="<?= $p['id'] ?>" <?= ($_GET['product_id'] ?? '') == $p['id'] ? 'selected' : '' ?>><?= sanitize($p['code'].' - '.$p['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <select name="type" class="form-select form-select-sm">
                    <option value="">All Types</option>
                    <option value="in" <?= ($_GET['type'] ?? '') === 'in' ? 'selected' : '' ?>>Stock In</option>
                    <option value="out" <?= ($_GET['type'] ?? '') === 'out' ? 'selected' : '' ?>>Stock Out</option>
                    <option value="adjustment" <?= ($_GET['type'] ?? '') === 'adjustment' ? 'selected' : '' ?>>Adjustment</option>
                </select>
            </div>
            <div class="col-md-2"><input type="date" name="date_from" class="form-control form-control-sm" value="<?= $_GET['date_from'] ?? '' ?>" placeholder="From"></div>
            <div class="col-md-2"><input type="date" name="date_to" class="form-control form-control-sm" value="<?= $_GET['date_to'] ?? '' ?>" placeholder="To"></div>
            <div class="col-md-1"><button class="btn btn-sm btn-primary w-100">Filter</button></div>
        </form>

        <?php if (empty($movements)): ?>
            <div class="empty-state"><i class="fas fa-inbox"></i><p>No stock movements found</p></div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead><tr><th>Date</th><th>Product</th><th>Type</th><th>Quantity</th><th>Notes</th><th>By</th></tr></thead>
                <tbody>
                <?php foreach ($movements as $m): ?>
                <tr>
                    <td><?= date('d M Y H:i', strtotime($m['created_at'])) ?></td>
                    <td><?= sanitize($m['product_code'].' - '.$m['product_name']) ?></td>
                    <td>
                        <?php if ($m['type'] === 'in'): ?><span class="badge bg-success">IN</span>
                        <?php elseif ($m['type'] === 'out'): ?><span class="badge bg-danger">OUT</span>
                        <?php else: ?><span class="badge bg-primary">ADJ</span><?php endif; ?>
                    </td>
                    <td><strong><?= $m['quantity'] ?></strong></td>
                    <td><?= sanitize($m['notes'] ?: '-') ?></td>
                    <td><?= sanitize($m['user_name'] ?? '-') ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php if ($pages > 1): ?>
        <nav><ul class="pagination pagination-sm justify-content-center">
            <?php for ($i=1; $i<=$pages; $i++): ?>
            <li class="page-item <?= $i===$page?'active':'' ?>"><a class="page-link" href="?page=<?= $i ?>&product_id=<?= urlencode($_GET['product_id']??'') ?>&type=<?= urlencode($_GET['type']??'') ?>&date_from=<?= urlencode($_GET['date_from']??'') ?>&date_to=<?= urlencode($_GET['date_to']??'') ?>"><?= $i ?></a></li>
            <?php endfor; ?>
        </ul></nav>
        <?php endif; endif; ?>
    </div>
</div>
<?php endif; ?>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
