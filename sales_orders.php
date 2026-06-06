<?php $pageTitle = 'Sales Orders'; require_once __DIR__ . '/includes/header.php'; ?>
<?php
$db = getDB();
$action = $_GET['action'] ?? 'list';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Load products for create/edit forms
$products = [];
if (in_array($action, ['create', 'edit'])) {
    $products = $db->query("SELECT id, code, name, selling_price, purchase_price, stock FROM products WHERE status='active' ORDER BY name")->fetchAll();
}

// ===================== STORE (POST) =====================
if ($action === 'store' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $db->beginTransaction();

        $customer_id = (int)$_POST['customer_id'];
        $order_date = $_POST['order_date'];
        $due_date = $_POST['due_date'] ?: null;
        $status = $_POST['status'] ?? 'draft';
        $notes = trim($_POST['notes'] ?? '');

        // Generate order number
        $prefix = 'SO-' . date('Ym') . '-';
        $stmt = $db->prepare("SELECT COUNT(*) FROM sales_orders WHERE order_number LIKE ?");
        $stmt->execute([$prefix . '%']);
        $count = (int)$stmt->fetchColumn();
        $order_number = $prefix . str_pad($count + 1, 4, '0', STR_PAD_LEFT);

        // Calculate totals from items
        $subtotal = 0;
        $tax_total = 0;
        $discount_total = 0;
        $items = [];

        if (!empty($_POST['product_id'])) {
            foreach ($_POST['product_id'] as $i => $product_id) {
                if (empty($product_id)) continue;
                $qty = (int)($_POST['quantity'][$i] ?? 1);
                $unit_price = (float)($_POST['unit_price'][$i] ?? 0);
                $discount_pct = (float)($_POST['discount'][$i] ?? 0);
                $tax_pct = (float)($_POST['tax'][$i] ?? 0);

                $line_subtotal = $qty * $unit_price;
                $line_discount = $line_subtotal * ($discount_pct / 100);
                $line_after_discount = $line_subtotal - $line_discount;
                $line_tax = $line_after_discount * ($tax_pct / 100);
                $line_total = $line_after_discount + $line_tax;

                $subtotal += $line_subtotal;
                $discount_total += $line_discount;
                $tax_total += $line_tax;

                $items[] = [
                    'product_id' => (int)$product_id,
                    'quantity' => $qty,
                    'unit_price' => $unit_price,
                    'discount' => $discount_pct,
                    'tax' => $tax_pct,
                    'total' => $line_total
                ];
            }
        }

        $total = $subtotal - $discount_total + $tax_total;

        $stmt = $db->prepare("INSERT INTO sales_orders (order_number, customer_id, order_date, due_date, status, subtotal, tax_amount, discount_amount, total, notes, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$order_number, $customer_id, $order_date, $due_date, $status, $subtotal, $tax_total, $discount_total, $total, $notes, $_SESSION['user_id']]);
        $order_id = $db->lastInsertId();

        // Insert items and update stock
        $stmtItem = $db->prepare("INSERT INTO sales_order_items (sales_order_id, product_id, quantity, unit_price, discount, tax, total) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmtStock = $db->prepare("UPDATE products SET stock = stock - ? WHERE id = ?");
        $stmtMovement = $db->prepare("INSERT INTO stock_movements (product_id, type, quantity, reference_type, reference_id, notes, created_by) VALUES (?, 'out', ?, 'sales_order', ?, ?, ?)");

        foreach ($items as $item) {
            $stmtItem->execute([$order_id, $item['product_id'], $item['quantity'], $item['unit_price'], $item['discount'], $item['tax'], $item['total']]);
            $stmtStock->execute([$item['quantity'], $item['product_id']]);
            $stmtMovement->execute([$item['product_id'], $item['quantity'], $order_id, 'Sales Order ' . $order_number, $_SESSION['user_id']]);
        }

        $db->commit();
        setFlash('success', 'Sales order ' . $order_number . ' created successfully.');
        header('Location: ' . BASE_URL . 'sales_orders.php?action=view&id=' . $order_id);
        exit;
    } catch (Exception $e) {
        $db->rollBack();
        setFlash('error', 'Failed to create sales order: ' . $e->getMessage());
        header('Location: ' . BASE_URL . 'sales_orders.php?action=create');
        exit;
    }
}

// ===================== UPDATE (POST) =====================
if ($action === 'update' && $_SERVER['REQUEST_METHOD'] === 'POST' && $id > 0) {
    try {
        $db->beginTransaction();

        // Get existing order for stock restoration
        $stmtOld = $db->prepare("SELECT * FROM sales_orders WHERE id = ?");
        $stmtOld->execute([$id]);
        $oldOrder = $stmtOld->fetch();
        if (!$oldOrder) throw new Exception('Order not found.');

        // Restore old stock
        $oldItems = $db->prepare("SELECT * FROM sales_order_items WHERE sales_order_id = ?");
        $oldItems->execute([$id]);
        $stmtRestoreStock = $db->prepare("UPDATE products SET stock = stock + ? WHERE id = ?");
        foreach ($oldItems->fetchAll() as $oi) {
            $stmtRestoreStock->execute([$oi['quantity'], $oi['product_id']]);
        }

        // Delete old items
        $db->prepare("DELETE FROM sales_order_items WHERE sales_order_id = ?")->execute([$id]);

        $customer_id = (int)$_POST['customer_id'];
        $order_date = $_POST['order_date'];
        $due_date = $_POST['due_date'] ?: null;
        $status = $_POST['status'] ?? 'draft';
        $notes = trim($_POST['notes'] ?? '');

        // Recalculate totals
        $subtotal = 0;
        $tax_total = 0;
        $discount_total = 0;
        $items = [];

        if (!empty($_POST['product_id'])) {
            foreach ($_POST['product_id'] as $i => $product_id) {
                if (empty($product_id)) continue;
                $qty = (int)($_POST['quantity'][$i] ?? 1);
                $unit_price = (float)($_POST['unit_price'][$i] ?? 0);
                $discount_pct = (float)($_POST['discount'][$i] ?? 0);
                $tax_pct = (float)($_POST['tax'][$i] ?? 0);

                $line_subtotal = $qty * $unit_price;
                $line_discount = $line_subtotal * ($discount_pct / 100);
                $line_after_discount = $line_subtotal - $line_discount;
                $line_tax = $line_after_discount * ($tax_pct / 100);
                $line_total = $line_after_discount + $line_tax;

                $subtotal += $line_subtotal;
                $discount_total += $line_discount;
                $tax_total += $line_tax;

                $items[] = [
                    'product_id' => (int)$product_id,
                    'quantity' => $qty,
                    'unit_price' => $unit_price,
                    'discount' => $discount_pct,
                    'tax' => $tax_pct,
                    'total' => $line_total
                ];
            }
        }

        $total = $subtotal - $discount_total + $tax_total;

        $stmt = $db->prepare("UPDATE sales_orders SET customer_id=?, order_date=?, due_date=?, status=?, subtotal=?, tax_amount=?, discount_amount=?, total=?, notes=? WHERE id=?");
        $stmt->execute([$customer_id, $order_date, $due_date, $status, $subtotal, $tax_total, $discount_total, $total, $notes, $id]);

        // Insert new items and decrease stock
        $stmtItem = $db->prepare("INSERT INTO sales_order_items (sales_order_id, product_id, quantity, unit_price, discount, tax, total) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmtStock = $db->prepare("UPDATE products SET stock = stock - ? WHERE id = ?");

        foreach ($items as $item) {
            $stmtItem->execute([$id, $item['product_id'], $item['quantity'], $item['unit_price'], $item['discount'], $item['tax'], $item['total']]);
            $stmtStock->execute([$item['quantity'], $item['product_id']]);
        }

        $db->commit();
        setFlash('success', 'Sales order updated successfully.');
        header('Location: ' . BASE_URL . 'sales_orders.php?action=view&id=' . $id);
        exit;
    } catch (Exception $e) {
        $db->rollBack();
        setFlash('error', 'Failed to update sales order: ' . $e->getMessage());
        header('Location: ' . BASE_URL . 'sales_orders.php?action=edit&id=' . $id);
        exit;
    }
}

// ===================== DELETE (POST) =====================
if ($action === 'delete' && $_SERVER['REQUEST_METHOD'] === 'POST' && $id > 0) {
    try {
        $db->beginTransaction();

        $stmt = $db->prepare("SELECT * FROM sales_orders WHERE id = ?");
        $stmt->execute([$id]);
        $order = $stmt->fetch();

        if (!$order) throw new Exception('Order not found.');
        if ($order['status'] !== 'draft') throw new Exception('Only draft orders can be deleted.');

        // Restore stock
        $items = $db->prepare("SELECT * FROM sales_order_items WHERE sales_order_id = ?");
        $items->execute([$id]);
        $stmtRestore = $db->prepare("UPDATE products SET stock = stock + ? WHERE id = ?");
        foreach ($items->fetchAll() as $item) {
            $stmtRestore->execute([$item['quantity'], $item['product_id']]);
        }

        // Delete order (items cascade)
        $db->prepare("DELETE FROM sales_orders WHERE id = ?")->execute([$id]);

        $db->commit();
        setFlash('success', 'Sales order deleted successfully.');
    } catch (Exception $e) {
        $db->rollBack();
        setFlash('error', 'Failed to delete: ' . $e->getMessage());
    }
    header('Location: ' . BASE_URL . 'sales_orders.php');
    exit;
}

// ===================== VIEW =====================
if ($action === 'view' && $id > 0):
    $stmt = $db->prepare("SELECT so.*, c.name as customer_name, c.email as customer_email, c.phone as customer_phone, c.address as customer_address, u.full_name as created_by_name
        FROM sales_orders so
        LEFT JOIN customers c ON so.customer_id = c.id
        LEFT JOIN users u ON so.created_by = u.id
        WHERE so.id = ?");
    $stmt->execute([$id]);
    $order = $stmt->fetch();
    if (!$order) { setFlash('error', 'Order not found.'); header('Location: ' . BASE_URL . 'sales_orders.php'); exit; }

    $stmtItems = $db->prepare("SELECT soi.*, p.name as product_name, p.code as product_code FROM sales_order_items soi LEFT JOIN products p ON soi.product_id = p.id WHERE soi.sales_order_id = ?");
    $stmtItems->execute([$id]);
    $orderItems = $stmtItems->fetchAll();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h5 class="mb-1">Sales Order: <?= sanitize($order['order_number']) ?></h5>
        <span class="badge status-<?= $order['status'] ?>"><?= ucfirst($order['status']) ?></span>
        <span class="badge status-<?= $order['payment_status'] ?> ms-1"><?= ucfirst($order['payment_status']) ?></span>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= BASE_URL ?>sales_orders.php" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i> Back</a>
        <a href="<?= BASE_URL ?>sales_orders.php?action=edit&id=<?= $order['id'] ?>" class="btn btn-primary btn-sm"><i class="fas fa-edit me-1"></i> Edit</a>
        <button onclick="window.print()" class="btn btn-outline-info btn-sm"><i class="fas fa-print me-1"></i> Print</button>
        <?php if ($order['status'] === 'draft'): ?>
        <form method="POST" action="<?= BASE_URL ?>sales_orders.php?action=delete&id=<?= $order['id'] ?>" onsubmit="return confirm('Are you sure you want to delete this order?');" class="d-inline">
            <button type="submit" class="btn btn-danger btn-sm"><i class="fas fa-trash me-1"></i> Delete</button>
        </form>
        <?php endif; ?>
    </div>
</div>

<div class="row">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header"><i class="fas fa-info-circle me-2"></i>Order Details</div>
            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <small class="text-muted d-block">Order Number</small>
                        <strong><?= sanitize($order['order_number']) ?></strong>
                    </div>
                    <div class="col-md-3">
                        <small class="text-muted d-block">Order Date</small>
                        <strong><?= formatDate($order['order_date']) ?></strong>
                    </div>
                    <div class="col-md-3">
                        <small class="text-muted d-block">Due Date</small>
                        <strong><?= $order['due_date'] ? formatDate($order['due_date']) : '-' ?></strong>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <small class="text-muted d-block">Customer</small>
                        <strong><?= sanitize($order['customer_name']) ?></strong><br>
                        <?php if ($order['customer_email']): ?><small class="text-muted"><?= sanitize($order['customer_email']) ?></small><br><?php endif; ?>
                        <?php if ($order['customer_phone']): ?><small class="text-muted"><?= sanitize($order['customer_phone']) ?></small><br><?php endif; ?>
                        <?php if ($order['customer_address']): ?><small class="text-muted"><?= sanitize($order['customer_address']) ?></small><?php endif; ?>
                    </div>
                    <div class="col-md-3">
                        <small class="text-muted d-block">Created By</small>
                        <strong><?= sanitize($order['created_by_name'] ?? '-') ?></strong>
                    </div>
                    <div class="col-md-3">
                        <small class="text-muted d-block">Created At</small>
                        <strong><?= formatDate($order['created_at']) ?></strong>
                    </div>
                </div>
                <?php if ($order['notes']): ?>
                <div class="mt-3 pt-3 border-top">
                    <small class="text-muted d-block">Notes</small>
                    <p class="mb-0"><?= nl2br(sanitize($order['notes'])) ?></p>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><i class="fas fa-list me-2"></i>Order Items</div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr><th>#</th><th>Product</th><th class="text-end">Qty</th><th class="text-end">Unit Price</th><th class="text-end">Disc %</th><th class="text-end">Tax %</th><th class="text-end">Total</th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ($orderItems as $i => $item): ?>
                            <tr>
                                <td><?= $i + 1 ?></td>
                                <td><strong><?= sanitize($item['product_name']) ?></strong><br><small class="text-muted"><?= sanitize($item['product_code']) ?></small></td>
                                <td class="text-end"><?= $item['quantity'] ?></td>
                                <td class="text-end"><?= formatCurrency($item['unit_price']) ?></td>
                                <td class="text-end"><?= number_format($item['discount'], 1) ?>%</td>
                                <td class="text-end"><?= number_format($item['tax'], 1) ?>%</td>
                                <td class="text-end"><?= formatCurrency($item['total']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card">
            <div class="card-header"><i class="fas fa-calculator me-2"></i>Order Summary</div>
            <div class="card-body">
                <div class="d-flex justify-content-between mb-2">
                    <span>Subtotal</span><strong><?= formatCurrency($order['subtotal']) ?></strong>
                </div>
                <div class="d-flex justify-content-between mb-2 text-danger">
                    <span>Discount</span><strong>-<?= formatCurrency($order['discount_amount']) ?></strong>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span>Tax</span><strong><?= formatCurrency($order['tax_amount']) ?></strong>
                </div>
                <hr>
                <div class="d-flex justify-content-between mb-2">
                    <span class="fw-bold fs-5">Total</span><strong class="fs-5 text-primary"><?= formatCurrency($order['total']) ?></strong>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span>Paid</span><strong class="text-success"><?= formatCurrency($order['paid_amount']) ?></strong>
                </div>
                <div class="d-flex justify-content-between">
                    <span>Balance Due</span><strong class="text-danger"><?= formatCurrency($order['total'] - $order['paid_amount']) ?></strong>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// ===================== CREATE / EDIT FORM =====================
elseif ($action === 'create' || $action === 'edit'):
    $order = null;
    $orderItems = [];
    if ($action === 'edit' && $id > 0) {
        $stmt = $db->prepare("SELECT * FROM sales_orders WHERE id = ?");
        $stmt->execute([$id]);
        $order = $stmt->fetch();
        if (!$order) { setFlash('error', 'Order not found.'); header('Location: ' . BASE_URL . 'sales_orders.php'); exit; }
        $stmtItems = $db->prepare("SELECT * FROM sales_order_items WHERE sales_order_id = ?");
        $stmtItems->execute([$id]);
        $orderItems = $stmtItems->fetchAll();
    }

    $customers = $db->query("SELECT id, name, code FROM customers WHERE status='active' ORDER BY name")->fetchAll();
    $statuses = ['draft', 'confirmed', 'processing', 'shipped', 'delivered', 'cancelled'];
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h5 class="mb-0"><?= $action === 'edit' ? 'Edit Sales Order: ' . sanitize($order['order_number']) : 'Create New Sales Order' ?></h5>
    <a href="<?= BASE_URL ?>sales_orders.php" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i> Back</a>
</div>

<form method="POST" action="<?= BASE_URL ?>sales_orders.php?action=<?= $action === 'edit' ? 'update&id=' . $id : 'store' ?>" id="orderForm">
    <div class="row">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header"><i class="fas fa-info-circle me-2"></i>Order Information</div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Customer <span class="text-danger">*</span></label>
                            <select name="customer_id" class="form-select" required>
                                <option value="">-- Select Customer --</option>
                                <?php foreach ($customers as $c): ?>
                                    <option value="<?= $c['id'] ?>" <?= ($order && $order['customer_id'] == $c['id']) ? 'selected' : '' ?>><?= sanitize($c['name']) ?> (<?= sanitize($c['code']) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Order Date <span class="text-danger">*</span></label>
                            <input type="date" name="order_date" class="form-control" value="<?= $order['order_date'] ?? date('Y-m-d') ?>" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Due Date</label>
                            <input type="date" name="due_date" class="form-control" value="<?= $order['due_date'] ?? '' ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select">
                                <?php foreach ($statuses as $s): ?>
                                    <option value="<?= $s ?>" <?= ($order && $order['status'] === $s) ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Notes</label>
                            <textarea name="notes" class="form-control" rows="2"><?= sanitize($order['notes'] ?? '') ?></textarea>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><i class="fas fa-list me-2"></i>Order Items</span>
                    <button type="button" class="btn btn-primary btn-sm" onclick="addItem()"><i class="fas fa-plus me-1"></i> Add Item</button>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0" id="itemsTable">
                            <thead>
                                <tr>
                                    <th style="min-width:200px">Product</th>
                                    <th style="width:80px">Qty</th>
                                    <th style="width:130px">Unit Price</th>
                                    <th style="width:80px">Disc %</th>
                                    <th style="width:80px">Tax %</th>
                                    <th style="width:130px" class="text-end">Total</th>
                                    <th style="width:40px"></th>
                                </tr>
                            </thead>
                            <tbody id="itemsBody">
                                <?php if (!empty($orderItems)): ?>
                                    <?php foreach ($orderItems as $idx => $item): ?>
                                    <tr class="item-row">
                                        <td>
                                            <select name="product_id[]" class="form-select form-select-sm product-select" onchange="onProductChange(this)" required>
                                                <option value="">-- Select --</option>
                                                <?php foreach ($products as $p): ?>
                                                    <option value="<?= $p['id'] ?>" data-price="<?= $p['selling_price'] ?>" data-stock="<?= $p['stock'] ?>" <?= $item['product_id'] == $p['id'] ? 'selected' : '' ?>><?= sanitize($p['code'] . ' - ' . $p['name']) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </td>
                                        <td><input type="number" name="quantity[]" class="form-control form-control-sm qty-input" value="<?= $item['quantity'] ?>" min="1" onchange="calculateTotals()" required></td>
                                        <td><input type="number" name="unit_price[]" class="form-control form-control-sm price-input" value="<?= $item['unit_price'] ?>" step="0.01" min="0" onchange="calculateTotals()" required></td>
                                        <td><input type="number" name="discount[]" class="form-control form-control-sm disc-input" value="<?= $item['discount'] ?>" step="0.1" min="0" max="100" onchange="calculateTotals()"></td>
                                        <td><input type="number" name="tax[]" class="form-control form-control-sm tax-input" value="<?= $item['tax'] ?>" step="0.1" min="0" max="100" onchange="calculateTotals()"></td>
                                        <td class="text-end line-total fw-bold">0</td>
                                        <td><button type="button" class="btn btn-outline-danger btn-sm" onclick="removeItem(this)"><i class="fas fa-times"></i></button></td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr class="item-row">
                                        <td>
                                            <select name="product_id[]" class="form-select form-select-sm product-select" onchange="onProductChange(this)" required>
                                                <option value="">-- Select --</option>
                                                <?php foreach ($products as $p): ?>
                                                    <option value="<?= $p['id'] ?>" data-price="<?= $p['selling_price'] ?>" data-stock="<?= $p['stock'] ?>"><?= sanitize($p['code'] . ' - ' . $p['name']) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </td>
                                        <td><input type="number" name="quantity[]" class="form-control form-control-sm qty-input" value="1" min="1" onchange="calculateTotals()" required></td>
                                        <td><input type="number" name="unit_price[]" class="form-control form-control-sm price-input" value="0" step="0.01" min="0" onchange="calculateTotals()" required></td>
                                        <td><input type="number" name="discount[]" class="form-control form-control-sm disc-input" value="0" step="0.1" min="0" max="100" onchange="calculateTotals()"></td>
                                        <td><input type="number" name="tax[]" class="form-control form-control-sm tax-input" value="0" step="0.1" min="0" max="100" onchange="calculateTotals()"></td>
                                        <td class="text-end line-total fw-bold">0</td>
                                        <td><button type="button" class="btn btn-outline-danger btn-sm" onclick="removeItem(this)"><i class="fas fa-times"></i></button></td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card">
                <div class="card-header"><i class="fas fa-calculator me-2"></i>Order Summary</div>
                <div class="card-body">
                    <div class="d-flex justify-content-between mb-2">
                        <span>Subtotal</span><strong id="display_subtotal">Rp 0</strong>
                    </div>
                    <div class="d-flex justify-content-between mb-2 text-danger">
                        <span>Discount</span><strong id="display_discount">-Rp 0</strong>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span>Tax</span><strong id="display_tax">Rp 0</strong>
                    </div>
                    <hr>
                    <div class="d-flex justify-content-between mb-3">
                        <span class="fw-bold fs-5">Total</span><strong class="fs-5 text-primary" id="display_total">Rp 0</strong>
                    </div>
                    <button type="submit" class="btn btn-primary w-100"><i class="fas fa-save me-1"></i> <?= $action === 'edit' ? 'Update Order' : 'Create Order' ?></button>
                </div>
            </div>
        </div>
    </div>
</form>

<script>
const productsData = <?= json_encode($products) ?>;

let itemIndex = <?= max(count($orderItems), 1) ?>;

function addItem() {
    itemIndex++;
    let options = '<option value="">-- Select --</option>';
    productsData.forEach(p => {
        options += `<option value="${p.id}" data-price="${p.selling_price}" data-stock="${p.stock}">${p.code} - ${p.name}</option>`;
    });

    const row = `<tr class="item-row">
        <td><select name="product_id[]" class="form-select form-select-sm product-select" onchange="onProductChange(this)" required>${options}</select></td>
        <td><input type="number" name="quantity[]" class="form-control form-control-sm qty-input" value="1" min="1" onchange="calculateTotals()" required></td>
        <td><input type="number" name="unit_price[]" class="form-control form-control-sm price-input" value="0" step="0.01" min="0" onchange="calculateTotals()" required></td>
        <td><input type="number" name="discount[]" class="form-control form-control-sm disc-input" value="0" step="0.1" min="0" max="100" onchange="calculateTotals()"></td>
        <td><input type="number" name="tax[]" class="form-control form-control-sm tax-input" value="0" step="0.1" min="0" max="100" onchange="calculateTotals()"></td>
        <td class="text-end line-total fw-bold">0</td>
        <td><button type="button" class="btn btn-outline-danger btn-sm" onclick="removeItem(this)"><i class="fas fa-times"></i></button></td>
    </tr>`;

    document.getElementById('itemsBody').insertAdjacentHTML('beforeend', row);
}

function removeItem(btn) {
    const rows = document.querySelectorAll('.item-row');
    if (rows.length <= 1) { alert('At least one item is required.'); return; }
    btn.closest('tr').remove();
    calculateTotals();
}

function onProductChange(select) {
    const option = select.options[select.selectedIndex];
    const price = parseFloat(option.dataset.price) || 0;
    const row = select.closest('tr');
    row.querySelector('.price-input').value = price;
    calculateTotals();
}

function formatRp(val) {
    return 'Rp ' + Math.round(val).toLocaleString('id-ID');
}

function calculateTotals() {
    let subtotal = 0, taxTotal = 0, discountTotal = 0;

    document.querySelectorAll('.item-row').forEach(row => {
        const qty = parseFloat(row.querySelector('.qty-input').value) || 0;
        const price = parseFloat(row.querySelector('.price-input').value) || 0;
        const discPct = parseFloat(row.querySelector('.disc-input').value) || 0;
        const taxPct = parseFloat(row.querySelector('.tax-input').value) || 0;

        const lineSub = qty * price;
        const lineDisc = lineSub * (discPct / 100);
        const afterDisc = lineSub - lineDisc;
        const lineTax = afterDisc * (taxPct / 100);
        const lineTotal = afterDisc + lineTax;

        subtotal += lineSub;
        discountTotal += lineDisc;
        taxTotal += lineTax;

        row.querySelector('.line-total').textContent = formatRp(lineTotal);
    });

    document.getElementById('display_subtotal').textContent = formatRp(subtotal);
    document.getElementById('display_discount').textContent = '-' + formatRp(discountTotal);
    document.getElementById('display_tax').textContent = formatRp(taxTotal);
    document.getElementById('display_total').textContent = formatRp(subtotal - discountTotal + taxTotal);
}

document.addEventListener('DOMContentLoaded', calculateTotals);
</script>

<?php
// ===================== LIST =====================
else:
    $search = trim($_GET['search'] ?? '');
    $filterStatus = $_GET['status'] ?? '';
    $filterPayment = $_GET['payment_status'] ?? '';
    $page = max(1, (int)($_GET['page'] ?? 1));
    $perPage = 20;
    $offset = ($page - 1) * $perPage;

    $where = "1=1";
    $params = [];

    if ($search !== '') {
        $where .= " AND (so.order_number LIKE ? OR c.name LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    if ($filterStatus !== '') {
        $where .= " AND so.status = ?";
        $params[] = $filterStatus;
    }
    if ($filterPayment !== '') {
        $where .= " AND so.payment_status = ?";
        $params[] = $filterPayment;
    }

    // Count
    $stmtCount = $db->prepare("SELECT COUNT(*) FROM sales_orders so LEFT JOIN customers c ON so.customer_id = c.id WHERE $where");
    $stmtCount->execute($params);
    $totalRows = (int)$stmtCount->fetchColumn();
    $totalPages = max(1, ceil($totalRows / $perPage));

    // Fetch
    $stmt = $db->prepare("SELECT so.*, c.name as customer_name FROM sales_orders so LEFT JOIN customers c ON so.customer_id = c.id WHERE $where ORDER BY so.created_at DESC LIMIT $perPage OFFSET $offset");
    $stmt->execute($params);
    $orders = $stmt->fetchAll();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h5 class="mb-0">Sales Orders</h5>
    <a href="<?= BASE_URL ?>sales_orders.php?action=create" class="btn btn-primary btn-sm"><i class="fas fa-plus me-1"></i> New Sales Order</a>
</div>

<!-- Filters -->
<div class="card">
    <div class="card-body py-3">
        <form method="GET" class="row g-2 align-items-end">
            <input type="hidden" name="action" value="list">
            <div class="col-md-4">
                <input type="text" name="search" class="form-control form-control-sm" placeholder="Search order number or customer..." value="<?= sanitize($search) ?>">
            </div>
            <div class="col-md-2">
                <select name="status" class="form-select form-select-sm">
                    <option value="">All Status</option>
                    <?php foreach (['draft','confirmed','processing','shipped','delivered','cancelled'] as $s): ?>
                        <option value="<?= $s ?>" <?= $filterStatus === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <select name="payment_status" class="form-select form-select-sm">
                    <option value="">All Payment</option>
                    <?php foreach (['unpaid','partial','paid'] as $ps): ?>
                        <option value="<?= $ps ?>" <?= $filterPayment === $ps ? 'selected' : '' ?>><?= ucfirst($ps) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary btn-sm w-100"><i class="fas fa-search me-1"></i> Filter</button>
            </div>
            <div class="col-md-2">
                <a href="<?= BASE_URL ?>sales_orders.php" class="btn btn-outline-secondary btn-sm w-100">Reset</a>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body p-0">
        <?php if (empty($orders)): ?>
            <div class="empty-state">
                <i class="fas fa-shopping-cart"></i>
                <p>No sales orders found.</p>
                <a href="<?= BASE_URL ?>sales_orders.php?action=create" class="btn btn-primary btn-sm"><i class="fas fa-plus me-1"></i> Create First Order</a>
            </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>Order #</th>
                        <th>Date</th>
                        <th>Customer</th>
                        <th class="text-end">Total</th>
                        <th>Payment</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($orders as $o): ?>
                    <tr>
                        <td><a href="<?= BASE_URL ?>sales_orders.php?action=view&id=<?= $o['id'] ?>" class="fw-bold text-decoration-none"><?= sanitize($o['order_number']) ?></a></td>
                        <td><?= formatDate($o['order_date']) ?></td>
                        <td><?= sanitize($o['customer_name'] ?? '-') ?></td>
                        <td class="text-end fw-bold"><?= formatCurrency($o['total']) ?></td>
                        <td><span class="badge status-<?= $o['payment_status'] ?>"><?= ucfirst($o['payment_status']) ?></span></td>
                        <td><span class="badge status-<?= $o['status'] ?>"><?= ucfirst($o['status']) ?></span></td>
                        <td>
                            <div class="d-flex gap-1">
                                <a href="<?= BASE_URL ?>sales_orders.php?action=view&id=<?= $o['id'] ?>" class="btn btn-outline-info btn-sm" title="View"><i class="fas fa-eye"></i></a>
                                <a href="<?= BASE_URL ?>sales_orders.php?action=edit&id=<?= $o['id'] ?>" class="btn btn-outline-primary btn-sm" title="Edit"><i class="fas fa-edit"></i></a>
                                <?php if ($o['status'] === 'draft'): ?>
                                <form method="POST" action="<?= BASE_URL ?>sales_orders.php?action=delete&id=<?= $o['id'] ?>" onsubmit="return confirm('Delete this order?');" class="d-inline">
                                    <button type="submit" class="btn btn-outline-danger btn-sm" title="Delete"><i class="fas fa-trash"></i></button>
                                </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($totalPages > 1): ?>
        <div class="d-flex justify-content-between align-items-center p-3">
            <small class="text-muted">Showing <?= $offset + 1 ?>-<?= min($offset + $perPage, $totalRows) ?> of <?= $totalRows ?> orders</small>
            <nav>
                <ul class="pagination pagination-sm mb-0">
                    <?php if ($page > 1): ?>
                        <li class="page-item"><a class="page-link" href="<?= BASE_URL ?>sales_orders.php?action=list&page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($filterStatus) ?>&payment_status=<?= urlencode($filterPayment) ?>">&laquo;</a></li>
                    <?php endif; ?>
                    <?php for ($p = max(1, $page - 2); $p <= min($totalPages, $page + 2); $p++): ?>
                        <li class="page-item <?= $p === $page ? 'active' : '' ?>"><a class="page-link" href="<?= BASE_URL ?>sales_orders.php?action=list&page=<?= $p ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($filterStatus) ?>&payment_status=<?= urlencode($filterPayment) ?>"><?= $p ?></a></li>
                    <?php endfor; ?>
                    <?php if ($page < $totalPages): ?>
                        <li class="page-item"><a class="page-link" href="<?= BASE_URL ?>sales_orders.php?action=list&page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($filterStatus) ?>&payment_status=<?= urlencode($filterPayment) ?>">&raquo;</a></li>
                    <?php endif; ?>
                </ul>
            </nav>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<?php endif; ?>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
