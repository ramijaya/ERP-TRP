<?php $pageTitle = 'Employees'; require_once __DIR__ . '/includes/header.php'; ?>
<?php
$db = getDB();
$action = $_GET['action'] ?? 'list';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'store') {
        $stmt = $db->query("SELECT MAX(id) as max_id FROM employees");
        $maxId = $stmt->fetch()['max_id'] ?? 0;
        $empId = 'EMP-' . str_pad($maxId + 1, 4, '0', STR_PAD_LEFT);

        $stmt = $db->prepare("INSERT INTO employees (employee_id, full_name, email, phone, address, department, position, hire_date, birth_date, gender, salary, status, emergency_contact, emergency_phone) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $empId,
            $_POST['full_name'],
            $_POST['email'],
            $_POST['phone'],
            $_POST['address'],
            $_POST['department'],
            $_POST['position'],
            $_POST['hire_date'] ?: null,
            $_POST['birth_date'] ?: null,
            $_POST['gender'],
            $_POST['salary'] ?: 0,
            $_POST['status'],
            $_POST['emergency_contact'],
            $_POST['emergency_phone']
        ]);
        setFlash('success', 'Employee created successfully with ID ' . $empId);
        header('Location: ' . BASE_URL . 'employees.php');
        exit;
    }

    if ($action === 'update' && $id > 0) {
        $stmt = $db->prepare("UPDATE employees SET full_name=?, email=?, phone=?, address=?, department=?, position=?, hire_date=?, birth_date=?, gender=?, salary=?, status=?, emergency_contact=?, emergency_phone=? WHERE id=?");
        $stmt->execute([
            $_POST['full_name'],
            $_POST['email'],
            $_POST['phone'],
            $_POST['address'],
            $_POST['department'],
            $_POST['position'],
            $_POST['hire_date'] ?: null,
            $_POST['birth_date'] ?: null,
            $_POST['gender'],
            $_POST['salary'] ?: 0,
            $_POST['status'],
            $_POST['emergency_contact'],
            $_POST['emergency_phone'],
            $id
        ]);
        setFlash('success', 'Employee updated successfully.');
        header('Location: ' . BASE_URL . 'employees.php?action=view&id=' . $id);
        exit;
    }

    if ($action === 'delete' && $id > 0) {
        $stmt = $db->prepare("UPDATE employees SET status = 'terminated' WHERE id = ?");
        $stmt->execute([$id]);
        setFlash('success', 'Employee has been terminated.');
        header('Location: ' . BASE_URL . 'employees.php');
        exit;
    }
}

// === LIST ===
if ($action === 'list'):
    $search = $_GET['search'] ?? '';
    $deptFilter = $_GET['department'] ?? '';
    $statusFilter = $_GET['status'] ?? '';
    $page = max(1, (int)($_GET['page'] ?? 1));
    $perPage = 20;
    $offset = ($page - 1) * $perPage;

    $where = [];
    $params = [];
    if ($search !== '') {
        $where[] = "(full_name LIKE ? OR employee_id LIKE ?)";
        $s = "%$search%";
        $params = array_merge($params, [$s, $s]);
    }
    if ($deptFilter !== '') {
        $where[] = "department = ?";
        $params[] = $deptFilter;
    }
    if ($statusFilter !== '') {
        $where[] = "status = ?";
        $params[] = $statusFilter;
    }
    $whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    $countStmt = $db->prepare("SELECT COUNT(*) as total FROM employees $whereSQL");
    $countStmt->execute($params);
    $total = $countStmt->fetch()['total'];
    $totalPages = max(1, ceil($total / $perPage));

    $stmt = $db->prepare("SELECT * FROM employees $whereSQL ORDER BY id DESC LIMIT $perPage OFFSET $offset");
    $stmt->execute($params);
    $employees = $stmt->fetchAll();

    // Get distinct departments for filter
    $depts = $db->query("SELECT DISTINCT department FROM employees WHERE department IS NOT NULL AND department != '' ORDER BY department")->fetchAll(PDO::FETCH_COLUMN);
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h5 class="mb-0">Employees</h5>
        <small class="text-muted"><?= $total ?> total employees</small>
    </div>
    <a href="<?= BASE_URL ?>employees.php?action=create" class="btn btn-primary">
        <i class="fas fa-plus me-1"></i> Add Employee
    </a>
</div>

<div class="card">
    <div class="card-header">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-4">
                <input type="text" name="search" class="form-control" placeholder="Search by name or employee ID..." value="<?= sanitize($search) ?>">
            </div>
            <div class="col-md-2">
                <select name="department" class="form-select">
                    <option value="">All Departments</option>
                    <?php foreach ($depts as $d): ?>
                    <option value="<?= sanitize($d) ?>" <?= $deptFilter === $d ? 'selected' : '' ?>><?= sanitize($d) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <select name="status" class="form-select">
                    <option value="">All Status</option>
                    <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Active</option>
                    <option value="inactive" <?= $statusFilter === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                    <option value="terminated" <?= $statusFilter === 'terminated' ? 'selected' : '' ?>>Terminated</option>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100"><i class="fas fa-search me-1"></i> Filter</button>
            </div>
            <?php if ($search || $deptFilter || $statusFilter): ?>
            <div class="col-md-2">
                <a href="<?= BASE_URL ?>employees.php" class="btn btn-outline-secondary w-100"><i class="fas fa-times me-1"></i> Clear</a>
            </div>
            <?php endif; ?>
        </form>
    </div>
    <div class="card-body p-0">
        <?php if (empty($employees)): ?>
        <div class="empty-state">
            <i class="fas fa-id-badge"></i>
            <p>No employees found.</p>
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>Employee ID</th>
                        <th>Name</th>
                        <th>Department</th>
                        <th>Position</th>
                        <th>Phone</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($employees as $e): ?>
                    <tr>
                        <td><strong><?= sanitize($e['employee_id']) ?></strong></td>
                        <td><?= sanitize($e['full_name']) ?></td>
                        <td><?= sanitize($e['department']) ?: '-' ?></td>
                        <td><?= sanitize($e['position']) ?: '-' ?></td>
                        <td><?= sanitize($e['phone']) ?: '-' ?></td>
                        <td><span class="badge status-<?= $e['status'] ?>"><?= ucfirst($e['status']) ?></span></td>
                        <td>
                            <a href="<?= BASE_URL ?>employees.php?action=view&id=<?= $e['id'] ?>" class="btn btn-sm btn-outline-primary" title="View"><i class="fas fa-eye"></i></a>
                            <a href="<?= BASE_URL ?>employees.php?action=edit&id=<?= $e['id'] ?>" class="btn btn-sm btn-outline-warning" title="Edit"><i class="fas fa-edit"></i></a>
                            <?php if ($e['status'] !== 'terminated'): ?>
                            <button type="button" class="btn btn-sm btn-outline-danger" title="Terminate" data-bs-toggle="modal" data-bs-target="#deleteModal<?= $e['id'] ?>"><i class="fas fa-user-slash"></i></button>
                            <div class="modal fade" id="deleteModal<?= $e['id'] ?>" tabindex="-1">
                                <div class="modal-dialog modal-sm">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h6 class="modal-title">Confirm Termination</h6>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body">
                                            Terminate employee <strong><?= sanitize($e['full_name']) ?></strong>?
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                            <form method="POST" action="<?= BASE_URL ?>employees.php?action=delete&id=<?= $e['id'] ?>" class="d-inline">
                                                <button type="submit" class="btn btn-sm btn-danger">Terminate</button>
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
                    <a class="page-link" href="<?= BASE_URL ?>employees.php?<?= $qs ?>page=<?= $page - 1 ?>">Prev</a>
                </li>
                <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                    <a class="page-link" href="<?= BASE_URL ?>employees.php?<?= $qs ?>page=<?= $i ?>"><?= $i ?></a>
                </li>
                <?php endfor; ?>
                <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                    <a class="page-link" href="<?= BASE_URL ?>employees.php?<?= $qs ?>page=<?= $page + 1 ?>">Next</a>
                </li>
            </ul>
        </nav>
    </div>
    <?php endif; ?>
</div>

<?php
// === CREATE ===
elseif ($action === 'create'):
    $stmt = $db->query("SELECT MAX(id) as max_id FROM employees");
    $nextId = ($stmt->fetch()['max_id'] ?? 0) + 1;
    $nextEmpId = 'EMP-' . str_pad($nextId, 4, '0', STR_PAD_LEFT);
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h5 class="mb-0">Add New Employee</h5>
        <small class="text-muted">ID will be auto-generated: <?= $nextEmpId ?></small>
    </div>
    <a href="<?= BASE_URL ?>employees.php" class="btn btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i> Back</a>
</div>

<div class="card">
    <div class="card-body">
        <form method="POST" action="<?= BASE_URL ?>employees.php?action=store">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Employee ID</label>
                    <input type="text" class="form-control" value="<?= $nextEmpId ?>" disabled>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Full Name <span class="text-danger">*</span></label>
                    <input type="text" name="full_name" class="form-control" required>
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
                <div class="col-md-6 mb-3">
                    <label class="form-label">Department</label>
                    <input type="text" name="department" class="form-control">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Position</label>
                    <input type="text" name="position" class="form-control">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Hire Date</label>
                    <input type="date" name="hire_date" class="form-control">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Birth Date</label>
                    <input type="date" name="birth_date" class="form-control">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Gender</label>
                    <select name="gender" class="form-select">
                        <option value="">-- Select --</option>
                        <option value="male">Male</option>
                        <option value="female">Female</option>
                        <option value="other">Other</option>
                    </select>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Salary</label>
                    <input type="number" name="salary" class="form-control" value="0" min="0" step="1">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Emergency Contact</label>
                    <input type="text" name="emergency_contact" class="form-control">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Emergency Phone</label>
                    <input type="text" name="emergency_phone" class="form-control">
                </div>
            </div>
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i> Save Employee</button>
                <a href="<?= BASE_URL ?>employees.php" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php
// === EDIT ===
elseif ($action === 'edit' && $id > 0):
    $stmt = $db->prepare("SELECT * FROM employees WHERE id = ?");
    $stmt->execute([$id]);
    $e = $stmt->fetch();
    if (!$e) { setFlash('error', 'Employee not found.'); header('Location: ' . BASE_URL . 'employees.php'); exit; }
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h5 class="mb-0">Edit Employee</h5>
        <small class="text-muted"><?= sanitize($e['employee_id']) ?> - <?= sanitize($e['full_name']) ?></small>
    </div>
    <a href="<?= BASE_URL ?>employees.php" class="btn btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i> Back</a>
</div>

<div class="card">
    <div class="card-body">
        <form method="POST" action="<?= BASE_URL ?>employees.php?action=update&id=<?= $e['id'] ?>">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Employee ID</label>
                    <input type="text" class="form-control" value="<?= sanitize($e['employee_id']) ?>" disabled>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Full Name <span class="text-danger">*</span></label>
                    <input type="text" name="full_name" class="form-control" value="<?= sanitize($e['full_name']) ?>" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-control" value="<?= sanitize($e['email']) ?>">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Phone</label>
                    <input type="text" name="phone" class="form-control" value="<?= sanitize($e['phone']) ?>">
                </div>
                <div class="col-md-12 mb-3">
                    <label class="form-label">Address</label>
                    <textarea name="address" class="form-control" rows="2"><?= sanitize($e['address']) ?></textarea>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Department</label>
                    <input type="text" name="department" class="form-control" value="<?= sanitize($e['department']) ?>">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Position</label>
                    <input type="text" name="position" class="form-control" value="<?= sanitize($e['position']) ?>">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Hire Date</label>
                    <input type="date" name="hire_date" class="form-control" value="<?= $e['hire_date'] ?>">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Birth Date</label>
                    <input type="date" name="birth_date" class="form-control" value="<?= $e['birth_date'] ?>">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Gender</label>
                    <select name="gender" class="form-select">
                        <option value="">-- Select --</option>
                        <option value="male" <?= $e['gender'] === 'male' ? 'selected' : '' ?>>Male</option>
                        <option value="female" <?= $e['gender'] === 'female' ? 'selected' : '' ?>>Female</option>
                        <option value="other" <?= $e['gender'] === 'other' ? 'selected' : '' ?>>Other</option>
                    </select>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Salary</label>
                    <input type="number" name="salary" class="form-control" value="<?= $e['salary'] ?>" min="0" step="1">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="active" <?= $e['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                        <option value="inactive" <?= $e['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                        <option value="terminated" <?= $e['status'] === 'terminated' ? 'selected' : '' ?>>Terminated</option>
                    </select>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Emergency Contact</label>
                    <input type="text" name="emergency_contact" class="form-control" value="<?= sanitize($e['emergency_contact']) ?>">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Emergency Phone</label>
                    <input type="text" name="emergency_phone" class="form-control" value="<?= sanitize($e['emergency_phone']) ?>">
                </div>
            </div>
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i> Update Employee</button>
                <a href="<?= BASE_URL ?>employees.php?action=view&id=<?= $e['id'] ?>" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php
// === VIEW ===
elseif ($action === 'view' && $id > 0):
    $stmt = $db->prepare("SELECT * FROM employees WHERE id = ?");
    $stmt->execute([$id]);
    $e = $stmt->fetch();
    if (!$e) { setFlash('error', 'Employee not found.'); header('Location: ' . BASE_URL . 'employees.php'); exit; }

    // Recent attendance
    $stmt = $db->prepare("SELECT * FROM attendance WHERE employee_id = ? ORDER BY date DESC LIMIT 10");
    $stmt->execute([$id]);
    $recentAttendance = $stmt->fetchAll();

    // Leave summary
    $stmt = $db->prepare("SELECT leave_type, SUM(days) as total_days FROM leaves WHERE employee_id = ? AND status = 'approved' GROUP BY leave_type");
    $stmt->execute([$id]);
    $leaveSummary = $stmt->fetchAll();
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h5 class="mb-0"><?= sanitize($e['full_name']) ?></h5>
        <small class="text-muted"><?= sanitize($e['employee_id']) ?></small>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= BASE_URL ?>employees.php?action=edit&id=<?= $e['id'] ?>" class="btn btn-warning"><i class="fas fa-edit me-1"></i> Edit</a>
        <a href="<?= BASE_URL ?>employees.php" class="btn btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i> Back</a>
    </div>
</div>

<div class="row">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header"><i class="fas fa-id-badge me-2"></i>Employee Details</div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label text-muted">Employee ID</label>
                        <div class="fw-bold"><?= sanitize($e['employee_id']) ?></div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label text-muted">Status</label>
                        <div><span class="badge status-<?= $e['status'] ?>"><?= ucfirst($e['status']) ?></span></div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label text-muted">Full Name</label>
                        <div><?= sanitize($e['full_name']) ?></div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label text-muted">Gender</label>
                        <div><?= $e['gender'] ? ucfirst($e['gender']) : '-' ?></div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label text-muted">Email</label>
                        <div><?= sanitize($e['email']) ?: '-' ?></div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label text-muted">Phone</label>
                        <div><?= sanitize($e['phone']) ?: '-' ?></div>
                    </div>
                    <div class="col-md-12 mb-3">
                        <label class="form-label text-muted">Address</label>
                        <div><?= nl2br(sanitize($e['address'])) ?: '-' ?></div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label text-muted">Department</label>
                        <div><?= sanitize($e['department']) ?: '-' ?></div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label text-muted">Position</label>
                        <div><?= sanitize($e['position']) ?: '-' ?></div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label text-muted">Hire Date</label>
                        <div><?= $e['hire_date'] ? formatDate($e['hire_date']) : '-' ?></div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label text-muted">Birth Date</label>
                        <div><?= $e['birth_date'] ? formatDate($e['birth_date']) : '-' ?></div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label text-muted">Salary</label>
                        <div class="fw-bold text-primary"><?= formatCurrency($e['salary']) ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card">
            <div class="card-header"><i class="fas fa-phone-alt me-2"></i>Emergency Contact</div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label text-muted">Contact Name</label>
                    <div><?= sanitize($e['emergency_contact']) ?: '-' ?></div>
                </div>
                <div class="mb-3">
                    <label class="form-label text-muted">Contact Phone</label>
                    <div><?= sanitize($e['emergency_phone']) ?: '-' ?></div>
                </div>
                <hr>
                <div class="mb-2">
                    <small class="text-muted">Created: <?= formatDate($e['created_at']) ?></small>
                </div>
                <div>
                    <small class="text-muted">Updated: <?= formatDate($e['updated_at']) ?></small>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><i class="fas fa-calendar-check me-2"></i>Leave Summary (Approved)</div>
            <div class="card-body">
                <?php if (empty($leaveSummary)): ?>
                <p class="text-muted mb-0">No approved leaves.</p>
                <?php else: ?>
                <?php foreach ($leaveSummary as $ls): ?>
                <div class="d-flex justify-content-between mb-2">
                    <span><?= ucfirst($ls['leave_type']) ?></span>
                    <strong><?= $ls['total_days'] ?> days</strong>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="card mt-2">
    <div class="card-header"><i class="fas fa-clock me-2"></i>Recent Attendance</div>
    <div class="card-body p-0">
        <?php if (empty($recentAttendance)): ?>
        <div class="empty-state py-4">
            <i class="fas fa-clock"></i>
            <p>No attendance records yet.</p>
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Check In</th>
                        <th>Check Out</th>
                        <th>Status</th>
                        <th>Notes</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recentAttendance as $a): ?>
                    <tr>
                        <td><?= formatDate($a['date']) ?></td>
                        <td><?= $a['check_in'] ?: '-' ?></td>
                        <td><?= $a['check_out'] ?: '-' ?></td>
                        <td><span class="badge status-<?= $a['status'] ?>"><?= ucfirst($a['status']) ?></span></td>
                        <td><?= sanitize($a['notes']) ?: '-' ?></td>
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
<a href="<?= BASE_URL ?>employees.php" class="btn btn-outline-secondary">Back to Employees</a>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
