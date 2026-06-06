<?php $pageTitle = 'Leave Management'; require_once __DIR__ . '/includes/header.php'; ?>
<?php
$db = getDB();
$action = $_GET['action'] ?? 'list';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'store') {
        $start = $_POST['start_date'];
        $end = $_POST['end_date'];
        $days = max(1, (int)((strtotime($end) - strtotime($start)) / 86400) + 1);
        $stmt = $db->prepare("INSERT INTO leaves (employee_id, leave_type, start_date, end_date, days, reason, status) VALUES (?,?,?,?,?,?,'pending')");
        $stmt->execute([$_POST['employee_id'], $_POST['leave_type'], $start, $end, $days, $_POST['reason']]);
        setFlash('success', 'Leave request submitted successfully');
        header('Location: ' . BASE_URL . 'leaves_page.php'); exit;
    } elseif ($action === 'approve' && isset($_GET['id'])) {
        $db->prepare("UPDATE leaves SET status='approved', approved_by=? WHERE id=? AND status='pending'")->execute([$_SESSION['user_id'], $_GET['id']]);
        setFlash('success', 'Leave request approved');
        header('Location: ' . BASE_URL . 'leaves_page.php'); exit;
    } elseif ($action === 'reject' && isset($_GET['id'])) {
        $db->prepare("UPDATE leaves SET status='rejected', approved_by=? WHERE id=? AND status='pending'")->execute([$_SESSION['user_id'], $_GET['id']]);
        setFlash('success', 'Leave request rejected');
        header('Location: ' . BASE_URL . 'leaves_page.php'); exit;
    }
}

if ($action === 'create'):
    $employees = $db->query("SELECT id, employee_id, full_name FROM employees WHERE status='active' ORDER BY full_name")->fetchAll();
?>
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fas fa-plus me-2"></i>New Leave Request</span>
        <a href="<?= BASE_URL ?>leaves_page.php" class="btn btn-sm btn-outline-secondary">Back</a>
    </div>
    <div class="card-body">
        <form method="POST" action="<?= BASE_URL ?>leaves_page.php?action=store">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Employee *</label>
                    <select name="employee_id" class="form-select" required>
                        <option value="">Select Employee</option>
                        <?php foreach ($employees as $e): ?>
                        <option value="<?= $e['id'] ?>"><?= sanitize($e['employee_id'].' - '.$e['full_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Leave Type *</label>
                    <select name="leave_type" class="form-select" required>
                        <option value="annual">Annual Leave</option>
                        <option value="sick">Sick Leave</option>
                        <option value="personal">Personal Leave</option>
                        <option value="maternity">Maternity Leave</option>
                        <option value="other">Other</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Start Date *</label>
                    <input type="date" name="start_date" id="start_date" class="form-control" required value="<?= date('Y-m-d') ?>" onchange="calcDays()">
                </div>
                <div class="col-md-2">
                    <label class="form-label">End Date *</label>
                    <input type="date" name="end_date" id="end_date" class="form-control" required value="<?= date('Y-m-d') ?>" onchange="calcDays()">
                </div>
                <div class="col-md-1">
                    <label class="form-label">Days</label>
                    <input type="text" id="days_display" class="form-control" readonly value="1">
                </div>
                <div class="col-12">
                    <label class="form-label">Reason</label>
                    <textarea name="reason" class="form-control" rows="3" placeholder="Reason for leave..."></textarea>
                </div>
            </div>
            <div class="mt-3"><button type="submit" class="btn btn-primary"><i class="fas fa-paper-plane me-1"></i>Submit Request</button></div>
        </form>
    </div>
</div>
<script>
function calcDays() {
    const s = new Date(document.getElementById('start_date').value);
    const e = new Date(document.getElementById('end_date').value);
    if (s && e && e >= s) {
        const days = Math.round((e - s) / 86400000) + 1;
        document.getElementById('days_display').value = days;
    }
}
</script>

<?php else: // LIST
    $where = []; $params = [];
    if (!empty($_GET['status'])) { $where[] = "l.status = ?"; $params[] = $_GET['status']; }
    if (!empty($_GET['employee_id'])) { $where[] = "l.employee_id = ?"; $params[] = $_GET['employee_id']; }
    $whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    $total = $db->prepare("SELECT COUNT(*) FROM leaves l $whereSQL"); $total->execute($params); $total = $total->fetchColumn();
    $page = max(1, (int)($_GET['page'] ?? 1)); $perPage = 20; $pages = max(1, ceil($total/$perPage)); $offset = ($page-1)*$perPage;

    $stmt = $db->prepare("SELECT l.*, e.full_name, e.employee_id as emp_code, u.full_name as approver_name FROM leaves l LEFT JOIN employees e ON l.employee_id=e.id LEFT JOIN users u ON l.approved_by=u.id $whereSQL ORDER BY l.created_at DESC LIMIT $perPage OFFSET $offset");
    $stmt->execute($params); $leaves = $stmt->fetchAll();

    // Summary
    $pending = $db->query("SELECT COUNT(*) FROM leaves WHERE status='pending'")->fetchColumn();
    $approved = $db->query("SELECT COUNT(*) FROM leaves WHERE status='approved' AND MONTH(start_date)=MONTH(NOW())")->fetchColumn();
?>

<div class="row g-3 mb-4">
    <div class="col-md-4"><div class="stat-card bg-warning-gradient py-3 px-3"><div class="stat-value"><?= $pending ?></div><div class="stat-label">Pending Requests</div></div></div>
    <div class="col-md-4"><div class="stat-card bg-success-gradient py-3 px-3"><div class="stat-value"><?= $approved ?></div><div class="stat-label">Approved This Month</div></div></div>
    <div class="col-md-4"><div class="stat-card bg-primary-gradient py-3 px-3"><div class="stat-value"><?= $total ?></div><div class="stat-label">Total Requests</div></div></div>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fas fa-calendar-check me-2"></i>Leave Requests (<?= $total ?>)</span>
        <a href="<?= BASE_URL ?>leaves_page.php?action=create" class="btn btn-sm btn-primary"><i class="fas fa-plus me-1"></i>New Request</a>
    </div>
    <div class="card-body">
        <form class="row g-2 mb-3">
            <div class="col-md-3">
                <select name="status" class="form-select form-select-sm">
                    <option value="">All Status</option>
                    <?php foreach (['pending','approved','rejected'] as $st): ?>
                    <option value="<?= $st ?>" <?= ($_GET['status'] ?? '') === $st ? 'selected' : '' ?>><?= ucfirst($st) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2"><button class="btn btn-sm btn-primary w-100">Filter</button></div>
        </form>

        <?php if (empty($leaves)): ?>
            <div class="empty-state"><i class="fas fa-inbox"></i><p>No leave requests found</p></div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead><tr><th>Employee</th><th>Type</th><th>Start</th><th>End</th><th>Days</th><th>Reason</th><th>Status</th><th>Actions</th></tr></thead>
                <tbody>
                <?php foreach ($leaves as $l): ?>
                <tr>
                    <td><?= sanitize($l['emp_code'].' - '.$l['full_name']) ?></td>
                    <td><span class="badge bg-secondary"><?= ucfirst($l['leave_type']) ?></span></td>
                    <td><?= formatDate($l['start_date']) ?></td>
                    <td><?= formatDate($l['end_date']) ?></td>
                    <td><?= $l['days'] ?></td>
                    <td><?= sanitize($l['reason'] ?: '-') ?></td>
                    <td><span class="badge status-<?= $l['status'] ?>"><?= ucfirst($l['status']) ?></span></td>
                    <td>
                        <?php if ($l['status'] === 'pending'): ?>
                        <form method="POST" action="<?= BASE_URL ?>leaves_page.php?action=approve&id=<?= $l['id'] ?>" class="d-inline">
                            <button class="btn btn-sm btn-outline-success" title="Approve"><i class="fas fa-check"></i></button>
                        </form>
                        <form method="POST" action="<?= BASE_URL ?>leaves_page.php?action=reject&id=<?= $l['id'] ?>" class="d-inline">
                            <button class="btn btn-sm btn-outline-danger" title="Reject"><i class="fas fa-times"></i></button>
                        </form>
                        <?php else: ?>
                        <small class="text-muted"><?= $l['approver_name'] ? 'By '.sanitize($l['approver_name']) : '' ?></small>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php if ($pages > 1): ?>
        <nav><ul class="pagination pagination-sm justify-content-center">
            <?php for ($i=1; $i<=$pages; $i++): ?>
            <li class="page-item <?= $i===$page?'active':'' ?>"><a class="page-link" href="?page=<?= $i ?>&status=<?= urlencode($_GET['status']??'') ?>"><?= $i ?></a></li>
            <?php endfor; ?>
        </ul></nav>
        <?php endif; endif; ?>
    </div>
</div>
<?php endif; ?>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
