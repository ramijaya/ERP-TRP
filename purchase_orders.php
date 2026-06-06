<?php $pageTitle = 'Purchase Orders'; require_once __DIR__ . '/includes/header.php'; ?>
<?php
$db = getDB();
$action = $_GET['action'] ?? 'list';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'store') {
        $orderNum = 'PO-' . date('Ym') . '-' . str_pad(($db->query("SELECT COUNT(*)+1 FROM purchase_orders")->fetchColumn()), 4, '0', STR_PAD_LEFT);
        $stmt = $db->prepare("INSERT INTO purchase_orders (order_number, supplier_id, order_date, expected_date, status, subtotal, tax_amount, discount_amount, total, notes, created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?)");
        $stmt->execute([$orderNum, $_POST['supplier_id'], $_POST['order_date'], $_POST['expected_date'] ?: null, $_POST['status'], $_POST['subtotal'], $_POST['tax_amount'], $_POST['discount_amount'], $_POST['total'], $_POST['notes'], $_SESSION['user_id']]);
        $orderId = $db->lastInsertId();

        if (!empty($_POST['product_id'])) {
            $stmtItem = $db->prepare("INSERT INTO purchase_order_items (purchase_order_id, product_id, quantity, unit_price, discount, tax, total) VALUES (?,?,?,?,?,?,?)");
            $stmtUpd = $db->prepare("UPDATE products SET stock = stock + ? WHERE id = ?");
            foreach ($_POST['product_id'] as $i => $pid) {
                if (!$pid) continue;
                $qty = (int)$_POST['quantity'][$i];
                $price = (float)$_POST['unit_price'][$i];
                $disc = (float)($_POST['discount'][$i] ?? 0);
                $tax = (float)($_POST['item_tax'][$i] ?? 0);
                $lineTotal = $qty * $price * (1 - $disc/100) * (1 + $tax/100);
                $stmtItem->execute([$orderId, $pid, $qty, $price, $disc, $tax, $lineTotal]);
                if ($_POST['status'] !== 'draft') $stmtUpd->execute([$qty, $pid]);
            }
        }
        setFlash('success', 'Purchase order created successfully');
        header('Location: ' . BASE_URL . 'purchase_orders.php?action=view&id=' . $orderId); exit;

    } elseif ($action === 'update' && isset($_GET['id'])) {
        $stmt = $db->prepare("UPDATE purchase_orders SET supplier_id=?, order_date=?, expected_date=?, status=?, subtotal=?, tax_amount=?, discount_amount=?, total=?, notes=? WHERE id=?");
        $stmt->execute([$_POST['supplier_id'], $_POST['order_date'], $_POST['expected_date'] ?: null, $_POST['status'], $_POST['subtotal'], $_POST['tax_amount'], $_POST['discount_amount'], $_POST['total'], $_POST['notes'], $_GET['id']]);

        $db->prepare("DELETE FROM purchase_order_items WHERE purchase_order_id=?")->execute([$_GET['id']]);
        if (!empty($_POST['product_id'])) {
            $stmtItem = $db->prepare("INSERT INTO purchase_order_items (purchase_order_id, product_id, quantity, unit_price, discount, tax, total) VALUES (?,?,?,?,?,?,?)");
            foreach ($_POST['product_id'] as $i => $pid) {
                if (!$pid) continue;
                $qty = (int)$_POST['quantity'][$i];
                $price = (float)$_POST['unit_price'][$i];
                $disc = (float)($_POST['discount'][$i] ?? 0);
                $tax = (float)($_POST['item_tax'][$i] ?? 0);
                $lineTotal = $qty * $price * (1 - $disc/100) * (1 + $tax/100);
                $stmtItem->execute([$_GET['id'], $pid, $qty, $price, $disc, $tax, $lineTotal]);
            }
        }
        setFlash('success', 'Purchase order updated successfully');
        header('Location: ' . BASE_URL . 'purchase_orders.php?action=view&id=' . $_GET['id']); exit;

    } elseif ($action === 'delete' && isset($_GET['id'])) {
        $order = $db->prepare("SELECT * FROM purchase_orders WHERE id=?"); $order->execute([$_GET['id']]); $order = $order->fetch();
        if ($order && $order['status'] === 'draft') {
            $db->prepare("DELETE FROM purchase_order_items WHERE purchase_order_id=?")->execute([$_GET['id']]);
            $db->prepare("DELETE FROM purchase_orders WHERE id=?")->execute([$_GET['id']]);
            setFlash('success', 'Purchase order deleted');
        } else {
            setFlash('error', 'Only draft orders can be deleted');
        }
        header('Location: ' . BASE_URL . 'purchase_orders.php'); exit;
    }
}

// VIEWS
if ($action === 'create' || $action === 'edit'):
    $order = null;
    $items = [];
    if ($action === 'edit' && isset($_GET['id'])) {
        $stmt = $db->prepare("SELECT * FROM purchase_orders WHERE id=?"); $stmt->execute([$_GET['id']]); $order = $stmt->fetch();
        $items = $db->prepare("SELECT * FROM purchase_order_items WHERE purchase_order_id=?"); $items->execute([$_GET['id']]); $items = $items->fetchAll();
    }
    $suppliers = $db->query("SELECT id, name FROM suppliers WHERE status='active' ORDER BY name")->fetchAll();
    $products = $db->query("SELECT id, code, name, purchase_price, stock FROM products WHERE status='active' ORDER BY name")->fetchAll();
?>
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fas fa-<?= $action === 'edit' ? 'edit' : 'plus' ?> me-2"></i><?= $action === 'edit' ? 'Edit' : 'New' ?> Purchase Order</span>
        <a href="<?= BASE_URL ?>purchase_orders.php" class="btn btn-sm btn-outline-secondary">Back</a>
    </div>
    <div class="card-body">
        <form method="POST" action="<?= BASE_URL ?>purchase_orders.php?action=<?= $action === 'edit' ? 'update&id='.$order['id'] : 'store' ?>">
            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    <label class="form-label">Supplier *</label>
                    <select name="supplier_id" class="form-select" required>
                        <option value="">Select Supplier</option>
                        <?php foreach ($suppliers as $s): ?>
                        <option value="<?= $s['id'] ?>" <?= ($order['supplier_id'] ?? '') == $s['id'] ? 'selected' : '' ?>><?= sanitize($s['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Order Date *</label>
                    <input type="date" name="order_date" class="form-control" required value="<?= $order['order_date'] ?? date('Y-m-d') ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Expected Date</label>
                    <input type="date" name="expected_date" class="form-control" value="<?= $order['expected_date'] ?? '' ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <?php foreach (['draft','confirmed','ordered','received','cancelled'] as $st): ?>
                        <option value="<?= $st ?>" <?= ($order['status'] ?? 'draft') === $st ? 'selected' : '' ?>><?= ucfirst($st) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <h6 class="mb-3"><i class="fas fa-list me-2"></i>Order Items</h6>
            <div class="table-responsive mb-3">
                <table class="table table-bordered" id="itemsTable">
                    <thead class="table-light">
                        <tr><th>Product</th><th width="90">Qty</th><th width="130">Unit Price</th><th width="80">Disc %</th><th width="80">Tax %</th><th width="130">Total</th><th width="50"></th></tr>
                    </thead>
                    <tbody id="itemsBody">
                        <?php if (!empty($items)): foreach ($items as $idx => $it): ?>
                        <tr>
                            <td><select name="product_id[]" class="form-select form-select-sm product-select" onchange="fillPrice(this)" required>
                                <option value="">Select</option>
                                <?php foreach ($products as $p): ?>
                                <option value="<?= $p['id'] ?>" data-price="<?= $p['purchase_price'] ?>" <?= $it['product_id'] == $p['id'] ? 'selected' : '' ?>><?= sanitize($p['code'].' - '.$p['name']) ?></option>
                                <?php endforeach; ?>
                            </select></td>
                            <td><input type="number" name="quantity[]" class="form-control form-control-sm" min="1" value="<?= $it['quantity'] ?>" onchange="calcRow(this)" required></td>
                            <td><input type="number" name="unit_price[]" class="form-control form-control-sm" step="0.01" value="<?= $it['unit_price'] ?>" onchange="calcRow(this)" required></td>
                            <td><input type="number" name="discount[]" class="form-control form-control-sm" step="0.01" value="<?= $it['discount'] ?>" onchange="calcRow(this)"></td>
                            <td><input type="number" name="item_tax[]" class="form-control form-control-sm" step="0.01" value="<?= $it['tax'] ?>" onchange="calcRow(this)"></td>
                            <td><input type="text" class="form-control form-control-sm line-total" readonly value="<?= number_format($it['total'],2) ?>"></td>
                            <td><button type="button" class="btn btn-sm btn-outline-danger" onclick="this.closest('tr').remove();calcTotals()"><i class="fas fa-times"></i></button></td>
                        </tr>
                        <?php endforeach; else: ?>
                        <tr>
                            <td><select name="product_id[]" class="form-select form-select-sm product-select" onchange="fillPrice(this)" required>
                                <option value="">Select Product</option>
                                <?php foreach ($products as $p): ?>
                                <option value="<?= $p['id'] ?>" data-price="<?= $p['purchase_price'] ?>"><?= sanitize($p['code'].' - '.$p['name']) ?></option>
                                <?php endforeach; ?>
                            </select></td>
                            <td><input type="number" name="quantity[]" class="form-control form-control-sm" min="1" value="1" onchange="calcRow(this)" required></td>
                            <td><input type="number" name="unit_price[]" class="form-control form-control-sm" step="0.01" value="0" onchange="calcRow(this)" required></td>
                            <td><input type="number" name="discount[]" class="form-control form-control-sm" step="0.01" value="0" onchange="calcRow(this)"></td>
                            <td><input type="number" name="item_tax[]" class="form-control form-control-sm" step="0.01" value="0" onchange="calcRow(this)"></td>
                            <td><input type="text" class="form-control form-control-sm line-total" readonly value="0.00"></td>
                            <td><button type="button" class="btn btn-sm btn-outline-danger" onclick="this.closest('tr').remove();calcTotals()"><i class="fas fa-times"></i></button></td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
                <button type="button" class="btn btn-sm btn-outline-primary" onclick="addRow()"><i class="fas fa-plus me-1"></i>Add Item</button>
            </div>

            <div class="row justify-content-end">
                <div class="col-md-4">
                    <div class="d-flex justify-content-between mb-2"><span>Subtotal</span><input type="number" name="subtotal" id="subtotal" class="form-control form-control-sm w-50 text-end" readonly value="<?= $order['subtotal'] ?? 0 ?>"></div>
                    <div class="d-flex justify-content-between mb-2"><span>Tax</span><input type="number" name="tax_amount" id="tax_amount" class="form-control form-control-sm w-50 text-end" readonly value="<?= $order['tax_amount'] ?? 0 ?>"></div>
                    <div class="d-flex justify-content-between mb-2"><span>Discount</span><input type="number" name="discount_amount" id="discount_amount" class="form-control form-control-sm w-50 text-end" readonly value="<?= $order['discount_amount'] ?? 0 ?>"></div>
                    <hr>
                    <div class="d-flex justify-content-between mb-2"><strong>Total</strong><input type="number" name="total" id="grand_total" class="form-control form-control-sm w-50 text-end fw-bold" readonly value="<?= $order['total'] ?? 0 ?>"></div>
                </div>
            </div>

            <div class="mb-3"><label class="form-label">Notes</label><textarea name="notes" class="form-control" rows="2"><?= sanitize($order['notes'] ?? '') ?></textarea></div>
            <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>Save</button>
        </form>
    </div>
</div>

<script>
const productsJSON = <?= json_encode($products) ?>;
function fillPrice(sel) {
    const opt = sel.options[sel.selectedIndex];
    const row = sel.closest('tr');
    if (opt.dataset.price) row.querySelector('[name="unit_price[]"]').value = parseFloat(opt.dataset.price).toFixed(2);
    calcRow(sel);
}
function calcRow(el) {
    const row = el.closest('tr');
    const qty = parseFloat(row.querySelector('[name="quantity[]"]').value) || 0;
    const price = parseFloat(row.querySelector('[name="unit_price[]"]').value) || 0;
    const disc = parseFloat(row.querySelector('[name="discount[]"]').value) || 0;
    const tax = parseFloat(row.querySelector('[name="item_tax[]"]').value) || 0;
    const total = qty * price * (1 - disc/100) * (1 + tax/100);
    row.querySelector('.line-total').value = total.toFixed(2);
    calcTotals();
}
function calcTotals() {
    let sub = 0, tax = 0, disc = 0;
    document.querySelectorAll('#itemsBody tr').forEach(row => {
        const qty = parseFloat(row.querySelector('[name="quantity[]"]')?.value) || 0;
        const price = parseFloat(row.querySelector('[name="unit_price[]"]')?.value) || 0;
        const d = parseFloat(row.querySelector('[name="discount[]"]')?.value) || 0;
        const t = parseFloat(row.querySelector('[name="item_tax[]"]')?.value) || 0;
        const base = qty * price;
        sub += base; disc += base * d / 100; tax += (base - base*d/100) * t / 100;
    });
    document.getElementById('subtotal').value = sub.toFixed(2);
    document.getElementById('tax_amount').value = tax.toFixed(2);
    document.getElementById('discount_amount').value = disc.toFixed(2);
    document.getElementById('grand_total').value = (sub - disc + tax).toFixed(2);
}
function addRow() {
    const tbody = document.getElementById('itemsBody');
    const row = tbody.rows[0].cloneNode(true);
    row.querySelector('[name="product_id[]"]').value = '';
    row.querySelector('[name="quantity[]"]').value = 1;
    row.querySelector('[name="unit_price[]"]').value = '0';
    row.querySelector('[name="discount[]"]').value = '0';
    row.querySelector('[name="item_tax[]"]').value = '0';
    row.querySelector('.line-total').value = '0.00';
    tbody.appendChild(row);
}
</script>

<?php elseif ($action === 'view' && isset($_GET['id'])):
    $stmt = $db->prepare("SELECT po.*, s.name as supplier_name FROM purchase_orders po LEFT JOIN suppliers s ON po.supplier_id=s.id WHERE po.id=?");
    $stmt->execute([$_GET['id']]); $order = $stmt->fetch();
    $items = $db->prepare("SELECT poi.*, p.name as product_name, p.code as product_code FROM purchase_order_items poi LEFT JOIN products p ON poi.product_id=p.id WHERE poi.purchase_order_id=?");
    $items->execute([$_GET['id']]); $items = $items->fetchAll();
    if (!$order) { setFlash('error','Order not found'); header('Location:'.BASE_URL.'purchase_orders.php'); exit; }
?>
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fas fa-clipboard-list me-2"></i>Purchase Order: <?= sanitize($order['order_number']) ?></span>
        <div>
            <a href="<?= BASE_URL ?>purchase_orders.php?action=edit&id=<?= $order['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="fas fa-edit"></i> Edit</a>
            <?php if ($order['status'] === 'draft'): ?>
            <form method="POST" action="<?= BASE_URL ?>purchase_orders.php?action=delete&id=<?= $order['id'] ?>" class="d-inline" onsubmit="return confirm('Delete this order?')">
                <button class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i> Delete</button>
            </form>
            <?php endif; ?>
            <a href="<?= BASE_URL ?>purchase_orders.php" class="btn btn-sm btn-outline-secondary">Back</a>
        </div>
    </div>
    <div class="card-body">
        <div class="row mb-4">
            <div class="col-md-3"><strong>Supplier:</strong><br><?= sanitize($order['supplier_name']) ?></div>
            <div class="col-md-2"><strong>Date:</strong><br><?= formatDate($order['order_date']) ?></div>
            <div class="col-md-2"><strong>Expected:</strong><br><?= $order['expected_date'] ? formatDate($order['expected_date']) : '-' ?></div>
            <div class="col-md-2"><strong>Status:</strong><br><span class="badge status-<?= $order['status'] ?>"><?= ucfirst($order['status']) ?></span></div>
            <div class="col-md-3"><strong>Payment:</strong><br><span class="badge status-<?= $order['payment_status'] ?>"><?= ucfirst($order['payment_status']) ?></span></div>
        </div>
        <div class="table-responsive">
            <table class="table table-bordered">
                <thead class="table-light"><tr><th>Product</th><th>Qty</th><th>Unit Price</th><th>Disc %</th><th>Tax %</th><th class="text-end">Total</th></tr></thead>
                <tbody>
                <?php foreach ($items as $it): ?>
                <tr>
                    <td><?= sanitize($it['product_code'].' - '.$it['product_name']) ?></td>
                    <td><?= $it['quantity'] ?></td>
                    <td><?= formatCurrency($it['unit_price']) ?></td>
                    <td><?= $it['discount'] ?>%</td>
                    <td><?= $it['tax'] ?>%</td>
                    <td class="text-end"><?= formatCurrency($it['total']) ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr><td colspan="5" class="text-end"><strong>Subtotal</strong></td><td class="text-end"><?= formatCurrency($order['subtotal']) ?></td></tr>
                    <tr><td colspan="5" class="text-end">Tax</td><td class="text-end"><?= formatCurrency($order['tax_amount']) ?></td></tr>
                    <tr><td colspan="5" class="text-end">Discount</td><td class="text-end"><?= formatCurrency($order['discount_amount']) ?></td></tr>
                    <tr><td colspan="5" class="text-end"><strong>Total</strong></td><td class="text-end"><strong><?= formatCurrency($order['total']) ?></strong></td></tr>
                </tfoot>
            </table>
        </div>
        <?php if ($order['notes']): ?><p><strong>Notes:</strong> <?= sanitize($order['notes']) ?></p><?php endif; ?>
    </div>
</div>

<?php else: // LIST
    $where = []; $params = [];
    if (!empty($_GET['search'])) { $where[] = "po.order_number LIKE ?"; $params[] = '%'.$_GET['search'].'%'; }
    if (!empty($_GET['status'])) { $where[] = "po.status = ?"; $params[] = $_GET['status']; }
    $whereSQL = $where ? 'WHERE '.implode(' AND ', $where) : '';

    $total = $db->prepare("SELECT COUNT(*) FROM purchase_orders po $whereSQL"); $total->execute($params); $total = $total->fetchColumn();
    $page = max(1, (int)($_GET['page'] ?? 1)); $perPage = 20; $pages = max(1, ceil($total/$perPage)); $offset = ($page-1)*$perPage;

    $stmt = $db->prepare("SELECT po.*, s.name as supplier_name FROM purchase_orders po LEFT JOIN suppliers s ON po.supplier_id=s.id $whereSQL ORDER BY po.created_at DESC LIMIT $perPage OFFSET $offset");
    $stmt->execute($params); $orders = $stmt->fetchAll();
?>
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fas fa-clipboard-list me-2"></i>Purchase Orders (<?= $total ?>)</span>
        <a href="<?= BASE_URL ?>purchase_orders.php?action=create" class="btn btn-sm btn-primary"><i class="fas fa-plus me-1"></i>New Order</a>
    </div>
    <div class="card-body">
        <form class="row g-2 mb-3">
            <div class="col-md-4"><input type="text" name="search" class="form-control form-control-sm" placeholder="Search order number..." value="<?= sanitize($_GET['search'] ?? '') ?>"></div>
            <div class="col-md-3">
                <select name="status" class="form-select form-select-sm">
                    <option value="">All Status</option>
                    <?php foreach (['draft','confirmed','ordered','received','cancelled'] as $st): ?>
                    <option value="<?= $st ?>" <?= ($_GET['status'] ?? '') === $st ? 'selected' : '' ?>><?= ucfirst($st) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2"><button class="btn btn-sm btn-primary w-100">Filter</button></div>
        </form>
        <?php if (empty($orders)): ?>
            <div class="empty-state"><i class="fas fa-inbox"></i><p>No purchase orders found</p></div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead><tr><th>Order #</th><th>Date</th><th>Supplier</th><th>Total</th><th>Payment</th><th>Status</th><th>Actions</th></tr></thead>
                <tbody>
                <?php foreach ($orders as $o): ?>
                <tr>
                    <td><a href="<?= BASE_URL ?>purchase_orders.php?action=view&id=<?= $o['id'] ?>"><?= sanitize($o['order_number']) ?></a></td>
                    <td><?= formatDate($o['order_date']) ?></td>
                    <td><?= sanitize($o['supplier_name'] ?? '-') ?></td>
                    <td><?= formatCurrency($o['total']) ?></td>
                    <td><span class="badge status-<?= $o['payment_status'] ?>"><?= ucfirst($o['payment_status']) ?></span></td>
                    <td><span class="badge status-<?= $o['status'] ?>"><?= ucfirst($o['status']) ?></span></td>
                    <td>
                        <a href="<?= BASE_URL ?>purchase_orders.php?action=edit&id=<?= $o['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="fas fa-edit"></i></a>
                        <a href="<?= BASE_URL ?>purchase_orders.php?action=view&id=<?= $o['id'] ?>" class="btn btn-sm btn-outline-info"><i class="fas fa-eye"></i></a>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php if ($pages > 1): ?>
        <nav><ul class="pagination pagination-sm justify-content-center">
            <?php for ($i=1; $i<=$pages; $i++): ?>
            <li class="page-item <?= $i===$page?'active':'' ?>"><a class="page-link" href="?page=<?= $i ?>&search=<?= urlencode($_GET['search']??'') ?>&status=<?= urlencode($_GET['status']??'') ?>"><?= $i ?></a></li>
            <?php endfor; ?>
        </ul></nav>
        <?php endif; endif; ?>
    </div>
</div>
<?php endif; ?>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
