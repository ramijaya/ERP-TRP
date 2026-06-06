<?php $pageTitle = 'Payments'; require_once __DIR__ . '/includes/header.php'; ?>
<?php
$db = getDB();
$action = $_GET['action'] ?? 'list';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'store') {
        $payNum = 'PAY-' . date('Ym') . '-' . str_pad(($db->query("SELECT COUNT(*)+1 FROM payments")->fetchColumn()), 4, '0', STR_PAD_LEFT);
        $stmt = $db->prepare("INSERT INTO payments (payment_number, invoice_id, type, amount, payment_method, payment_date, reference, notes, created_by) VALUES (?,?,?,?,?,?,?,?,?)");
        $invoiceId = $_POST['invoice_id'] ?: null;
        $stmt->execute([$payNum, $invoiceId, $_POST['type'], $_POST['amount'], $_POST['payment_method'], $_POST['payment_date'], $_POST['reference'], $_POST['notes'], $_SESSION['user_id']]);

        if ($invoiceId) {
            $db->prepare("UPDATE invoices SET paid_amount = paid_amount + ?, payment_status = CASE WHEN paid_amount + ? >= total THEN 'paid' ELSE 'partial' END, status = CASE WHEN paid_amount + ? >= total THEN 'paid' ELSE status END WHERE id = ?")->execute([$_POST['amount'], $_POST['amount'], $_POST['amount'], $invoiceId]);
        }
        setFlash('success', 'Payment recorded successfully');
        header('Location: ' . BASE_URL . 'payments.php'); exit;

    } elseif ($action === 'delete' && isset($_GET['id'])) {
        $payment = $db->prepare("SELECT * FROM payments WHERE id=?"); $payment->execute([$_GET['id']]); $payment = $payment->fetch();
        if ($payment) {
            if ($payment['invoice_id']) {
                $db->prepare("UPDATE invoices SET paid_amount = GREATEST(0, paid_amount - ?), payment_status = CASE WHEN paid_amount - ? <= 0 THEN 'unpaid' ELSE 'partial' END WHERE id = ?")->execute([$payment['amount'], $payment['amount'], $payment['invoice_id']]);
            }
            $db->prepare("DELETE FROM payments WHERE id=?")->execute([$_GET['id']]);
            setFlash('success', 'Payment deleted');
        }
        header('Location: ' . BASE_URL . 'payments.php'); exit;
    }
}

if ($action === 'create'):
    $invoices = $db->query("SELECT id, invoice_number, type, total, paid_amount FROM invoices WHERE status != 'cancelled' AND status != 'paid' ORDER BY invoice_date DESC")->fetchAll();
?>
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fas fa-plus me-2"></i>Record Payment</span>
        <a href="<?= BASE_URL ?>payments.php" class="btn btn-sm btn-outline-secondary">Back</a>
    </div>
    <div class="card-body">
        <form method="POST" action="<?= BASE_URL ?>payments.php?action=store">
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Type *</label>
                    <select name="type" class="form-select" required>
                        <option value="incoming">Incoming (Received)</option>
                        <option value="outgoing">Outgoing (Paid)</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Invoice (Optional)</label>
                    <select name="invoice_id" class="form-select">
                        <option value="">No Invoice</option>
                        <?php foreach ($invoices as $inv): ?>
                        <option value="<?= $inv['id'] ?>" data-balance="<?= $inv['total'] - $inv['paid_amount'] ?>"><?= sanitize($inv['invoice_number']) ?> (Balance: <?= formatCurrency($inv['total'] - $inv['paid_amount']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Amount *</label>
                    <input type="number" name="amount" class="form-control" step="0.01" min="0.01" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Payment Method *</label>
                    <select name="payment_method" class="form-select" required>
                        <option value="cash">Cash</option>
                        <option value="bank_transfer">Bank Transfer</option>
                        <option value="credit_card">Credit Card</option>
                        <option value="check">Check</option>
                        <option value="other">Other</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Date *</label>
                    <input type="date" name="payment_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Reference</label>
                    <input type="text" name="reference" class="form-control" placeholder="Transfer ref, check #, etc.">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Notes</label>
                    <input type="text" name="notes" class="form-control" placeholder="Optional notes">
                </div>
            </div>
            <div class="mt-3"><button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>Record Payment</button></div>
        </form>
    </div>
</div>

<?php elseif ($action === 'view' && isset($_GET['id'])):
    $stmt = $db->prepare("SELECT p.*, i.invoice_number, u.full_name as user_name FROM payments p LEFT JOIN invoices i ON p.invoice_id=i.id LEFT JOIN users u ON p.created_by=u.id WHERE p.id=?");
    $stmt->execute([$_GET['id']]); $payment = $stmt->fetch();
    if (!$payment) { setFlash('error','Payment not found'); header('Location:'.BASE_URL.'payments.php'); exit; }
?>
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fas fa-money-bill-wave me-2"></i>Payment: <?= sanitize($payment['payment_number']) ?></span>
        <div>
            <form method="POST" action="<?= BASE_URL ?>payments.php?action=delete&id=<?= $payment['id'] ?>" class="d-inline" onsubmit="return confirm('Delete this payment?')">
                <button class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i> Delete</button>
            </form>
            <a href="<?= BASE_URL ?>payments.php" class="btn btn-sm btn-outline-secondary">Back</a>
        </div>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <table class="table table-borderless">
                    <tr><td class="text-muted" width="150">Payment #</td><td><strong><?= sanitize($payment['payment_number']) ?></strong></td></tr>
                    <tr><td class="text-muted">Type</td><td><span class="badge <?= $payment['type']==='incoming' ? 'bg-success' : 'bg-danger' ?>"><?= ucfirst($payment['type']) ?></span></td></tr>
                    <tr><td class="text-muted">Amount</td><td><strong style="font-size:1.2rem"><?= formatCurrency($payment['amount']) ?></strong></td></tr>
                    <tr><td class="text-muted">Method</td><td><?= ucfirst(str_replace('_',' ',$payment['payment_method'])) ?></td></tr>
                    <tr><td class="text-muted">Date</td><td><?= formatDate($payment['payment_date']) ?></td></tr>
                    <tr><td class="text-muted">Invoice</td><td><?= $payment['invoice_number'] ? '<a href="'.BASE_URL.'invoices.php?action=view&id='.$payment['invoice_id'].'">'.sanitize($payment['invoice_number']).'</a>' : '-' ?></td></tr>
                    <tr><td class="text-muted">Reference</td><td><?= sanitize($payment['reference'] ?: '-') ?></td></tr>
                    <tr><td class="text-muted">Notes</td><td><?= sanitize($payment['notes'] ?: '-') ?></td></tr>
                    <tr><td class="text-muted">Recorded By</td><td><?= sanitize($payment['user_name'] ?? '-') ?></td></tr>
                    <tr><td class="text-muted">Created</td><td><?= date('d M Y H:i', strtotime($payment['created_at'])) ?></td></tr>
                </table>
            </div>
        </div>
    </div>
</div>

<?php else: // LIST
    $where = []; $params = [];
    if (!empty($_GET['type'])) { $where[] = "p.type = ?"; $params[] = $_GET['type']; }
    if (!empty($_GET['method'])) { $where[] = "p.payment_method = ?"; $params[] = $_GET['method']; }
    if (!empty($_GET['date_from'])) { $where[] = "p.payment_date >= ?"; $params[] = $_GET['date_from']; }
    if (!empty($_GET['date_to'])) { $where[] = "p.payment_date <= ?"; $params[] = $_GET['date_to']; }
    $whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    $total = $db->prepare("SELECT COUNT(*) FROM payments p $whereSQL"); $total->execute($params); $total = $total->fetchColumn();
    $page = max(1, (int)($_GET['page'] ?? 1)); $perPage = 20; $pages = max(1, ceil($total/$perPage)); $offset = ($page-1)*$perPage;

    $stmt = $db->prepare("SELECT p.*, i.invoice_number FROM payments p LEFT JOIN invoices i ON p.invoice_id=i.id $whereSQL ORDER BY p.created_at DESC LIMIT $perPage OFFSET $offset");
    $stmt->execute($params); $payments = $stmt->fetchAll();

    $totalIn = $db->query("SELECT COALESCE(SUM(amount),0) FROM payments WHERE type='incoming'")->fetchColumn();
    $totalOut = $db->query("SELECT COALESCE(SUM(amount),0) FROM payments WHERE type='outgoing'")->fetchColumn();
?>

<div class="row g-3 mb-4">
    <div class="col-md-4"><div class="stat-card bg-success-gradient py-3 px-3"><div class="stat-value"><?= formatCurrency($totalIn) ?></div><div class="stat-label">Total Incoming</div></div></div>
    <div class="col-md-4"><div class="stat-card bg-danger-gradient py-3 px-3"><div class="stat-value"><?= formatCurrency($totalOut) ?></div><div class="stat-label">Total Outgoing</div></div></div>
    <div class="col-md-4"><div class="stat-card bg-primary-gradient py-3 px-3"><div class="stat-value"><?= formatCurrency($totalIn - $totalOut) ?></div><div class="stat-label">Net Cash Flow</div></div></div>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fas fa-money-bill-wave me-2"></i>Payments (<?= $total ?>)</span>
        <a href="<?= BASE_URL ?>payments.php?action=create" class="btn btn-sm btn-primary"><i class="fas fa-plus me-1"></i>Record Payment</a>
    </div>
    <div class="card-body">
        <form class="row g-2 mb-3">
            <div class="col-md-2">
                <select name="type" class="form-select form-select-sm">
                    <option value="">All Types</option>
                    <option value="incoming" <?= ($_GET['type'] ?? '') === 'incoming' ? 'selected' : '' ?>>Incoming</option>
                    <option value="outgoing" <?= ($_GET['type'] ?? '') === 'outgoing' ? 'selected' : '' ?>>Outgoing</option>
                </select>
            </div>
            <div class="col-md-2">
                <select name="method" class="form-select form-select-sm">
                    <option value="">All Methods</option>
                    <?php foreach (['cash','bank_transfer','credit_card','check','other'] as $m): ?>
                    <option value="<?= $m ?>" <?= ($_GET['method'] ?? '') === $m ? 'selected' : '' ?>><?= ucfirst(str_replace('_',' ',$m)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2"><input type="date" name="date_from" class="form-control form-control-sm" value="<?= $_GET['date_from'] ?? '' ?>"></div>
            <div class="col-md-2"><input type="date" name="date_to" class="form-control form-control-sm" value="<?= $_GET['date_to'] ?? '' ?>"></div>
            <div class="col-md-1"><button class="btn btn-sm btn-primary w-100">Filter</button></div>
        </form>

        <?php if (empty($payments)): ?>
            <div class="empty-state"><i class="fas fa-inbox"></i><p>No payments found</p></div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead><tr><th>Payment #</th><th>Date</th><th>Type</th><th>Invoice</th><th>Amount</th><th>Method</th><th>Actions</th></tr></thead>
                <tbody>
                <?php foreach ($payments as $p): ?>
                <tr>
                    <td><a href="<?= BASE_URL ?>payments.php?action=view&id=<?= $p['id'] ?>"><?= sanitize($p['payment_number']) ?></a></td>
                    <td><?= formatDate($p['payment_date']) ?></td>
                    <td><span class="badge <?= $p['type']==='incoming' ? 'bg-success' : 'bg-danger' ?>"><?= ucfirst($p['type']) ?></span></td>
                    <td><?= $p['invoice_number'] ? sanitize($p['invoice_number']) : '-' ?></td>
                    <td><strong><?= formatCurrency($p['amount']) ?></strong></td>
                    <td><?= ucfirst(str_replace('_',' ',$p['payment_method'])) ?></td>
                    <td>
                        <a href="<?= BASE_URL ?>payments.php?action=view&id=<?= $p['id'] ?>" class="btn btn-sm btn-outline-info"><i class="fas fa-eye"></i></a>
                        <form method="POST" action="<?= BASE_URL ?>payments.php?action=delete&id=<?= $p['id'] ?>" class="d-inline" onsubmit="return confirm('Delete?')">
                            <button class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php if ($pages > 1): ?>
        <nav><ul class="pagination pagination-sm justify-content-center">
            <?php for ($i=1; $i<=$pages; $i++): ?>
            <li class="page-item <?= $i===$page?'active':'' ?>"><a class="page-link" href="?page=<?= $i ?>&type=<?= urlencode($_GET['type']??'') ?>&method=<?= urlencode($_GET['method']??'') ?>"><?= $i ?></a></li>
            <?php endfor; ?>
        </ul></nav>
        <?php endif; endif; ?>
    </div>
</div>
<?php endif; ?>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
