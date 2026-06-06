<?php $pageTitle = 'Attendance'; require_once __DIR__ . '/includes/header.php'; ?>
<?php
$db = getDB();
$action = $_GET['action'] ?? 'list';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'store') {
        $stmt = $db->prepare("INSERT INTO attendance (employee_id, date, check_in, check_out, status, notes) VALUES (?,?,?,?,?,?) ON DUPLICATE KEY UPDATE check_in=VALUES(check_in), check_out=VALUES(check_out), status=VALUES(status), notes=VALUES(notes)");
        $stmt->execute([$_POST['employee_id'], $_POST['date'], $_POST['check_in'] ?: null, $_POST['check_out'] ?: null, $_POST['status'], $_POST['notes']]);
        setFlash('success', 'Attendance recorded successfully');
        header('Location: ' . BASE_URL . 'attendance.php?date=' . $_POST['date']); exit;
    } elseif ($action === 'bulk_store') {
        $date = $_POST['date'];
        $stmt = $db->prepare("INSERT INTO attendance (employee_id, date, check_in, check_out, status, notes) VALUES (?,?,?,?,?,?) ON DUPLICATE KEY UPDATE check_in=VALUES(check_in), check_out=VALUES(check_out), status=VALUES(status), notes=VALUES(notes)");
        foreach ($_POST['employees'] as $empId => $data) {
            if (empty($data['status'])) continue;
            $stmt->execute([$empId, $date, $data['check_in'] ?: null, $data['check_out'] ?: null, $data['status'], $data['notes'] ?? '']);
        }
        setFlash('success', 'Bulk attendance saved successfully');
        header('Location: ' . BASE_URL . 'attendance.php?date=' . $date); exit;
    }
}

if ($action === 'create'):
    $employees = $db->query("SELECT id, employee_id, full_name, department FROM employees WHERE status='active' ORDER BY full_name")->fetchAll();
?>
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fas fa-plus me-2"></i>Record Attendance</span>
        <a href="<?= BASE_URL ?>attendance.php" class="btn btn-sm btn-outline-secondary">Back</a>
    </div>
    <div class="card-body">
        <form method="POST" action="<?= BASE_URL ?>attendance.php?action=store">
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Employee *</label>
                    <select name="employee_id" class="form-select" required>
                        <option value="">Select Employee</option>
                        <?php foreach ($employees as $e): ?>
                        <option value="<?= $e['id'] ?>"><?= sanitize($e['employee_id'].' - '.$e['full_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2"><label class="form-label">Date *</label><input type="date" name="date" class="form-control" value="<?= date('Y-m-d') ?>" required></div>
                <div class="col-md-2"><label class="form-label">Check In</label><input type="time" name="check_in" class="form-control" value="08:00"></div>
                <div class="col-md-2"><label class="form-label">Check Out</label><input type="time" name="check_out" class="form-control" value="17:00"></div>
                <div class="col-md-2">
                    <label class="form-label">Status *</label>
                    <select name="status" class="form-select" required>
                        <option value="present">Present</option>
                        <option value="absent">Absent</option>
                        <option value="late">Late</option>
                        <option value="leave">Leave</option>
                        <option value="sick">Sick</option>
                    </select>
                </div>
                <div class="col-md-3"><label class="form-label">Notes</label><input type="text" name="notes" class="form-control"></div>
            </div>
            <div class="mt-3"><button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>Save</button></div>
        </form>
    </div>
</div>

<?php elseif ($action === 'bulk'):
    $date = $_GET['date'] ?? date('Y-m-d');
    $employees = $db->query("SELECT id, employee_id, full_name, department FROM employees WHERE status='active' ORDER BY full_name")->fetchAll();
    $existing = [];
    $stmt = $db->prepare("SELECT * FROM attendance WHERE date = ?"); $stmt->execute([$date]);
    foreach ($stmt->fetchAll() as $a) { $existing[$a['employee_id']] = $a; }
?>
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fas fa-users me-2"></i>Bulk Attendance Entry</span>
        <a href="<?= BASE_URL ?>attendance.php" class="btn btn-sm btn-outline-secondary">Back</a>
    </div>
    <div class="card-body">
        <form method="POST" action="<?= BASE_URL ?>attendance.php?action=bulk_store">
            <div class="row g-2 mb-3">
                <div class="col-md-3">
                    <label class="form-label">Date</label>
                    <input type="date" name="date" class="form-control" value="<?= $date ?>" onchange="window.location='?action=bulk&date='+this.value">
                </div>
            </div>
            <div class="table-responsive">
                <table class="table table-bordered">
                    <thead class="table-light"><tr><th>Employee</th><th>Department</th><th width="100">Check In</th><th width="100">Check Out</th><th width="120">Status</th><th>Notes</th></tr></thead>
                    <tbody>
                    <?php foreach ($employees as $e): $ex = $existing[$e['id']] ?? null; ?>
                    <tr>
                        <td><?= sanitize($e['employee_id'].' - '.$e['full_name']) ?></td>
                        <td><?= sanitize($e['department'] ?? '-') ?></td>
                        <td><input type="time" name="employees[<?= $e['id'] ?>][check_in]" class="form-control form-control-sm" value="<?= $ex['check_in'] ?? '08:00' ?>"></td>
                        <td><input type="time" name="employees[<?= $e['id'] ?>][check_out]" class="form-control form-control-sm" value="<?= $ex['check_out'] ?? '17:00' ?>"></td>
                        <td><select name="employees[<?= $e['id'] ?>][status]" class="form-select form-select-sm">
                            <?php foreach (['present','absent','late','leave','sick'] as $st): ?>
                            <option value="<?= $st ?>" <?= ($ex['status'] ?? 'present') === $st ? 'selected' : '' ?>><?= ucfirst($st) ?></option>
                            <?php endforeach; ?>
                        </select></td>
                        <td><input type="text" name="employees[<?= $e['id'] ?>][notes]" class="form-control form-control-sm" value="<?= sanitize($ex['notes'] ?? '') ?>"></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>Save All</button>
        </form>
    </div>
</div>

<?php else: // LIST
    $date = $_GET['date'] ?? date('Y-m-d');
    $dept = $_GET['department'] ?? '';

    $deptWhere = $dept ? " AND e.department = " . $db->quote($dept) : '';
    $employees = $db->query("SELECT e.id, e.employee_id, e.full_name, e.department, a.check_in, a.check_out, a.status as att_status, a.notes as att_notes FROM employees e LEFT JOIN attendance a ON e.id = a.employee_id AND a.date = " . $db->quote($date) . " WHERE e.status='active' $deptWhere ORDER BY e.full_name")->fetchAll();

    $departments = $db->query("SELECT DISTINCT department FROM employees WHERE status='active' AND department IS NOT NULL AND department != '' ORDER BY department")->fetchAll(PDO::FETCH_COLUMN);

    // Summary
    $summary = ['present' => 0, 'absent' => 0, 'late' => 0, 'leave' => 0, 'sick' => 0, 'unmarked' => 0];
    foreach ($employees as $e) {
        if ($e['att_status']) $summary[$e['att_status']]++;
        else $summary['unmarked']++;
    }
?>

<!-- Summary stats -->
<div class="row g-3 mb-4">
    <div class="col"><div class="stat-card bg-success-gradient py-3 px-3"><div class="stat-value"><?= $summary['present'] ?></div><div class="stat-label">Present</div></div></div>
    <div class="col"><div class="stat-card bg-danger-gradient py-3 px-3"><div class="stat-value"><?= $summary['absent'] ?></div><div class="stat-label">Absent</div></div></div>
    <div class="col"><div class="stat-card bg-warning-gradient py-3 px-3"><div class="stat-value"><?= $summary['late'] ?></div><div class="stat-label">Late</div></div></div>
    <div class="col"><div class="stat-card bg-info-gradient py-3 px-3"><div class="stat-value"><?= $summary['leave'] + $summary['sick'] ?></div><div class="stat-label">Leave/Sick</div></div></div>
    <div class="col"><div class="stat-card bg-purple-gradient py-3 px-3"><div class="stat-value"><?= $summary['unmarked'] ?></div><div class="stat-label">Unmarked</div></div></div>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fas fa-clock me-2"></i>Attendance - <?= formatDate($date) ?></span>
        <div>
            <a href="<?= BASE_URL ?>attendance.php?action=bulk&date=<?= $date ?>" class="btn btn-sm btn-outline-primary"><i class="fas fa-users me-1"></i>Bulk Entry</a>
            <a href="<?= BASE_URL ?>attendance.php?action=create" class="btn btn-sm btn-primary"><i class="fas fa-plus me-1"></i>Record</a>
        </div>
    </div>
    <div class="card-body">
        <form class="row g-2 mb-3">
            <div class="col-md-3"><input type="date" name="date" class="form-control form-control-sm" value="<?= $date ?>"></div>
            <div class="col-md-3">
                <select name="department" class="form-select form-select-sm">
                    <option value="">All Departments</option>
                    <?php foreach ($departments as $d): ?>
                    <option value="<?= sanitize($d) ?>" <?= $dept === $d ? 'selected' : '' ?>><?= sanitize($d) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2"><button class="btn btn-sm btn-primary w-100">Filter</button></div>
        </form>

        <div class="table-responsive">
            <table class="table table-hover">
                <thead><tr><th>Employee ID</th><th>Name</th><th>Department</th><th>Check In</th><th>Check Out</th><th>Status</th><th>Notes</th></tr></thead>
                <tbody>
                <?php foreach ($employees as $e): ?>
                <tr>
                    <td><?= sanitize($e['employee_id']) ?></td>
                    <td><?= sanitize($e['full_name']) ?></td>
                    <td><?= sanitize($e['department'] ?? '-') ?></td>
                    <td><?= $e['check_in'] ? date('H:i', strtotime($e['check_in'])) : '-' ?></td>
                    <td><?= $e['check_out'] ? date('H:i', strtotime($e['check_out'])) : '-' ?></td>
                    <td>
                        <?php if ($e['att_status']): ?>
                        <span class="badge status-<?= $e['att_status'] ?>"><?= ucfirst($e['att_status']) ?></span>
                        <?php else: ?>
                        <span class="badge bg-secondary">Unmarked</span>
                        <?php endif; ?>
                    </td>
                    <td><?= sanitize($e['att_notes'] ?? '-') ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
