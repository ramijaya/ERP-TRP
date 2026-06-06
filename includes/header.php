<?php require_once __DIR__ . '/../config/app.php'; requireLogin(); $currentUser = getCurrentUser(); $flash = getFlash(); ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?? 'Dashboard' ?> - <?= APP_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --sidebar-width: 260px;
            --sidebar-bg: #1e293b;
            --sidebar-hover: #334155;
            --sidebar-active: #3b82f6;
            --header-height: 60px;
            --body-bg: #f1f5f9;
            --card-shadow: 0 1px 3px rgba(0,0,0,0.08);
        }
        * { font-family: 'Inter', sans-serif; }
        body { background: var(--body-bg); overflow-x: hidden; }

        .sidebar {
            position: fixed; top: 0; left: 0; bottom: 0;
            width: var(--sidebar-width);
            background: var(--sidebar-bg);
            z-index: 1000;
            transition: transform 0.3s ease;
            overflow-y: auto;
        }
        .sidebar-brand {
            height: var(--header-height);
            display: flex; align-items: center; padding: 0 20px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        .sidebar-brand h4 { color: #fff; margin: 0; font-weight: 700; font-size: 1.2rem; }
        .sidebar-brand span { color: var(--sidebar-active); }

        .sidebar-menu { padding: 15px 0; }
        .sidebar-menu .menu-label {
            color: #64748b; font-size: 0.7rem; font-weight: 600;
            text-transform: uppercase; letter-spacing: 1px;
            padding: 10px 20px 5px; margin-top: 5px;
        }
        .sidebar-menu a {
            display: flex; align-items: center; padding: 10px 20px;
            color: #94a3b8; text-decoration: none; font-size: 0.875rem;
            transition: all 0.2s; border-left: 3px solid transparent;
        }
        .sidebar-menu a:hover { background: var(--sidebar-hover); color: #e2e8f0; }
        .sidebar-menu a.active {
            background: rgba(59,130,246,0.1); color: #fff;
            border-left-color: var(--sidebar-active);
        }
        .sidebar-menu a i { width: 20px; margin-right: 12px; font-size: 0.9rem; text-align: center; }
        .sidebar-menu .badge { font-size: 0.65rem; margin-left: auto; }

        .main-content {
            margin-left: var(--sidebar-width);
            min-height: 100vh;
        }
        .top-header {
            height: var(--header-height);
            background: #fff;
            border-bottom: 1px solid #e2e8f0;
            display: flex; align-items: center; justify-content: space-between;
            padding: 0 24px;
            position: sticky; top: 0; z-index: 999;
        }
        .top-header .page-title { font-size: 1.1rem; font-weight: 600; color: #1e293b; }
        .top-header .user-menu { display: flex; align-items: center; gap: 15px; }
        .top-header .user-avatar {
            width: 36px; height: 36px; border-radius: 50%;
            background: var(--sidebar-active); color: #fff;
            display: flex; align-items: center; justify-content: center;
            font-weight: 600; font-size: 0.85rem;
        }

        .content-wrapper { padding: 24px; }

        .card {
            border: none; border-radius: 12px;
            box-shadow: var(--card-shadow);
            margin-bottom: 20px;
        }
        .card-header {
            background: #fff; border-bottom: 1px solid #f1f5f9;
            font-weight: 600; padding: 16px 20px;
            border-radius: 12px 12px 0 0 !important;
        }

        .stat-card {
            border-radius: 12px; padding: 20px;
            color: #fff; position: relative; overflow: hidden;
        }
        .stat-card .stat-icon {
            position: absolute; right: 15px; top: 50%;
            transform: translateY(-50%); font-size: 3rem; opacity: 0.2;
        }
        .stat-card .stat-value { font-size: 1.8rem; font-weight: 700; }
        .stat-card .stat-label { font-size: 0.8rem; opacity: 0.9; margin-top: 2px; }
        .stat-card.bg-primary-gradient { background: linear-gradient(135deg, #3b82f6, #1d4ed8); }
        .stat-card.bg-success-gradient { background: linear-gradient(135deg, #22c55e, #16a34a); }
        .stat-card.bg-warning-gradient { background: linear-gradient(135deg, #f59e0b, #d97706); }
        .stat-card.bg-danger-gradient { background: linear-gradient(135deg, #ef4444, #dc2626); }
        .stat-card.bg-info-gradient { background: linear-gradient(135deg, #06b6d4, #0891b2); }
        .stat-card.bg-purple-gradient { background: linear-gradient(135deg, #8b5cf6, #7c3aed); }

        .table th { font-weight: 600; font-size: 0.8rem; text-transform: uppercase; color: #64748b; letter-spacing: 0.5px; }
        .table td { vertical-align: middle; font-size: 0.875rem; }

        .btn { border-radius: 8px; font-size: 0.875rem; font-weight: 500; }
        .btn-primary { background: #3b82f6; border-color: #3b82f6; }
        .btn-primary:hover { background: #2563eb; border-color: #2563eb; }

        .badge { border-radius: 6px; font-weight: 500; padding: 5px 10px; }

        .status-draft { background: #f1f5f9; color: #64748b; }
        .status-confirmed, .status-active, .status-present, .status-paid, .status-approved, .status-posted { background: #dcfce7; color: #16a34a; }
        .status-processing, .status-ordered, .status-partial, .status-pending { background: #fef3c7; color: #d97706; }
        .status-shipped, .status-sent { background: #dbeafe; color: #2563eb; }
        .status-delivered, .status-received { background: #d1fae5; color: #059669; }
        .status-cancelled, .status-inactive, .status-rejected, .status-void, .status-overdue, .status-terminated { background: #fee2e2; color: #dc2626; }
        .status-absent, .status-unpaid { background: #fee2e2; color: #dc2626; }
        .status-late { background: #ffedd5; color: #ea580c; }
        .status-leave, .status-sick { background: #e0e7ff; color: #4f46e5; }

        .empty-state { text-align: center; padding: 60px 20px; color: #94a3b8; }
        .empty-state i { font-size: 3rem; margin-bottom: 15px; }

        .form-control, .form-select { border-radius: 8px; border-color: #e2e8f0; font-size: 0.875rem; }
        .form-control:focus, .form-select:focus { border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59,130,246,0.1); }
        .form-label { font-weight: 500; font-size: 0.85rem; color: #475569; }

        .sidebar-toggle { display: none; background: none; border: none; font-size: 1.2rem; color: #475569; cursor: pointer; }

        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); }
            .sidebar.show { transform: translateX(0); }
            .main-content { margin-left: 0; }
            .sidebar-toggle { display: block; }
        }
    </style>
</head>
<body>
<?php
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
$menuItems = [
    ['label' => 'MAIN', 'items' => [
        ['page' => 'index', 'icon' => 'fas fa-tachometer-alt', 'text' => 'Dashboard'],
    ]],
    ['label' => 'SALES', 'items' => [
        ['page' => 'customers', 'icon' => 'fas fa-users', 'text' => 'Customers'],
        ['page' => 'sales_orders', 'icon' => 'fas fa-shopping-cart', 'text' => 'Sales Orders'],
        ['page' => 'invoices', 'icon' => 'fas fa-file-invoice', 'text' => 'Invoices'],
    ]],
    ['label' => 'PURCHASING', 'items' => [
        ['page' => 'suppliers', 'icon' => 'fas fa-truck', 'text' => 'Suppliers'],
        ['page' => 'purchase_orders', 'icon' => 'fas fa-clipboard-list', 'text' => 'Purchase Orders'],
    ]],
    ['label' => 'INVENTORY', 'items' => [
        ['page' => 'products', 'icon' => 'fas fa-boxes-stacked', 'text' => 'Products'],
        ['page' => 'categories', 'icon' => 'fas fa-tags', 'text' => 'Categories'],
        ['page' => 'stock', 'icon' => 'fas fa-warehouse', 'text' => 'Stock Movement'],
    ]],
    ['label' => 'ACCOUNTING', 'items' => [
        ['page' => 'accounts', 'icon' => 'fas fa-building-columns', 'text' => 'Chart of Accounts'],
        ['page' => 'journals', 'icon' => 'fas fa-book', 'text' => 'Journal Entries'],
        ['page' => 'payments', 'icon' => 'fas fa-money-bill-wave', 'text' => 'Payments'],
    ]],
    ['label' => 'HR', 'items' => [
        ['page' => 'employees', 'icon' => 'fas fa-id-badge', 'text' => 'Employees'],
        ['page' => 'attendance', 'icon' => 'fas fa-clock', 'text' => 'Attendance'],
        ['page' => 'leaves_page', 'icon' => 'fas fa-calendar-check', 'text' => 'Leave Management'],
    ]],
    ['label' => 'REPORTS', 'items' => [
        ['page' => 'reports', 'icon' => 'fas fa-chart-bar', 'text' => 'Reports'],
    ]],
    ['label' => 'SETTINGS', 'items' => [
        ['page' => 'settings', 'icon' => 'fas fa-cog', 'text' => 'Settings'],
    ]],
];
?>

<div class="sidebar" id="sidebar">
    <div class="sidebar-brand">
        <h4><span>ERP</span>-TRP</h4>
    </div>
    <div class="sidebar-menu">
        <?php foreach ($menuItems as $group): ?>
            <div class="menu-label"><?= $group['label'] ?></div>
            <?php foreach ($group['items'] as $item): ?>
                <a href="<?= BASE_URL . $item['page'] ?>.php" class="<?= $currentPage === $item['page'] ? 'active' : '' ?>">
                    <i class="<?= $item['icon'] ?>"></i> <?= $item['text'] ?>
                </a>
            <?php endforeach; ?>
        <?php endforeach; ?>
        <div class="menu-label">ACCOUNT</div>
        <a href="<?= BASE_URL ?>logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>
</div>

<div class="main-content">
    <div class="top-header">
        <div class="d-flex align-items-center gap-3">
            <button class="sidebar-toggle" onclick="document.getElementById('sidebar').classList.toggle('show')">
                <i class="fas fa-bars"></i>
            </button>
            <span class="page-title"><?= $pageTitle ?? 'Dashboard' ?></span>
        </div>
        <div class="user-menu">
            <span class="text-muted" style="font-size:0.85rem"><?= date('l, d M Y') ?></span>
            <div class="dropdown">
                <a href="#" class="d-flex align-items-center gap-2 text-decoration-none dropdown-toggle" data-bs-toggle="dropdown">
                    <div class="user-avatar"><?= strtoupper(substr($currentUser['full_name'] ?? 'A', 0, 1)) ?></div>
                    <div>
                        <div style="font-size:0.85rem;font-weight:600;color:#1e293b"><?= sanitize($currentUser['full_name'] ?? 'Admin') ?></div>
                        <div style="font-size:0.7rem;color:#94a3b8"><?= ucfirst($currentUser['role'] ?? 'admin') ?></div>
                    </div>
                </a>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" href="<?= BASE_URL ?>settings.php"><i class="fas fa-cog me-2"></i>Settings</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item text-danger" href="<?= BASE_URL ?>logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                </ul>
            </div>
        </div>
    </div>
    <div class="content-wrapper">
        <?php if ($flash): ?>
        <div class="alert alert-<?= $flash['type'] === 'error' ? 'danger' : $flash['type'] ?> alert-dismissible fade show" role="alert">
            <?= $flash['message'] ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>
