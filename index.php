<?php $pageTitle = 'Dashboard'; require_once __DIR__ . '/includes/header.php'; ?>
<?php
$db = getDB();

// Stats
$totalCustomers = $db->query("SELECT COUNT(*) FROM customers WHERE status='active'")->fetchColumn();
$totalProducts = $db->query("SELECT COUNT(*) FROM products WHERE status='active'")->fetchColumn();
$totalEmployees = $db->query("SELECT COUNT(*) FROM employees WHERE status='active'")->fetchColumn();
$totalSalesOrders = $db->query("SELECT COUNT(*) FROM sales_orders")->fetchColumn();

// Revenue (from paid invoices this month)
$monthStart = date('Y-m-01');
$monthEnd = date('Y-m-t');
$stmt = $db->prepare("SELECT COALESCE(SUM(total), 0) FROM sales_orders WHERE order_date BETWEEN ? AND ?");
$stmt->execute([$monthStart, $monthEnd]);
$monthlyRevenue = $stmt->fetchColumn();

$stmt = $db->prepare("SELECT COALESCE(SUM(total), 0) FROM purchase_orders WHERE order_date BETWEEN ? AND ?");
$stmt->execute([$monthStart, $monthEnd]);
$monthlyExpenses = $stmt->fetchColumn();

$netProfit = $monthlyRevenue - $monthlyExpenses;

// Monthly sales data for chart (last 6 months)
$chartData = [];
for ($i = 5; $i >= 0; $i--) {
    $m = date('Y-m', strtotime("-$i months"));
    $stmt = $db->prepare("SELECT COALESCE(SUM(total), 0) FROM sales_orders WHERE DATE_FORMAT(order_date, '%Y-%m') = ?");
    $stmt->execute([$m]);
    $chartData[] = ['month' => date('M Y', strtotime("-$i months")), 'total' => (float)$stmt->fetchColumn()];
}

// Recent sales orders
$recentSales = $db->query("SELECT so.*, c.name as customer_name FROM sales_orders so LEFT JOIN customers c ON so.customer_id = c.id ORDER BY so.created_at DESC LIMIT 5")->fetchAll();

// Recent purchase orders
$recentPurchases = $db->query("SELECT po.*, s.name as supplier_name FROM purchase_orders po LEFT JOIN suppliers s ON po.supplier_id = s.id ORDER BY po.created_at DESC LIMIT 5")->fetchAll();

// Low stock products
$lowStock = $db->query("SELECT * FROM products WHERE stock <= min_stock AND min_stock > 0 AND status = 'active' LIMIT 5")->fetchAll();
?>

<!-- Stats Row -->
<div class="row g-3 mb-4">
    <div class="col-xl-4 col-md-6">
        <div class="stat-card bg-primary-gradient">
            <div class="stat-value"><?= formatCurrency($monthlyRevenue) ?></div>
            <div class="stat-label">Monthly Revenue</div>
            <i class="fas fa-chart-line stat-icon"></i>
        </div>
    </div>
    <div class="col-xl-4 col-md-6">
        <div class="stat-card bg-warning-gradient">
            <div class="stat-value"><?= formatCurrency($monthlyExpenses) ?></div>
            <div class="stat-label">Monthly Expenses</div>
            <i class="fas fa-receipt stat-icon"></i>
        </div>
    </div>
    <div class="col-xl-4 col-md-6">
        <div class="stat-card <?= $netProfit >= 0 ? 'bg-success-gradient' : 'bg-danger-gradient' ?>">
            <div class="stat-value"><?= formatCurrency($netProfit) ?></div>
            <div class="stat-label">Net Profit</div>
            <i class="fas fa-wallet stat-icon"></i>
        </div>
    </div>
    <div class="col-xl-4 col-md-6">
        <div class="stat-card bg-info-gradient">
            <div class="stat-value"><?= number_format($totalCustomers) ?></div>
            <div class="stat-label">Active Customers</div>
            <i class="fas fa-users stat-icon"></i>
        </div>
    </div>
    <div class="col-xl-4 col-md-6">
        <div class="stat-card bg-purple-gradient">
            <div class="stat-value"><?= number_format($totalProducts) ?></div>
            <div class="stat-label">Active Products</div>
            <i class="fas fa-boxes-stacked stat-icon"></i>
        </div>
    </div>
    <div class="col-xl-4 col-md-6">
        <div class="stat-card bg-danger-gradient">
            <div class="stat-value"><?= number_format($totalEmployees) ?></div>
            <div class="stat-label">Employees</div>
            <i class="fas fa-id-badge stat-icon"></i>
        </div>
    </div>
</div>

<!-- Quick Actions -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-body py-3">
                <div class="d-flex flex-wrap gap-2">
                    <a href="<?= BASE_URL ?>sales_orders.php?action=create" class="btn btn-primary btn-sm"><i class="fas fa-plus me-1"></i> New Sale</a>
                    <a href="<?= BASE_URL ?>purchase_orders.php?action=create" class="btn btn-outline-primary btn-sm"><i class="fas fa-plus me-1"></i> New Purchase</a>
                    <a href="<?= BASE_URL ?>customers.php?action=create" class="btn btn-outline-success btn-sm"><i class="fas fa-user-plus me-1"></i> Add Customer</a>
                    <a href="<?= BASE_URL ?>products.php?action=create" class="btn btn-outline-info btn-sm"><i class="fas fa-box me-1"></i> Add Product</a>
                    <a href="<?= BASE_URL ?>invoices.php?action=create" class="btn btn-outline-warning btn-sm"><i class="fas fa-file-invoice me-1"></i> New Invoice</a>
                    <a href="<?= BASE_URL ?>reports.php" class="btn btn-outline-secondary btn-sm"><i class="fas fa-chart-bar me-1"></i> Reports</a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Sales Chart -->
<div class="row mb-4">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fas fa-chart-area me-2 text-primary"></i>Sales Overview</span>
                <span class="text-muted" style="font-size:0.8rem">Last 6 months</span>
            </div>
            <div class="card-body">
                <canvas id="salesChart" height="100"></canvas>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header"><i class="fas fa-exclamation-triangle me-2 text-warning"></i>Low Stock Alert</div>
            <div class="card-body p-0">
                <?php if (empty($lowStock)): ?>
                    <div class="text-center text-muted py-4"><i class="fas fa-check-circle text-success"></i><br><small>All stock levels OK</small></div>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($lowStock as $p): ?>
                        <div class="list-group-item d-flex justify-content-between align-items-center py-3">
                            <div>
                                <div style="font-size:0.85rem;font-weight:500"><?= sanitize($p['name']) ?></div>
                                <small class="text-muted"><?= sanitize($p['code']) ?></small>
                            </div>
                            <span class="badge bg-danger"><?= $p['stock'] ?> / <?= $p['min_stock'] ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Recent Orders -->
<div class="row">
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fas fa-shopping-cart me-2 text-success"></i>Recent Sales Orders</span>
                <a href="<?= BASE_URL ?>sales_orders.php" class="btn btn-sm btn-outline-primary">View All</a>
            </div>
            <div class="card-body p-0">
                <?php if (empty($recentSales)): ?>
                    <div class="empty-state py-4"><i class="fas fa-inbox"></i><br><small>No sales orders yet</small></div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead><tr><th>Order #</th><th>Customer</th><th>Total</th><th>Status</th></tr></thead>
                        <tbody>
                        <?php foreach ($recentSales as $so): ?>
                            <tr>
                                <td><a href="<?= BASE_URL ?>sales_orders.php?action=view&id=<?= $so['id'] ?>"><?= sanitize($so['order_number']) ?></a></td>
                                <td><?= sanitize($so['customer_name'] ?? '-') ?></td>
                                <td><?= formatCurrency($so['total']) ?></td>
                                <td><span class="badge status-<?= $so['status'] ?>"><?= ucfirst($so['status']) ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fas fa-truck me-2 text-warning"></i>Recent Purchase Orders</span>
                <a href="<?= BASE_URL ?>purchase_orders.php" class="btn btn-sm btn-outline-primary">View All</a>
            </div>
            <div class="card-body p-0">
                <?php if (empty($recentPurchases)): ?>
                    <div class="empty-state py-4"><i class="fas fa-inbox"></i><br><small>No purchase orders yet</small></div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead><tr><th>Order #</th><th>Supplier</th><th>Total</th><th>Status</th></tr></thead>
                        <tbody>
                        <?php foreach ($recentPurchases as $po): ?>
                            <tr>
                                <td><a href="<?= BASE_URL ?>purchase_orders.php?action=view&id=<?= $po['id'] ?>"><?= sanitize($po['order_number']) ?></a></td>
                                <td><?= sanitize($po['supplier_name'] ?? '-') ?></td>
                                <td><?= formatCurrency($po['total']) ?></td>
                                <td><span class="badge status-<?= $po['status'] ?>"><?= ucfirst($po['status']) ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('salesChart').getContext('2d');
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: <?= json_encode(array_column($chartData, 'month')) ?>,
            datasets: [{
                label: 'Sales Revenue',
                data: <?= json_encode(array_column($chartData, 'total')) ?>,
                borderColor: '#3b82f6',
                backgroundColor: 'rgba(59, 130, 246, 0.1)',
                borderWidth: 2,
                fill: true,
                tension: 0.4,
                pointBackgroundColor: '#3b82f6',
                pointRadius: 4,
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: function(ctx) { return 'Rp ' + ctx.raw.toLocaleString('id-ID'); }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: { callback: function(v) { return 'Rp ' + (v/1000000).toFixed(0) + 'M'; } },
                    grid: { color: '#f1f5f9' }
                },
                x: { grid: { display: false } }
            }
        }
    });
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
