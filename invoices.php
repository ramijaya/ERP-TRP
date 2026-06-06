<?php $pageTitle = 'Invoices'; require_once __DIR__ . '/includes/header.php'; ?>
<?php
$db = getDB();
$action = $_GET['action'] ?? 'list';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'store') {
        // Auto-generate invoice number: INV-YYYYMM-0001
        $prefix = 'INV-' . date('Ym') . '-';
        $stmt = $db->prepare("SELECT COUNT(*) as cnt FROM invoices WHERE invoice_number LIKE ?");
        $stmt->execute([$prefix . '%']);
        $count = $stmt->fetch()['cnt'];
        $invoiceNumber = $prefix . str_pad($count + 1, 4, '0', STR_PAD_LEFT);

        $type = $_POST['type'];
        $customerId = $type === 'sales' ? ($_POST['customer_id'] ?: null) : null;
        $supplierId = $type === 'purchase' ? ($_POST['supplier_id'] ?: null) : null;
        $subtotal = (float)($_POST['subtotal'] ?? 0);
        $taxAmount = (float)($_POST['tax_amount'] ?? 0);
        $discountAmount = (float)($_POST['discount_amount'] ?? 0);
        $total = $subtotal + $taxAmount - $discountAmount;

        $stmt = $db->prepare("INSERT INTO invoices (invoice_number, type, reference_id, customer_id, supplier_id, invoice_date, due_date, subtotal, tax_amount, discount_amount, total, paid_amount, status, notes, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 'draft', ?, ?)");
        $stmt->execute([
            $invoiceNumber,
            $type,
            $_POST['reference_id'] ?: null,
            $customerId,
            $supplierId,
            $_POST['invoice_date'],
            $_POST['due_date'],
            $subtotal,
            $taxAmount,
            $discountAmount,
            $total,
            $_POST['notes'],
            $currentUser['id'] ?? 1
        ]);
        setFlash('success', 'Invoice created successfully: ' . $invoiceNumber);
        header('Location: ' . BASE_URL . 'invoices.php');
        exit;
    }

    if ($action === 'update' && $id > 0) {
        // Only allow editing draft invoices
        $stmt = $db->prepare("SELECT status FROM invoices WHERE id = ?");
        $stmt->execute([$id]);
        $inv = $stmt->fetch();
        if (!$inv || $inv['status'] !== 'draft') {
            setFlash('error', 'Only draft invoices can be edited.');
            header('Location: ' . BASE_URL . 'invoices.php');
            exit;
        }

        $type = $_POST['type'];
        $customerId = $type === 'sales' ? ($_POST['customer_id'] ?: null) : null;
        $supplierId = $type === 'purchase' ? ($_POST['supplier_id'] ?: null) : null;
        $subtotal = (float)($_POST['subtotal'] ?? 0);
        $taxAmount = (float)($_POST['tax_amount'] ?? 0);
        $discountAmount = (float)($_POST['discount_amount'] ?? 0);
        $total = $subtotal + $taxAmount - $discountAmount;

        $stmt = $db->prepare("UPDATE invoices SET type=?, reference_id=?, customer_id=?, supplier_id=?, invoice_date=?, due_date=?, subtotal=?, tax_amount=?, discount_amount=?, total=?, notes=?, updated_at=NOW() WHERE id=?");
        $stmt->execute([
            $type,
            $_POST['reference_id'] ?: null,
            $customerId,
            $supplierId,
            $_POST['invoice_date'],
            $_POST['due_date'],
            $subtotal,
            $taxAmount,
            $discountAmount,
            $total,
            $_POST['notes'],
            $id
        ]);
        setFlash('success', 'Invoice updated successfully.');
        header('Location: ' . BASE_URL . 'invoices.php?action=view&id=' . $id);
        exit;
    }

    if ($action === 'delete' && $id > 0) {
        $stmt = $db->prepare("SELECT status FROM invoices WHERE id = ?");
        $stmt->execute([$id]);
        $inv = $stmt->fetch();
        if (!$inv || $inv['status'] !== 'draft') {
            setFlash('error', 'Only draft invoices can be deleted.');
        } else {
            $db->prepare("DELETE FROM payments WHERE invoice_id = ?")->execute([$id]);
            $db->prepare("DELETE FROM invoices WHERE id = ?")->execute([$id]);
            setFlash('success', 'Invoice deleted successfully.');
        }
        header('Location: ' . BASE_URL . 'invoices.php');
        exit;
    }

    if ($action === 'mark_sent' && $id > 0) {
        $stmt = $db->prepare("UPDATE invoices SET status = 'sent', updated_at = NOW() WHERE id = ? AND status = 'draft'");
        $stmt->execute([$id]);
        if ($stmt->rowCount() > 0) {
            setFlash('success', 'Invoice marked as sent.');
        } else {
            setFlash('error', 'Only draft invoices can be marked as sent.');
        }
        header('Location: ' . BASE_URL . 'invoices.php?action=view&id=' . $id);
        exit;
    }
}

// === LIST ===
if ($action === 'list'):
    $search = $_GET['search'] ?? '';
    $typeFilter = $_GET['type'] ?? '';
    $statusFilter = $_GET['status'] ?? '';
    $page = max(1, (int)($_GET['page'] ?? 1));
    $perPage = 20;
    $offset = ($page - 1) * $perPage;

    $where = [];
    $params = [];
    if ($search !== '') {
        $where[] = "i.invoice_number LIKE ?";
        $params[] = "%$search%";
    }
    if ($typeFilter !== '') {
        $where[] = "i.type = ?";
        $params[] = $typeFilter;
    }
    if ($statusFilter !== '') {
        $where[] = "i.status = ?";
        $params[] = $statusFilter;
    }
    $whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    $countStmt = $db->prepare("SELECT COUNT(*) as total FROM invoices i $whereSQL");
    $countStmt->execute($params);
    $total = $countStmt->fetch()['total'];
    $totalPages = max(1, ceil($total / $perPage));

    $stmt = $db->prepare("SELECT i.*, c.name as customer_name, s.name as supplier_name FROM invoices i LEFT JOIN customers c ON i.customer_id = c.id LEFT JOIN suppliers s ON i.supplier_id = s.id $whereSQL ORDER BY i.id DESC LIMIT $perPage OFFSET $offset");
    $stmt->execute($params);
    $invoices = $stmt->fetchAll();
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h5 class="mb-0">Invoices</h5>
        <small class="text-muted"><?= $total ?> total invoices</small>
    </div>
    <a href="<?= BASE_URL ?>invoices.php?action=create" class="btn btn-primary">
        <i class="fas fa-plus me-1"></i> New Invoice
    </a>
</div>

<div class="card">
    <div class="card-header">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-4">
                <input type="text" name="search" class="form-control" placeholder="Search by invoice number..." value="<?= sanitize($search) ?>">
            </div>
            <div class="col-md-2">
                <select name="type" class="form-select">
                    <option value="">All Types</option>
                    <option value="sales" <?= $typeFilter === 'sales' ? 'selected' : '' ?>>Sales</option>
                    <option value="purchase" <?= $typeFilter === 'purchase' ? 'selected' : '' ?>>Purchase</option>
                </select>
            </div>
            <div class="col-md-2">
                <select name="status" class="form-select">
                    <option value="">All Status</option>
                    <option value="draft" <?= $statusFilter === 'draft' ? 'selected' : '' ?>>Draft</option>
                    <option value="sent" <?= $statusFilter === 'sent' ? 'selected' : '' ?>>Sent</option>
                    <option value="paid" <?= $statusFilter === 'paid' ? 'selected' : '' ?>>Paid</option>
                    <option value="overdue" <?= $statusFilter === 'overdue' ? 'selected' : '' ?>>Overdue</option>
                    <option value="cancelled" <?= $statusFilter === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100"><i class="fas fa-search me-1"></i> Filter</button>
            </div>
            <?php if ($search || $typeFilter || $statusFilter): ?>
            <div class="col-md-2">
                <a href="<?= BASE_URL ?>invoices.php" class="btn btn-outline-secondary w-100"><i class="fas fa-times me-1"></i> Clear</a>
            </div>
            <?php endif; ?>
        </form>
    </div>
    <div class="card-body p-0">
        <?php if (empty($invoices)): ?>
        <div class="empty-state">
            <i class="fas fa-file-invoice"></i>
            <p>No invoices found.</p>
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>Invoice #</th>
                        <th>Type</th>
                        <th>Customer/Supplier</th>
                        <th>Date</th>
                        <th>Due Date</th>
                        <th>Total</th>
                        <th>Paid</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($invoices as $inv):
                        // Check for overdue
                        $displayStatus = $inv['status'];
                        if ($inv['status'] === 'sent' && $inv['due_date'] < date('Y-m-d') && $inv['paid_amount'] < $inv['total']) {
                            $displayStatus = 'overdue';
                        }
                    ?>
                    <tr>
                        <td><strong><?= sanitize($inv['invoice_number']) ?></strong></td>
                        <td><span class="badge bg-<?= $inv['type'] === 'sales' ? 'info' : 'secondary' ?>"><?= ucfirst($inv['type']) ?></span></td>
                        <td><?= $inv['type'] === 'sales' ? sanitize($inv['customer_name'] ?? '-') : sanitize($inv['supplier_name'] ?? '-') ?></td>
                        <td><?= formatDate($inv['invoice_date']) ?></td>
                        <td><?= $inv['due_date'] ? formatDate($inv['due_date']) : '-' ?></td>
                        <td><?= formatCurrency($inv['total']) ?></td>
                        <td><?= formatCurrency($inv['paid_amount']) ?></td>
                        <td><span class="badge status-<?= $displayStatus ?>"><?= ucfirst($displayStatus) ?></span></td>
                        <td>
                            <a href="<?= BASE_URL ?>invoices.php?action=view&id=<?= $inv['id'] ?>" class="btn btn-sm btn-outline-primary" title="View"><i class="fas fa-eye"></i></a>
                            <?php if ($inv['status'] === 'draft'): ?>
                            <a href="<?= BASE_URL ?>invoices.php?action=edit&id=<?= $inv['id'] ?>" class="btn btn-sm btn-outline-warning" title="Edit"><i class="fas fa-edit"></i></a>
                            <button type="button" class="btn btn-sm btn-outline-danger" title="Delete" data-bs-toggle="modal" data-bs-target="#deleteModal<?= $inv['id'] ?>"><i class="fas fa-trash"></i></button>
                            <!-- Delete Modal -->
                            <div class="modal fade" id="deleteModal<?= $inv['id'] ?>" tabindex="-1">
                                <div class="modal-dialog modal-sm">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h6 class="modal-title">Confirm Delete</h6>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body">
                                            Delete invoice <strong><?= sanitize($inv['invoice_number']) ?></strong>?
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                            <form method="POST" action="<?= BASE_URL ?>invoices.php?action=delete&id=<?= $inv['id'] ?>" class="d-inline">
                                                <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
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
                    <a class="page-link" href="<?= BASE_URL ?>invoices.php?<?= $qs ?>page=<?= $page - 1 ?>">Prev</a>
                </li>
                <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                    <a class="page-link" href="<?= BASE_URL ?>invoices.php?<?= $qs ?>page=<?= $i ?>"><?= $i ?></a>
                </li>
                <?php endfor; ?>
                <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                    <a class="page-link" href="<?= BASE_URL ?>invoices.php?<?= $qs ?>page=<?= $page + 1 ?>">Next</a>
                </li>
            </ul>
        </nav>
    </div>
    <?php endif; ?>
</div>

<?php
// === CREATE ===
elseif ($action === 'create'):
    $customers = $db->query("SELECT id, name FROM customers WHERE status = 'active' ORDER BY name")->fetchAll();
    $suppliers = $db->query("SELECT id, name FROM suppliers WHERE status = 'active' ORDER BY name")->fetchAll();
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h5 class="mb-0">Create New Invoice</h5>
        <small class="text-muted">Invoice number will be auto-generated</small>
    </div>
    <a href="<?= BASE_URL ?>invoices.php" class="btn btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i> Back</a>
</div>

<div class="card">
    <div class="card-body">
        <form method="POST" action="<?= BASE_URL ?>invoices.php?action=store">
            <div class="row">
                <div class="col-md-4 mb-3">
                    <label class="form-label">Type <span class="text-danger">*</span></label>
                    <select name="type" id="invoiceType" class="form-select" required onchange="toggleParty()">
                        <option value="sales">Sales</option>
                        <option value="purchase">Purchase</option>
                    </select>
                </div>
                <div class="col-md-4 mb-3" id="customerField">
                    <label class="form-label">Customer <span class="text-danger">*</span></label>
                    <select name="customer_id" id="customerId" class="form-select">
                        <option value="">-- Select Customer --</option>
                        <?php foreach ($customers as $cust): ?>
                        <option value="<?= $cust['id'] ?>"><?= sanitize($cust['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4 mb-3" id="supplierField" style="display:none;">
                    <label class="form-label">Supplier <span class="text-danger">*</span></label>
                    <select name="supplier_id" id="supplierId" class="form-select">
                        <option value="">-- Select Supplier --</option>
                        <?php foreach ($suppliers as $sup): ?>
                        <option value="<?= $sup['id'] ?>"><?= sanitize($sup['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Reference ID</label>
                    <input type="text" name="reference_id" class="form-control" placeholder="e.g. SO-001 or PO-001">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Invoice Date <span class="text-danger">*</span></label>
                    <input type="date" name="invoice_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Due Date <span class="text-danger">*</span></label>
                    <input type="date" name="due_date" class="form-control" value="<?= date('Y-m-d', strtotime('+30 days')) ?>" required>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">Subtotal <span class="text-danger">*</span></label>
                    <input type="number" name="subtotal" id="subtotal" class="form-control" value="0" min="0" step="0.01" required oninput="calcTotal()">
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">Tax Amount</label>
                    <input type="number" name="tax_amount" id="tax_amount" class="form-control" value="0" min="0" step="0.01" oninput="calcTotal()">
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">Discount Amount</label>
                    <input type="number" name="discount_amount" id="discount_amount" class="form-control" value="0" min="0" step="0.01" oninput="calcTotal()">
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">Total</label>
                    <input type="number" name="total" id="total" class="form-control" value="0" step="0.01" readonly>
                </div>
                <div class="col-md-12 mb-3">
                    <label class="form-label">Notes</label>
                    <textarea name="notes" class="form-control" rows="2"></textarea>
                </div>
            </div>
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i> Save Invoice</button>
                <a href="<?= BASE_URL ?>invoices.php" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<script>
function toggleParty() {
    var type = document.getElementById('invoiceType').value;
    document.getElementById('customerField').style.display = type === 'sales' ? '' : 'none';
    document.getElementById('supplierField').style.display = type === 'purchase' ? '' : 'none';
    if (type === 'sales') {
        document.getElementById('supplierId').value = '';
    } else {
        document.getElementById('customerId').value = '';
    }
}
function calcTotal() {
    var subtotal = parseFloat(document.getElementById('subtotal').value) || 0;
    var tax = parseFloat(document.getElementById('tax_amount').value) || 0;
    var discount = parseFloat(document.getElementById('discount_amount').value) || 0;
    document.getElementById('total').value = (subtotal + tax - discount).toFixed(2);
}
calcTotal();
</script>

<?php
// === EDIT ===
elseif ($action === 'edit' && $id > 0):
    $stmt = $db->prepare("SELECT * FROM invoices WHERE id = ?");
    $stmt->execute([$id]);
    $inv = $stmt->fetch();
    if (!$inv) { setFlash('error', 'Invoice not found.'); header('Location: ' . BASE_URL . 'invoices.php'); exit; }
    if ($inv['status'] !== 'draft') { setFlash('error', 'Only draft invoices can be edited.'); header('Location: ' . BASE_URL . 'invoices.php?action=view&id=' . $id); exit; }

    $customers = $db->query("SELECT id, name FROM customers WHERE status = 'active' ORDER BY name")->fetchAll();
    $suppliers = $db->query("SELECT id, name FROM suppliers WHERE status = 'active' ORDER BY name")->fetchAll();
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h5 class="mb-0">Edit Invoice</h5>
        <small class="text-muted"><?= sanitize($inv['invoice_number']) ?></small>
    </div>
    <a href="<?= BASE_URL ?>invoices.php?action=view&id=<?= $inv['id'] ?>" class="btn btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i> Back</a>
</div>

<div class="card">
    <div class="card-body">
        <form method="POST" action="<?= BASE_URL ?>invoices.php?action=update&id=<?= $inv['id'] ?>">
            <div class="row">
                <div class="col-md-4 mb-3">
                    <label class="form-label">Invoice Number</label>
                    <input type="text" class="form-control" value="<?= sanitize($inv['invoice_number']) ?>" disabled>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Type <span class="text-danger">*</span></label>
                    <select name="type" id="invoiceType" class="form-select" required onchange="toggleParty()">
                        <option value="sales" <?= $inv['type'] === 'sales' ? 'selected' : '' ?>>Sales</option>
                        <option value="purchase" <?= $inv['type'] === 'purchase' ? 'selected' : '' ?>>Purchase</option>
                    </select>
                </div>
                <div class="col-md-4 mb-3" id="customerField" style="<?= $inv['type'] === 'purchase' ? 'display:none;' : '' ?>">
                    <label class="form-label">Customer <span class="text-danger">*</span></label>
                    <select name="customer_id" id="customerId" class="form-select">
                        <option value="">-- Select Customer --</option>
                        <?php foreach ($customers as $cust): ?>
                        <option value="<?= $cust['id'] ?>" <?= $inv['customer_id'] == $cust['id'] ? 'selected' : '' ?>><?= sanitize($cust['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4 mb-3" id="supplierField" style="<?= $inv['type'] === 'sales' ? 'display:none;' : '' ?>">
                    <label class="form-label">Supplier <span class="text-danger">*</span></label>
                    <select name="supplier_id" id="supplierId" class="form-select">
                        <option value="">-- Select Supplier --</option>
                        <?php foreach ($suppliers as $sup): ?>
                        <option value="<?= $sup['id'] ?>" <?= $inv['supplier_id'] == $sup['id'] ? 'selected' : '' ?>><?= sanitize($sup['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Reference ID</label>
                    <input type="text" name="reference_id" class="form-control" value="<?= sanitize($inv['reference_id']) ?>">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Invoice Date <span class="text-danger">*</span></label>
                    <input type="date" name="invoice_date" class="form-control" value="<?= $inv['invoice_date'] ?>" required>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Due Date <span class="text-danger">*</span></label>
                    <input type="date" name="due_date" class="form-control" value="<?= $inv['due_date'] ?>" required>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">Subtotal <span class="text-danger">*</span></label>
                    <input type="number" name="subtotal" id="subtotal" class="form-control" value="<?= $inv['subtotal'] ?>" min="0" step="0.01" required oninput="calcTotal()">
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">Tax Amount</label>
                    <input type="number" name="tax_amount" id="tax_amount" class="form-control" value="<?= $inv['tax_amount'] ?>" min="0" step="0.01" oninput="calcTotal()">
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">Discount Amount</label>
                    <input type="number" name="discount_amount" id="discount_amount" class="form-control" value="<?= $inv['discount_amount'] ?>" min="0" step="0.01" oninput="calcTotal()">
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">Total</label>
                    <input type="number" name="total" id="total" class="form-control" value="<?= $inv['total'] ?>" step="0.01" readonly>
                </div>
                <div class="col-md-12 mb-3">
                    <label class="form-label">Notes</label>
                    <textarea name="notes" class="form-control" rows="2"><?= sanitize($inv['notes']) ?></textarea>
                </div>
            </div>
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i> Update Invoice</button>
                <a href="<?= BASE_URL ?>invoices.php?action=view&id=<?= $inv['id'] ?>" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<script>
function toggleParty() {
    var type = document.getElementById('invoiceType').value;
    document.getElementById('customerField').style.display = type === 'sales' ? '' : 'none';
    document.getElementById('supplierField').style.display = type === 'purchase' ? '' : 'none';
    if (type === 'sales') {
        document.getElementById('supplierId').value = '';
    } else {
        document.getElementById('customerId').value = '';
    }
}
function calcTotal() {
    var subtotal = parseFloat(document.getElementById('subtotal').value) || 0;
    var tax = parseFloat(document.getElementById('tax_amount').value) || 0;
    var discount = parseFloat(document.getElementById('discount_amount').value) || 0;
    document.getElementById('total').value = (subtotal + tax - discount).toFixed(2);
}
</script>

<?php
// === VIEW ===
elseif ($action === 'view' && $id > 0):
    $stmt = $db->prepare("SELECT i.*, c.name as customer_name, s.name as supplier_name, u.full_name as created_by_name FROM invoices i LEFT JOIN customers c ON i.customer_id = c.id LEFT JOIN suppliers s ON i.supplier_id = s.id LEFT JOIN users u ON i.created_by = u.id WHERE i.id = ?");
    $stmt->execute([$id]);
    $inv = $stmt->fetch();
    if (!$inv) { setFlash('error', 'Invoice not found.'); header('Location: ' . BASE_URL . 'invoices.php'); exit; }

    // Check for overdue
    $displayStatus = $inv['status'];
    if ($inv['status'] === 'sent' && $inv['due_date'] < date('Y-m-d') && $inv['paid_amount'] < $inv['total']) {
        $displayStatus = 'overdue';
    }

    // Payment history
    $stmt = $db->prepare("SELECT * FROM payments WHERE invoice_id = ? ORDER BY payment_date DESC, id DESC");
    $stmt->execute([$id]);
    $payments = $stmt->fetchAll();

    $remaining = $inv['total'] - $inv['paid_amount'];
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h5 class="mb-0"><?= sanitize($inv['invoice_number']) ?></h5>
        <small class="text-muted"><span class="badge bg-<?= $inv['type'] === 'sales' ? 'info' : 'secondary' ?>"><?= ucfirst($inv['type']) ?></span> Invoice</small>
    </div>
    <div class="d-flex gap-2">
        <?php if ($inv['status'] === 'draft'): ?>
        <form method="POST" action="<?= BASE_URL ?>invoices.php?action=mark_sent&id=<?= $inv['id'] ?>" class="d-inline">
            <button type="submit" class="btn btn-info text-white"><i class="fas fa-paper-plane me-1"></i> Mark as Sent</button>
        </form>
        <a href="<?= BASE_URL ?>invoices.php?action=edit&id=<?= $inv['id'] ?>" class="btn btn-warning"><i class="fas fa-edit me-1"></i> Edit</a>
        <?php endif; ?>
        <?php if ($displayStatus !== 'paid' && $inv['status'] !== 'cancelled' && $inv['status'] !== 'draft'): ?>
        <a href="<?= BASE_URL ?>payments.php?action=create&invoice_id=<?= $inv['id'] ?>" class="btn btn-success"><i class="fas fa-money-bill-wave me-1"></i> Record Payment</a>
        <?php endif; ?>
        <button type="button" class="btn btn-outline-primary" onclick="window.print()"><i class="fas fa-print me-1"></i> Print</button>
        <?php if ($inv['status'] === 'draft'): ?>
        <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteModalView"><i class="fas fa-trash me-1"></i> Delete</button>
        <!-- Delete Modal -->
        <div class="modal fade" id="deleteModalView" tabindex="-1">
            <div class="modal-dialog modal-sm">
                <div class="modal-content">
                    <div class="modal-header">
                        <h6 class="modal-title">Confirm Delete</h6>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        Delete invoice <strong><?= sanitize($inv['invoice_number']) ?></strong>? This will also delete related payments.
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <form method="POST" action="<?= BASE_URL ?>invoices.php?action=delete&id=<?= $inv['id'] ?>" class="d-inline">
                            <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
        <a href="<?= BASE_URL ?>invoices.php" class="btn btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i> Back</a>
    </div>
</div>

<div class="row">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header"><i class="fas fa-file-invoice me-2"></i>Invoice Details</div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label text-muted">Invoice Number</label>
                        <div class="fw-bold"><?= sanitize($inv['invoice_number']) ?></div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label text-muted">Status</label>
                        <div><span class="badge status-<?= $displayStatus ?>"><?= ucfirst($displayStatus) ?></span></div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label text-muted">Type</label>
                        <div><span class="badge bg-<?= $inv['type'] === 'sales' ? 'info' : 'secondary' ?>"><?= ucfirst($inv['type']) ?></span></div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label text-muted"><?= $inv['type'] === 'sales' ? 'Customer' : 'Supplier' ?></label>
                        <div><?= $inv['type'] === 'sales' ? sanitize($inv['customer_name'] ?? '-') : sanitize($inv['supplier_name'] ?? '-') ?></div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label text-muted">Reference ID</label>
                        <div><?= sanitize($inv['reference_id']) ?: '-' ?></div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label text-muted">Created By</label>
                        <div><?= sanitize($inv['created_by_name'] ?? '-') ?></div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label text-muted">Invoice Date</label>
                        <div><?= formatDate($inv['invoice_date']) ?></div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label text-muted">Due Date</label>
                        <div><?= $inv['due_date'] ? formatDate($inv['due_date']) : '-' ?></div>
                    </div>
                    <?php if ($inv['notes']): ?>
                    <div class="col-md-12 mb-3">
                        <label class="form-label text-muted">Notes</label>
                        <div><?= nl2br(sanitize($inv['notes'])) ?></div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card">
            <div class="card-header"><i class="fas fa-calculator me-2"></i>Financial Summary</div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label text-muted">Subtotal</label>
                    <div class="fw-bold"><?= formatCurrency($inv['subtotal']) ?></div>
                </div>
                <div class="mb-3">
                    <label class="form-label text-muted">Tax Amount</label>
                    <div class="fw-bold"><?= formatCurrency($inv['tax_amount']) ?></div>
                </div>
                <div class="mb-3">
                    <label class="form-label text-muted">Discount Amount</label>
                    <div class="fw-bold text-danger">- <?= formatCurrency($inv['discount_amount']) ?></div>
                </div>
                <hr>
                <div class="mb-3">
                    <label class="form-label text-muted">Total</label>
                    <div class="fs-5 fw-bold text-primary"><?= formatCurrency($inv['total']) ?></div>
                </div>
                <div class="mb-3">
                    <label class="form-label text-muted">Paid Amount</label>
                    <div class="fw-bold text-success"><?= formatCurrency($inv['paid_amount']) ?></div>
                </div>
                <div class="mb-3">
                    <label class="form-label text-muted">Remaining Balance</label>
                    <div class="fs-5 fw-bold <?= $remaining > 0 ? 'text-danger' : 'text-success' ?>"><?= formatCurrency($remaining) ?></div>
                </div>
                <hr>
                <div class="mb-2">
                    <small class="text-muted">Created: <?= formatDate($inv['created_at']) ?></small>
                </div>
                <div>
                    <small class="text-muted">Updated: <?= formatDate($inv['updated_at']) ?></small>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card mt-2">
    <div class="card-header"><i class="fas fa-money-bill-wave me-2"></i>Payment History</div>
    <div class="card-body p-0">
        <?php if (empty($payments)): ?>
        <div class="empty-state py-4">
            <i class="fas fa-money-bill-wave"></i>
            <p>No payments recorded yet.</p>
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>Payment #</th>
                        <th>Date</th>
                        <th>Method</th>
                        <th>Amount</th>
                        <th>Reference</th>
                        <th>Notes</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($payments as $p): ?>
                    <tr>
                        <td><a href="<?= BASE_URL ?>payments.php?action=view&id=<?= $p['id'] ?>"><?= sanitize($p['payment_number'] ?? $p['id']) ?></a></td>
                        <td><?= formatDate($p['payment_date']) ?></td>
                        <td><?= sanitize(ucfirst($p['payment_method'] ?? '-')) ?></td>
                        <td class="text-success fw-bold"><?= formatCurrency($p['amount']) ?></td>
                        <td><?= sanitize($p['reference'] ?? '-') ?></td>
                        <td><?= sanitize($p['notes'] ?? '-') ?></td>
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
<a href="<?= BASE_URL ?>invoices.php" class="btn btn-outline-secondary">Back to Invoices</a>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
