<?php $pageTitle = 'Suppliers'; require_once __DIR__ . '/includes/header.php'; ?>
<?php
$db = getDB();
$action = $_GET['action'] ?? 'list';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'store') {
        // Auto-generate code
        $stmt = $db->query("SELECT MAX(id) as max_id FROM suppliers");
        $maxId = $stmt->fetch()['max_id'] ?? 0;
        $code = 'SUP-' . str_pad($maxId + 1, 4, '0', STR_PAD_LEFT);

        $stmt = $db->prepare("INSERT INTO suppliers (code, name, company, email, phone, address, city, country, tax_id, status, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $code,
            $_POST['name'],
            $_POST['company'],
            $_POST['email'],
            $_POST['phone'],
            $_POST['address'],
            $_POST['city'],
            $_POST['country'],
            $_POST['tax_id'],
            $_POST['status'],
            $_POST['notes']
        ]);
        setFlash('success', 'Supplier created successfully with code ' . $code);
        header('Location: ' . BASE_URL . 'suppliers.php');
        exit;
    }

    if ($action === 'update' && $id > 0) {
        $stmt = $db->prepare("UPDATE suppliers SET name=?, company=?, email=?, phone=?, address=?, city=?, country=?, tax_id=?, status=?, notes=? WHERE id=?");
        $stmt->execute([
            $_POST['name'],
            $_POST['company'],
            $_POST['email'],
            $_POST['phone'],
            $_POST['address'],
            $_POST['city'],
            $_POST['country'],
            $_POST['tax_id'],
            $_POST['status'],
            $_POST['notes'],
            $id
        ]);
        setFlash('success', 'Supplier updated successfully.');
        header('Location: ' . BASE_URL . 'suppliers.php?action=view&id=' . $id);
        exit;
    }

    if ($action === 'delete' && $id > 0) {
        // Check for purchase orders
        $stmt = $db->prepare("SELECT COUNT(*) as cnt FROM purchase_orders WHERE supplier_id = ?");
        $stmt->execute([$id]);
        if ($stmt->fetch()['cnt'] > 0) {
            setFlash('error', 'Cannot delete supplier: has existing purchase orders.');
        } else {
            $stmt = $db->prepare("DELETE FROM suppliers WHERE id = ?");
            $stmt->execute([$id]);
            setFlash('success', 'Supplier deleted successfully.');
        }
        header('Location: ' . BASE_URL . 'suppliers.php');
        exit;
    }
}

// === LIST ===
if ($action === 'list'):
    $search = $_GET['search'] ?? '';
    $statusFilter = $_GET['status'] ?? '';
    $page = max(1, (int)($_GET['page'] ?? 1));
    $perPage = 20;
    $offset = ($page - 1) * $perPage;

    $where = [];
    $params = [];
    if ($search !== '') {
        $where[] = "(name LIKE ? OR company LIKE ? OR code LIKE ? OR email LIKE ? OR phone LIKE ?)";
        $s = "%$search%";
        $params = array_merge($params, [$s, $s, $s, $s, $s]);
    }
    if ($statusFilter !== '') {
        $where[] = "status = ?";
        $params[] = $statusFilter;
    }
    $whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    $countStmt = $db->prepare("SELECT COUNT(*) as total FROM suppliers $whereSQL");
    $countStmt->execute($params);
    $total = $countStmt->fetch()['total'];
    $totalPages = max(1, ceil($total / $perPage));

    $stmt = $db->prepare("SELECT * FROM suppliers $whereSQL ORDER BY id DESC LIMIT $perPage OFFSET $offset");
    $stmt->execute($params);
    $suppliers = $stmt->fetchAll();
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h5 class="mb-0">Suppliers</h5>
        <small class="text-muted"><?= $total ?> total suppliers</small>
    </div>
    <a href="<?= BASE_URL ?>suppliers.php?action=create" class="btn btn-primary">
        <i class="fas fa-plus me-1"></i> Add Supplier
    </a>
</div>

<div class="card">
    <div class="card-header">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-5">
                <input type="text" name="search" class="form-control" placeholder="Search by name, company, code, email, phone..." value="<?= sanitize($search) ?>">
            </div>
            <div class="col-md-3">
                <select name="status" class="form-select">
                    <option value="">All Status</option>
                    <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Active</option>
                    <option value="inactive" <?= $statusFilter === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100"><i class="fas fa-search me-1"></i> Filter</button>
            </div>
            <?php if ($search || $statusFilter): ?>
            <div class="col-md-2">
                <a href="<?= BASE_URL ?>suppliers.php" class="btn btn-outline-secondary w-100"><i class="fas fa-times me-1"></i> Clear</a>
            </div>
            <?php endif; ?>
        </form>
    </div>
    <div class="card-body p-0">
        <?php if (empty($suppliers)): ?>
        <div class="empty-state">
            <i class="fas fa-truck"></i>
            <p>No suppliers found.</p>
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Name</th>
                        <th>Company</th>
                        <th>Phone</th>
                        <th>Email</th>
                        <th>Balance</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($suppliers as $s): ?>
                    <tr>
                        <td><strong><?= sanitize($s['code']) ?></strong></td>
                        <td><?= sanitize($s['name']) ?></td>
                        <td><?= sanitize($s['company']) ?></td>
                        <td><?= sanitize($s['phone']) ?></td>
                        <td><?= sanitize($s['email']) ?></td>
                        <td><?= formatCurrency($s['balance']) ?></td>
                        <td><span class="badge status-<?= $s['status'] ?>"><?= ucfirst($s['status']) ?></span></td>
                        <td>
                            <a href="<?= BASE_URL ?>suppliers.php?action=view&id=<?= $s['id'] ?>" class="btn btn-sm btn-outline-primary" title="View"><i class="fas fa-eye"></i></a>
                            <a href="<?= BASE_URL ?>suppliers.php?action=edit&id=<?= $s['id'] ?>" class="btn btn-sm btn-outline-warning" title="Edit"><i class="fas fa-edit"></i></a>
                            <button type="button" class="btn btn-sm btn-outline-danger" title="Delete" data-bs-toggle="modal" data-bs-target="#deleteModal<?= $s['id'] ?>"><i class="fas fa-trash"></i></button>
                            <!-- Delete Modal -->
                            <div class="modal fade" id="deleteModal<?= $s['id'] ?>" tabindex="-1">
                                <div class="modal-dialog modal-sm">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h6 class="modal-title">Confirm Delete</h6>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body">
                                            Delete supplier <strong><?= sanitize($s['name']) ?></strong>?
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                            <form method="POST" action="<?= BASE_URL ?>suppliers.php?action=delete&id=<?= $s['id'] ?>" class="d-inline">
                                                <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
    <?php if ($totalPages > 1): ?>
    <div class="card-footer d-flex justify-content-between align-items-center">
        <small class="text-muted">Showing <?= $offset + 1 ?>-<?= min($offset + $perPage, $total) ?> of <?= $total ?></small>
        <nav>
            <ul class="pagination pagination-sm mb-0">
                <?php
                $queryParams = $_GET;
                unset($queryParams['page']);
                $qs = http_build_query($queryParams);
                $qs = $qs ? $qs . '&' : '';
                ?>
                <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                    <a class="page-link" href="<?= BASE_URL ?>suppliers.php?<?= $qs ?>page=<?= $page - 1 ?>">Prev</a>
                </li>
                <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                    <a class="page-link" href="<?= BASE_URL ?>suppliers.php?<?= $qs ?>page=<?= $i ?>"><?= $i ?></a>
                </li>
                <?php endfor; ?>
                <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                    <a class="page-link" href="<?= BASE_URL ?>suppliers.php?<?= $qs ?>page=<?= $page + 1 ?>">Next</a>
                </li>
            </ul>
        </nav>
    </div>
    <?php endif; ?>
</div>

<?php
// === CREATE ===
elseif ($action === 'create'):
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h5 class="mb-0">Add New Supplier</h5>
        <small class="text-muted">Code will be auto-generated</small>
    </div>
    <a href="<?= BASE_URL ?>suppliers.php" class="btn btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i> Back</a>
</div>

<div class="card">
    <div class="card-body">
        <form method="POST" action="<?= BASE_URL ?>suppliers.php?action=store">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Name <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Company</label>
                    <input type="text" name="company" class="form-control">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-control">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Phone</label>
                    <input type="text" name="phone" class="form-control">
                </div>
                <div class="col-md-12 mb-3">
                    <label class="form-label">Address</label>
                    <textarea name="address" class="form-control" rows="2"></textarea>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">City</label>
                    <input type="text" name="city" class="form-control">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Country</label>
                    <input type="text" name="country" class="form-control" value="Indonesia">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Tax ID</label>
                    <input type="text" name="tax_id" class="form-control">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
                <div class="col-md-12 mb-3">
                    <label class="form-label">Notes</label>
                    <textarea name="notes" class="form-control" rows="2"></textarea>
                </div>
            </div>
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i> Save Supplier</button>
                <a href="<?= BASE_URL ?>suppliers.php" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php
// === EDIT ===
elseif ($action === 'edit' && $id > 0):
    $stmt = $db->prepare("SELECT * FROM suppliers WHERE id = ?");
    $stmt->execute([$id]);
    $s = $stmt->fetch();
    if (!$s) { setFlash('error', 'Supplier not found.'); header('Location: ' . BASE_URL . 'suppliers.php'); exit; }
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h5 class="mb-0">Edit Supplier</h5>
        <small class="text-muted"><?= sanitize($s['code']) ?> - <?= sanitize($s['name']) ?></small>
    </div>
    <a href="<?= BASE_URL ?>suppliers.php" class="btn btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i> Back</a>
</div>

<div class="card">
    <div class="card-body">
        <form method="POST" action="<?= BASE_URL ?>suppliers.php?action=update&id=<?= $s['id'] ?>">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Code</label>
                    <input type="text" class="form-control" value="<?= sanitize($s['code']) ?>" disabled>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Name <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control" value="<?= sanitize($s['name']) ?>" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Company</label>
                    <input type="text" name="company" class="form-control" value="<?= sanitize($s['company']) ?>">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-control" value="<?= sanitize($s['email']) ?>">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Phone</label>
                    <input type="text" name="phone" class="form-control" value="<?= sanitize($s['phone']) ?>">
                </div>
                <div class="col-md-12 mb-3">
                    <label class="form-label">Address</label>
                    <textarea name="address" class="form-control" rows="2"><?= sanitize($s['address']) ?></textarea>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">City</label>
                    <input type="text" name="city" class="form-control" value="<?= sanitize($s['city']) ?>">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Country</label>
                    <input type="text" name="country" class="form-control" value="<?= sanitize($s['country']) ?>">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Tax ID</label>
                    <input type="text" name="tax_id" class="form-control" value="<?= sanitize($s['tax_id']) ?>">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="active" <?= $s['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                        <option value="inactive" <?= $s['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                    </select>
                </div>
                <div class="col-md-12 mb-3">
                    <label class="form-label">Notes</label>
                    <textarea name="notes" class="form-control" rows="2"><?= sanitize($s['notes']) ?></textarea>
                </div>
            </div>
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i> Update Supplier</button>
                <a href="<?= BASE_URL ?>suppliers.php?action=view&id=<?= $s['id'] ?>" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php
// === VIEW ===
elseif ($action === 'view' && $id > 0):
    $stmt = $db->prepare("SELECT * FROM suppliers WHERE id = ?");
    $stmt->execute([$id]);
    $s = $stmt->fetch();
    if (!$s) { setFlash('error', 'Supplier not found.'); header('Location: ' . BASE_URL . 'suppliers.php'); exit; }

    // Recent purchase orders
    $stmt = $db->prepare("SELECT * FROM purchase_orders WHERE supplier_id = ? ORDER BY id DESC LIMIT 10");
    $stmt->execute([$id]);
    $orders = $stmt->fetchAll();
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h5 class="mb-0"><?= sanitize($s['name']) ?></h5>
        <small class="text-muted"><?= sanitize($s['code']) ?></small>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= BASE_URL ?>suppliers.php?action=edit&id=<?= $s['id'] ?>" class="btn btn-warning"><i class="fas fa-edit me-1"></i> Edit</a>
        <a href="<?= BASE_URL ?>suppliers.php" class="btn btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i> Back</a>
    </div>
</div>

<div class="row">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header"><i class="fas fa-truck me-2"></i>Supplier Details</div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label text-muted">Code</label>
                        <div class="fw-bold"><?= sanitize($s['code']) ?></div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label text-muted">Status</label>
                        <div><span class="badge status-<?= $s['status'] ?>"><?= ucfirst($s['status']) ?></span></div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label text-muted">Name</label>
                        <div><?= sanitize($s['name']) ?></div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label text-muted">Company</label>
                        <div><?= sanitize($s['company']) ?: '-' ?></div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label text-muted">Email</label>
                        <div><?= sanitize($s['email']) ?: '-' ?></div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label text-muted">Phone</label>
                        <div><?= sanitize($s['phone']) ?: '-' ?></div>
                    </div>
                    <div class="col-md-12 mb-3">
                        <label class="form-label text-muted">Address</label>
                        <div><?= nl2br(sanitize($s['address'])) ?: '-' ?></div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label text-muted">City</label>
                        <div><?= sanitize($s['city']) ?: '-' ?></div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label text-muted">Country</label>
                        <div><?= sanitize($s['country']) ?: '-' ?></div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label text-muted">Tax ID</label>
                        <div><?= sanitize($s['tax_id']) ?: '-' ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card">
            <div class="card-header"><i class="fas fa-chart-line me-2"></i>Financial</div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label text-muted">Balance</label>
                    <div class="fs-5 fw-bold text-primary"><?= formatCurrency($s['balance']) ?></div>
                </div>
                <div class="mb-3">
                    <label class="form-label text-muted">Notes</label>
                    <div><?= nl2br(sanitize($s['notes'])) ?: '-' ?></div>
                </div>
                <hr>
                <div class="mb-2">
                    <small class="text-muted">Created: <?= formatDate($s['created_at']) ?></small>
                </div>
                <div>
                    <small class="text-muted">Updated: <?= formatDate($s['updated_at']) ?></small>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card mt-2">
    <div class="card-header"><i class="fas fa-clipboard-list me-2"></i>Recent Purchase Orders</div>
    <div class="card-body p-0">
        <?php if (empty($orders)): ?>
        <div class="empty-state py-4">
            <i class="fas fa-clipboard-list"></i>
            <p>No purchase orders yet.</p>
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>Order #</th>
                        <th>Date</th>
                        <th>Expected Date</th>
                        <th>Total</th>
                        <th>Status</th>
                        <th>Payment</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orders as $o): ?>
                    <tr>
                        <td><a href="<?= BASE_URL ?>purchase_orders.php?action=view&id=<?= $o['id'] ?>"><?= sanitize($o['order_number']) ?></a></td>
                        <td><?= formatDate($o['order_date']) ?></td>
                        <td><?= $o['expected_date'] ? formatDate($o['expected_date']) : '-' ?></td>
                        <td><?= formatCurrency($o['total']) ?></td>
                        <td><span class="badge status-<?= $o['status'] ?>"><?= ucfirst($o['status']) ?></span></td>
                        <td><span class="badge status-<?= $o['payment_status'] ?>"><?= ucfirst($o['payment_status']) ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php else: ?>
<div class="alert alert-danger">Invalid action.</div>
<a href="<?= BASE_URL ?>suppliers.php" class="btn btn-outline-secondary">Back to Suppliers</a>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
