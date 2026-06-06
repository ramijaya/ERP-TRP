<?php $pageTitle = 'Reports'; require_once __DIR__ . '/includes/header.php'; ?>
<?php
$db = getDB();
$type = $_GET['type'] ?? '';
$dateFrom = $_GET['date_from'] ?? date('Y-01-01');
$dateTo = $_GET['date_to'] ?? date('Y-m-d');

if ($type): ?>
<div class="mb-3">
    <a href="<?= BASE_URL ?>reports.php" class="btn btn-sm btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i>Back to Reports</a>
    <button onclick="window.print()" class="btn btn-sm btn-outline-primary"><i class="fas fa-print me-1"></i>Print</button>
</div>

<?php if ($type === 'sales'):
    $stmt = $db->prepare("SELECT DATE_FORMAT(order_date, '%Y-%m') as month, COUNT(*) as count, COALESCE(SUM(total),0) as total FROM sales_orders WHERE order_date BETWEEN ? AND ? AND status != 'cancelled' GROUP BY month ORDER BY month");
    $stmt->execute([$dateFrom, $dateTo]); $data = $stmt->fetchAll();
    $grandTotal = array_sum(array_column($data, 'total'));
    $totalOrders = array_sum(array_column($data, 'count'));
?>
<div class="card">
    <div class="card-header"><i class="fas fa-chart-line me-2 text-primary"></i>Sales Report</div>
    <div class="card-body">
        <form class="row g-2 mb-4">
            <input type="hidden" name="type" value="sales">
            <div class="col-md-3"><label class="form-label">From</label><input type="date" name="date_from" class="form-control form-control-sm" value="<?= $dateFrom ?>"></div>
            <div class="col-md-3"><label class="form-label">To</label><input type="date" name="date_to" class="form-control form-control-sm" value="<?= $dateTo ?>"></div>
            <div class="col-md-2 d-flex align-items-end"><button class="btn btn-sm btn-primary">Generate</button></div>
        </form>
        <canvas id="salesChart" height="80" class="mb-4"></canvas>
        <div class="table-responsive">
            <table class="table table-bordered">
                <thead class="table-light"><tr><th>Month</th><th>Orders</th><th class="text-end">Total Amount</th></tr></thead>
                <tbody>
                <?php foreach ($data as $d): ?>
                <tr><td><?= date('F Y', strtotime($d['month'].'-01')) ?></td><td><?= $d['count'] ?></td><td class="text-end"><?= formatCurrency($d['total']) ?></td></tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot><tr class="table-primary"><td><strong>Total</strong></td><td><strong><?= $totalOrders ?></strong></td><td class="text-end"><strong><?= formatCurrency($grandTotal) ?></strong></td></tr></tfoot>
            </table>
        </div>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function() {
    new Chart(document.getElementById('salesChart'), {
        type: 'bar',
        data: {
            labels: <?= json_encode(array_map(fn($d) => date('M Y', strtotime($d['month'].'-01')), $data)) ?>,
            datasets: [{
                label: 'Sales', data: <?= json_encode(array_column($data, 'total')) ?>,
                backgroundColor: 'rgba(59,130,246,0.7)', borderRadius: 6
            }]
        },
        options: { responsive: true, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { callback: v => 'Rp '+(v/1000000).toFixed(0)+'M' } } } }
    });
});
</script>

<?php elseif ($type === 'purchases'):
    $stmt = $db->prepare("SELECT DATE_FORMAT(order_date, '%Y-%m') as month, COUNT(*) as count, COALESCE(SUM(total),0) as total FROM purchase_orders WHERE order_date BETWEEN ? AND ? AND status != 'cancelled' GROUP BY month ORDER BY month");
    $stmt->execute([$dateFrom, $dateTo]); $data = $stmt->fetchAll();
    $grandTotal = array_sum(array_column($data, 'total'));
?>
<div class="card">
    <div class="card-header"><i class="fas fa-truck me-2 text-warning"></i>Purchase Report</div>
    <div class="card-body">
        <form class="row g-2 mb-4">
            <input type="hidden" name="type" value="purchases">
            <div class="col-md-3"><label class="form-label">From</label><input type="date" name="date_from" class="form-control form-control-sm" value="<?= $dateFrom ?>"></div>
            <div class="col-md-3"><label class="form-label">To</label><input type="date" name="date_to" class="form-control form-control-sm" value="<?= $dateTo ?>"></div>
            <div class="col-md-2 d-flex align-items-end"><button class="btn btn-sm btn-primary">Generate</button></div>
        </form>
        <canvas id="purchaseChart" height="80" class="mb-4"></canvas>
        <div class="table-responsive">
            <table class="table table-bordered">
                <thead class="table-light"><tr><th>Month</th><th>Orders</th><th class="text-end">Total Amount</th></tr></thead>
                <tbody>
                <?php foreach ($data as $d): ?>
                <tr><td><?= date('F Y', strtotime($d['month'].'-01')) ?></td><td><?= $d['count'] ?></td><td class="text-end"><?= formatCurrency($d['total']) ?></td></tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot><tr class="table-warning"><td><strong>Total</strong></td><td><strong><?= array_sum(array_column($data, 'count')) ?></strong></td><td class="text-end"><strong><?= formatCurrency($grandTotal) ?></strong></td></tr></tfoot>
            </table>
        </div>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function() {
    new Chart(document.getElementById('purchaseChart'), {
        type: 'bar',
        data: {
            labels: <?= json_encode(array_map(fn($d) => date('M Y', strtotime($d['month'].'-01')), $data)) ?>,
            datasets: [{ label: 'Purchases', data: <?= json_encode(array_column($data, 'total')) ?>, backgroundColor: 'rgba(245,158,11,0.7)', borderRadius: 6 }]
        },
        options: { responsive: true, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { callback: v => 'Rp '+(v/1000000).toFixed(0)+'M' } } } }
    });
});
</script>

<?php elseif ($type === 'inventory'):
    $products = $db->query("SELECT p.*, pc.name as category_name FROM products p LEFT JOIN product_categories pc ON p.category_id=pc.id WHERE p.status='active' ORDER BY p.name")->fetchAll();
    $totalValue = 0;
    foreach ($products as $p) $totalValue += $p['stock'] * $p['purchase_price'];
?>
<div class="card">
    <div class="card-header"><i class="fas fa-boxes-stacked me-2 text-info"></i>Inventory Report</div>
    <div class="card-body">
        <div class="alert alert-info">Total Inventory Value: <strong><?= formatCurrency($totalValue) ?></strong> | Total Products: <strong><?= count($products) ?></strong></div>
        <div class="table-responsive">
            <table class="table table-bordered table-hover">
                <thead class="table-light"><tr><th>Code</th><th>Product</th><th>Category</th><th class="text-center">Stock</th><th class="text-center">Min Stock</th><th class="text-end">Unit Cost</th><th class="text-end">Value</th></tr></thead>
                <tbody>
                <?php foreach ($products as $p): $isLow = $p['min_stock'] > 0 && $p['stock'] <= $p['min_stock']; ?>
                <tr class="<?= $isLow ? 'table-danger' : '' ?>">
                    <td><?= sanitize($p['code']) ?></td>
                    <td><?= sanitize($p['name']) ?> <?= $isLow ? '<i class="fas fa-exclamation-triangle text-danger"></i>' : '' ?></td>
                    <td><?= sanitize($p['category_name'] ?? '-') ?></td>
                    <td class="text-center"><?= $p['stock'] ?></td>
                    <td class="text-center"><?= $p['min_stock'] ?></td>
                    <td class="text-end"><?= formatCurrency($p['purchase_price']) ?></td>
                    <td class="text-end"><?= formatCurrency($p['stock'] * $p['purchase_price']) ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot><tr class="table-primary"><td colspan="6" class="text-end"><strong>Total Value</strong></td><td class="text-end"><strong><?= formatCurrency($totalValue) ?></strong></td></tr></tfoot>
            </table>
        </div>
    </div>
</div>

<?php elseif ($type === 'profit_loss'):
    $stmt = $db->prepare("SELECT COALESCE(SUM(total),0) FROM sales_orders WHERE order_date BETWEEN ? AND ? AND status != 'cancelled'");
    $stmt->execute([$dateFrom, $dateTo]); $revenue = $stmt->fetchColumn();

    $stmt = $db->prepare("SELECT COALESCE(SUM(total),0) FROM purchase_orders WHERE order_date BETWEEN ? AND ? AND status != 'cancelled'");
    $stmt->execute([$dateFrom, $dateTo]); $cogs = $stmt->fetchColumn();

    $grossProfit = $revenue - $cogs;

    $stmt = $db->prepare("SELECT COALESCE(SUM(jel.debit),0) FROM journal_entry_lines jel JOIN journal_entries je ON jel.journal_entry_id=je.id JOIN chart_of_accounts coa ON jel.account_id=coa.id WHERE coa.account_type='expense' AND je.status='posted' AND je.entry_date BETWEEN ? AND ?");
    $stmt->execute([$dateFrom, $dateTo]); $expenses = $stmt->fetchColumn();

    $netProfit = $grossProfit - $expenses;
?>
<div class="card">
    <div class="card-header"><i class="fas fa-calculator me-2 text-success"></i>Profit & Loss Statement</div>
    <div class="card-body">
        <form class="row g-2 mb-4">
            <input type="hidden" name="type" value="profit_loss">
            <div class="col-md-3"><label class="form-label">From</label><input type="date" name="date_from" class="form-control form-control-sm" value="<?= $dateFrom ?>"></div>
            <div class="col-md-3"><label class="form-label">To</label><input type="date" name="date_to" class="form-control form-control-sm" value="<?= $dateTo ?>"></div>
            <div class="col-md-2 d-flex align-items-end"><button class="btn btn-sm btn-primary">Generate</button></div>
        </form>
        <div class="row justify-content-center">
            <div class="col-md-8">
                <table class="table table-bordered">
                    <tbody>
                        <tr class="table-light"><td colspan="2"><strong>REVENUE</strong></td></tr>
                        <tr><td class="ps-4">Sales Revenue</td><td class="text-end"><?= formatCurrency($revenue) ?></td></tr>
                        <tr class="table-light"><td><strong>Total Revenue</strong></td><td class="text-end"><strong><?= formatCurrency($revenue) ?></strong></td></tr>

                        <tr class="table-light"><td colspan="2"><strong>COST OF GOODS SOLD</strong></td></tr>
                        <tr><td class="ps-4">Purchase Costs</td><td class="text-end"><?= formatCurrency($cogs) ?></td></tr>
                        <tr class="table-light"><td><strong>Total COGS</strong></td><td class="text-end"><strong><?= formatCurrency($cogs) ?></strong></td></tr>

                        <tr class="<?= $grossProfit >= 0 ? 'table-success' : 'table-danger' ?>"><td><strong>GROSS PROFIT</strong></td><td class="text-end"><strong><?= formatCurrency($grossProfit) ?></strong></td></tr>

                        <tr class="table-light"><td colspan="2"><strong>OPERATING EXPENSES</strong></td></tr>
                        <tr><td class="ps-4">Journal Expenses</td><td class="text-end"><?= formatCurrency($expenses) ?></td></tr>
                        <tr class="table-light"><td><strong>Total Expenses</strong></td><td class="text-end"><strong><?= formatCurrency($expenses) ?></strong></td></tr>

                        <tr class="<?= $netProfit >= 0 ? 'table-success' : 'table-danger' ?>" style="font-size:1.1rem"><td><strong>NET PROFIT / (LOSS)</strong></td><td class="text-end"><strong><?= formatCurrency($netProfit) ?></strong></td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php elseif ($type === 'customers'):
    $customers = $db->query("SELECT c.*, COUNT(so.id) as order_count, COALESCE(SUM(so.total),0) as total_spent, MAX(so.order_date) as last_order FROM customers c LEFT JOIN sales_orders so ON c.id=so.customer_id AND so.status != 'cancelled' GROUP BY c.id ORDER BY total_spent DESC LIMIT 20")->fetchAll();
?>
<div class="card">
    <div class="card-header"><i class="fas fa-users me-2 text-info"></i>Top Customers Report</div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered table-hover">
                <thead class="table-light"><tr><th>#</th><th>Customer</th><th>Company</th><th class="text-center">Orders</th><th class="text-end">Total Spent</th><th>Last Order</th></tr></thead>
                <tbody>
                <?php foreach ($customers as $i => $c): ?>
                <tr>
                    <td><?= $i+1 ?></td>
                    <td><a href="<?= BASE_URL ?>customers.php?action=view&id=<?= $c['id'] ?>"><?= sanitize($c['name']) ?></a></td>
                    <td><?= sanitize($c['company'] ?? '-') ?></td>
                    <td class="text-center"><?= $c['order_count'] ?></td>
                    <td class="text-end"><strong><?= formatCurrency($c['total_spent']) ?></strong></td>
                    <td><?= $c['last_order'] ? formatDate($c['last_order']) : '-' ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php elseif ($type === 'employees'):
    $month = $_GET['month'] ?? date('Y-m');
    $employees = $db->query("SELECT e.*,
        (SELECT COUNT(*) FROM attendance a WHERE a.employee_id=e.id AND DATE_FORMAT(a.date,'%Y-%m')='$month' AND a.status='present') as present_days,
        (SELECT COUNT(*) FROM attendance a WHERE a.employee_id=e.id AND DATE_FORMAT(a.date,'%Y-%m')='$month') as total_days,
        (SELECT COALESCE(SUM(l.days),0) FROM leaves l WHERE l.employee_id=e.id AND l.status='approved' AND YEAR(l.start_date)=YEAR(NOW())) as leaves_taken
        FROM employees e WHERE e.status='active' ORDER BY e.full_name")->fetchAll();
?>
<div class="card">
    <div class="card-header"><i class="fas fa-id-badge me-2 text-purple"></i>Employee Report</div>
    <div class="card-body">
        <form class="row g-2 mb-4">
            <input type="hidden" name="type" value="employees">
            <div class="col-md-3"><label class="form-label">Month</label><input type="month" name="month" class="form-control form-control-sm" value="<?= $month ?>"></div>
            <div class="col-md-2 d-flex align-items-end"><button class="btn btn-sm btn-primary">Generate</button></div>
        </form>
        <div class="table-responsive">
            <table class="table table-bordered table-hover">
                <thead class="table-light"><tr><th>Employee</th><th>Department</th><th>Position</th><th class="text-center">Present Days</th><th class="text-center">Attendance Rate</th><th class="text-center">Leaves Taken (Year)</th></tr></thead>
                <tbody>
                <?php foreach ($employees as $e):
                    $rate = $e['total_days'] > 0 ? round(($e['present_days'] / $e['total_days']) * 100) : 0;
                ?>
                <tr>
                    <td><?= sanitize($e['employee_id'].' - '.$e['full_name']) ?></td>
                    <td><?= sanitize($e['department'] ?? '-') ?></td>
                    <td><?= sanitize($e['position'] ?? '-') ?></td>
                    <td class="text-center"><?= $e['present_days'] ?> / <?= $e['total_days'] ?></td>
                    <td class="text-center">
                        <div class="progress" style="height:20px">
                            <div class="progress-bar <?= $rate >= 80 ? 'bg-success' : ($rate >= 60 ? 'bg-warning' : 'bg-danger') ?>" style="width:<?= $rate ?>%"><?= $rate ?>%</div>
                        </div>
                    </td>
                    <td class="text-center"><?= $e['leaves_taken'] ?> days</td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php endif; ?>

<?php else: // Report cards grid ?>
<div class="row g-4">
    <?php
    $reports = [
        ['type' => 'sales', 'icon' => 'fas fa-chart-line', 'color' => 'primary', 'title' => 'Sales Report', 'desc' => 'Monthly sales summary with charts and totals'],
        ['type' => 'purchases', 'icon' => 'fas fa-truck', 'color' => 'warning', 'title' => 'Purchase Report', 'desc' => 'Monthly purchase summary and trends'],
        ['type' => 'inventory', 'icon' => 'fas fa-boxes-stacked', 'color' => 'info', 'title' => 'Inventory Report', 'desc' => 'Current stock levels and inventory value'],
        ['type' => 'profit_loss', 'icon' => 'fas fa-calculator', 'color' => 'success', 'title' => 'Profit & Loss', 'desc' => 'Revenue, expenses, and net profit statement'],
        ['type' => 'customers', 'icon' => 'fas fa-users', 'color' => 'purple', 'title' => 'Customer Report', 'desc' => 'Top customers ranked by total spending'],
        ['type' => 'employees', 'icon' => 'fas fa-id-badge', 'color' => 'danger', 'title' => 'Employee Report', 'desc' => 'Attendance rates and leave summary'],
    ];
    foreach ($reports as $r): ?>
    <div class="col-md-4">
        <div class="card h-100" style="border-left: 4px solid var(--bs-<?= $r['color'] ?>)">
            <div class="card-body">
                <div class="d-flex align-items-center mb-3">
                    <div class="rounded-circle bg-<?= $r['color'] ?> bg-opacity-10 p-3 me-3">
                        <i class="<?= $r['icon'] ?> text-<?= $r['color'] ?> fa-lg"></i>
                    </div>
                    <h6 class="mb-0"><?= $r['title'] ?></h6>
                </div>
                <p class="text-muted mb-3" style="font-size:0.85rem"><?= $r['desc'] ?></p>
                <a href="<?= BASE_URL ?>reports.php?type=<?= $r['type'] ?>" class="btn btn-sm btn-outline-<?= $r['color'] ?>"><i class="fas fa-chart-bar me-1"></i>Generate</a>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
