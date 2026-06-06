<?php $pageTitle = 'Journal Entries'; require_once __DIR__ . '/includes/header.php'; ?>
<?php
$db = getDB();
$action = $_GET['action'] ?? 'list';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'store') {
        // Auto-generate entry_number
        $prefix = 'JE-' . date('Ym') . '-';
        $stmt = $db->prepare("SELECT COUNT(*) as cnt FROM journal_entries WHERE entry_number LIKE ?");
        $stmt->execute([$prefix . '%']);
        $count = $stmt->fetch()['cnt'];
        $entryNumber = $prefix . str_pad($count + 1, 4, '0', STR_PAD_LEFT);

        $db->beginTransaction();
        try {
            $stmt = $db->prepare("INSERT INTO journal_entries (entry_number, entry_date, description, reference, status, created_by) VALUES (?, ?, ?, ?, 'draft', ?)");
            $stmt->execute([
                $entryNumber,
                $_POST['entry_date'],
                $_POST['description'],
                $_POST['reference'],
                $_SESSION['user_id'] ?? 1
            ]);
            $entryId = $db->lastInsertId();

            // Insert line items
            if (isset($_POST['account_id']) && is_array($_POST['account_id'])) {
                $lineStmt = $db->prepare("INSERT INTO journal_entry_lines (journal_entry_id, account_id, debit, credit, description) VALUES (?, ?, ?, ?, ?)");
                for ($i = 0; $i < count($_POST['account_id']); $i++) {
                    if (empty($_POST['account_id'][$i])) continue;
                    $lineStmt->execute([
                        $entryId,
                        (int)$_POST['account_id'][$i],
                        (float)($_POST['debit'][$i] ?? 0),
                        (float)($_POST['credit'][$i] ?? 0),
                        $_POST['line_description'][$i] ?? ''
                    ]);
                }
            }

            $db->commit();
            setFlash('success', 'Journal entry created successfully: ' . $entryNumber);
            header('Location: ' . BASE_URL . 'journals.php?action=view&id=' . $entryId);
            exit;
        } catch (Exception $e) {
            $db->rollBack();
            setFlash('error', 'Failed to create journal entry: ' . $e->getMessage());
            header('Location: ' . BASE_URL . 'journals.php?action=create');
            exit;
        }
    }

    if ($action === 'post' && $id > 0) {
        $stmt = $db->prepare("SELECT * FROM journal_entries WHERE id = ? AND status = 'draft'");
        $stmt->execute([$id]);
        $entry = $stmt->fetch();
        if (!$entry) {
            setFlash('error', 'Journal entry not found or not in draft status.');
            header('Location: ' . BASE_URL . 'journals.php');
            exit;
        }

        $db->beginTransaction();
        try {
            // Update status
            $stmt = $db->prepare("UPDATE journal_entries SET status = 'posted' WHERE id = ?");
            $stmt->execute([$id]);

            // Update account balances
            $lines = $db->prepare("SELECT * FROM journal_entry_lines WHERE journal_entry_id = ?");
            $lines->execute([$id]);
            foreach ($lines->fetchAll() as $line) {
                $balanceChange = $line['debit'] - $line['credit'];
                $stmt = $db->prepare("UPDATE chart_of_accounts SET balance = balance + ? WHERE id = ?");
                $stmt->execute([$balanceChange, $line['account_id']]);
            }

            $db->commit();
            setFlash('success', 'Journal entry posted successfully.');
        } catch (Exception $e) {
            $db->rollBack();
            setFlash('error', 'Failed to post journal entry: ' . $e->getMessage());
        }
        header('Location: ' . BASE_URL . 'journals.php?action=view&id=' . $id);
        exit;
    }

    if ($action === 'delete' && $id > 0) {
        $stmt = $db->prepare("SELECT * FROM journal_entries WHERE id = ? AND status = 'draft'");
        $stmt->execute([$id]);
        if (!$stmt->fetch()) {
            setFlash('error', 'Cannot delete: only draft entries can be deleted.');
        } else {
            $db->beginTransaction();
            try {
                $stmt = $db->prepare("DELETE FROM journal_entry_lines WHERE journal_entry_id = ?");
                $stmt->execute([$id]);
                $stmt = $db->prepare("DELETE FROM journal_entries WHERE id = ?");
                $stmt->execute([$id]);
                $db->commit();
                setFlash('success', 'Journal entry deleted successfully.');
            } catch (Exception $e) {
                $db->rollBack();
                setFlash('error', 'Failed to delete journal entry.');
            }
        }
        header('Location: ' . BASE_URL . 'journals.php');
        exit;
    }
}

// === LIST ===
if ($action === 'list'):
    $statusFilter = $_GET['status'] ?? '';
    $dateFrom = $_GET['date_from'] ?? '';
    $dateTo = $_GET['date_to'] ?? '';
    $page = max(1, (int)($_GET['page'] ?? 1));
    $perPage = 20;
    $offset = ($page - 1) * $perPage;

    $where = [];
    $params = [];
    if ($statusFilter !== '') {
        $where[] = "status = ?";
        $params[] = $statusFilter;
    }
    if ($dateFrom !== '') {
        $where[] = "entry_date >= ?";
        $params[] = $dateFrom;
    }
    if ($dateTo !== '') {
        $where[] = "entry_date <= ?";
        $params[] = $dateTo;
    }
    $whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    $countStmt = $db->prepare("SELECT COUNT(*) as total FROM journal_entries $whereSQL");
    $countStmt->execute($params);
    $total = $countStmt->fetch()['total'];
    $totalPages = max(1, ceil($total / $perPage));

    $stmt = $db->prepare("SELECT * FROM journal_entries $whereSQL ORDER BY id DESC LIMIT $perPage OFFSET $offset");
    $stmt->execute($params);
    $entries = $stmt->fetchAll();
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h5 class="mb-0">Journal Entries</h5>
        <small class="text-muted"><?= $total ?> total entries</small>
    </div>
    <a href="<?= BASE_URL ?>journals.php?action=create" class="btn btn-primary">
        <i class="fas fa-plus me-1"></i> New Journal Entry
    </a>
</div>

<div class="card">
    <div class="card-header">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label mb-1">Status</label>
                <select name="status" class="form-select">
                    <option value="">All Status</option>
                    <option value="draft" <?= $statusFilter === 'draft' ? 'selected' : '' ?>>Draft</option>
                    <option value="posted" <?= $statusFilter === 'posted' ? 'selected' : '' ?>>Posted</option>
                    <option value="void" <?= $statusFilter === 'void' ? 'selected' : '' ?>>Void</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label mb-1">Date From</label>
                <input type="date" name="date_from" class="form-control" value="<?= sanitize($dateFrom) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label mb-1">Date To</label>
                <input type="date" name="date_to" class="form-control" value="<?= sanitize($dateTo) ?>">
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100"><i class="fas fa-search me-1"></i> Filter</button>
            </div>
            <?php if ($statusFilter || $dateFrom || $dateTo): ?>
            <div class="col-md-1">
                <a href="<?= BASE_URL ?>journals.php" class="btn btn-outline-secondary w-100"><i class="fas fa-times"></i></a>
            </div>
            <?php endif; ?>
        </form>
    </div>
    <div class="card-body p-0">
        <?php if (empty($entries)): ?>
        <div class="empty-state">
            <i class="fas fa-book"></i>
            <p>No journal entries found.</p>
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>Entry #</th>
                        <th>Date</th>
                        <th>Description</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($entries as $e): ?>
                    <tr>
                        <td><strong><?= sanitize($e['entry_number']) ?></strong></td>
                        <td><?= formatDate($e['entry_date']) ?></td>
                        <td><?= sanitize($e['description']) ?></td>
                        <td><span class="badge status-<?= $e['status'] ?>"><?= ucfirst($e['status']) ?></span></td>
                        <td>
                            <a href="<?= BASE_URL ?>journals.php?action=view&id=<?= $e['id'] ?>" class="btn btn-sm btn-outline-primary" title="View"><i class="fas fa-eye"></i></a>
                            <?php if ($e['status'] === 'draft'): ?>
                            <form method="POST" action="<?= BASE_URL ?>journals.php?action=post&id=<?= $e['id'] ?>" class="d-inline">
                                <button type="submit" class="btn btn-sm btn-outline-success" title="Post" onclick="return confirm('Post this journal entry? This will update account balances.')"><i class="fas fa-check"></i></button>
                            </form>
                            <button type="button" class="btn btn-sm btn-outline-danger" title="Delete" data-bs-toggle="modal" data-bs-target="#deleteModal<?= $e['id'] ?>"><i class="fas fa-trash"></i></button>
                            <div class="modal fade" id="deleteModal<?= $e['id'] ?>" tabindex="-1">
                                <div class="modal-dialog modal-sm">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h6 class="modal-title">Confirm Delete</h6>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body">
                                            Delete journal entry <strong><?= sanitize($e['entry_number']) ?></strong>?
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                            <form method="POST" action="<?= BASE_URL ?>journals.php?action=delete&id=<?= $e['id'] ?>" class="d-inline">
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
                    <a class="page-link" href="<?= BASE_URL ?>journals.php?<?= $qs ?>page=<?= $page - 1 ?>">Prev</a>
                </li>
                <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                    <a class="page-link" href="<?= BASE_URL ?>journals.php?<?= $qs ?>page=<?= $i ?>"><?= $i ?></a>
                </li>
                <?php endfor; ?>
                <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                    <a class="page-link" href="<?= BASE_URL ?>journals.php?<?= $qs ?>page=<?= $page + 1 ?>">Next</a>
                </li>
            </ul>
        </nav>
    </div>
    <?php endif; ?>
</div>

<?php
// === CREATE ===
elseif ($action === 'create'):
    $accounts = $db->query("SELECT id, account_code, account_name FROM chart_of_accounts WHERE is_active = 1 ORDER BY account_code")->fetchAll();
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h5 class="mb-0">New Journal Entry</h5>
        <small class="text-muted">Entry number will be auto-generated</small>
    </div>
    <a href="<?= BASE_URL ?>journals.php" class="btn btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i> Back</a>
</div>

<form method="POST" action="<?= BASE_URL ?>journals.php?action=store" id="journalForm" onsubmit="return validateJournal()">
    <div class="card mb-3">
        <div class="card-header"><i class="fas fa-info-circle me-2"></i>Entry Details</div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-4 mb-3">
                    <label class="form-label">Entry Date <span class="text-danger">*</span></label>
                    <input type="date" name="entry_date" class="form-control" required value="<?= date('Y-m-d') ?>">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Reference</label>
                    <input type="text" name="reference" class="form-control" placeholder="e.g. INV-001">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Description <span class="text-danger">*</span></label>
                    <input type="text" name="description" class="form-control" required placeholder="Entry description">
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span><i class="fas fa-list me-2"></i>Line Items</span>
            <button type="button" class="btn btn-sm btn-success" onclick="addLine()"><i class="fas fa-plus me-1"></i> Add Line</button>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table mb-0" id="linesTable">
                    <thead>
                        <tr>
                            <th style="width:35%">Account</th>
                            <th style="width:20%">Debit</th>
                            <th style="width:20%">Credit</th>
                            <th style="width:20%">Description</th>
                            <th style="width:5%"></th>
                        </tr>
                    </thead>
                    <tbody id="linesBody">
                        <tr>
                            <td>
                                <select name="account_id[]" class="form-select form-select-sm" required>
                                    <option value="">Select Account</option>
                                    <?php foreach ($accounts as $a): ?>
                                    <option value="<?= $a['id'] ?>"><?= sanitize($a['account_code']) ?> - <?= sanitize($a['account_name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td><input type="number" name="debit[]" class="form-control form-control-sm debit-input" step="0.01" min="0" value="0" onchange="updateTotals()"></td>
                            <td><input type="number" name="credit[]" class="form-control form-control-sm credit-input" step="0.01" min="0" value="0" onchange="updateTotals()"></td>
                            <td><input type="text" name="line_description[]" class="form-control form-control-sm"></td>
                            <td><button type="button" class="btn btn-sm btn-outline-danger" onclick="removeLine(this)"><i class="fas fa-times"></i></button></td>
                        </tr>
                        <tr>
                            <td>
                                <select name="account_id[]" class="form-select form-select-sm" required>
                                    <option value="">Select Account</option>
                                    <?php foreach ($accounts as $a): ?>
                                    <option value="<?= $a['id'] ?>"><?= sanitize($a['account_code']) ?> - <?= sanitize($a['account_name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td><input type="number" name="debit[]" class="form-control form-control-sm debit-input" step="0.01" min="0" value="0" onchange="updateTotals()"></td>
                            <td><input type="number" name="credit[]" class="form-control form-control-sm credit-input" step="0.01" min="0" value="0" onchange="updateTotals()"></td>
                            <td><input type="text" name="line_description[]" class="form-control form-control-sm"></td>
                            <td><button type="button" class="btn btn-sm btn-outline-danger" onclick="removeLine(this)"><i class="fas fa-times"></i></button></td>
                        </tr>
                    </tbody>
                    <tfoot>
                        <tr class="fw-bold">
                            <td class="text-end">Totals:</td>
                            <td id="totalDebit">0.00</td>
                            <td id="totalCredit">0.00</td>
                            <td colspan="2">
                                <span id="balanceStatus" class="badge bg-secondary">Enter amounts</span>
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

    <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i> Save as Draft</button>
        <a href="<?= BASE_URL ?>journals.php" class="btn btn-outline-secondary">Cancel</a>
    </div>
</form>

<script>
const accountOptions = `<option value="">Select Account</option><?php foreach ($accounts as $a): ?><option value="<?= $a['id'] ?>"><?= sanitize($a['account_code']) ?> - <?= sanitize($a['account_name']) ?></option><?php endforeach; ?>`;

function addLine() {
    const row = document.createElement('tr');
    row.innerHTML = `
        <td><select name="account_id[]" class="form-select form-select-sm" required>${accountOptions}</select></td>
        <td><input type="number" name="debit[]" class="form-control form-control-sm debit-input" step="0.01" min="0" value="0" onchange="updateTotals()"></td>
        <td><input type="number" name="credit[]" class="form-control form-control-sm credit-input" step="0.01" min="0" value="0" onchange="updateTotals()"></td>
        <td><input type="text" name="line_description[]" class="form-control form-control-sm"></td>
        <td><button type="button" class="btn btn-sm btn-outline-danger" onclick="removeLine(this)"><i class="fas fa-times"></i></button></td>
    `;
    document.getElementById('linesBody').appendChild(row);
}

function removeLine(btn) {
    const tbody = document.getElementById('linesBody');
    if (tbody.rows.length > 2) {
        btn.closest('tr').remove();
        updateTotals();
    } else {
        alert('A journal entry must have at least 2 lines.');
    }
}

function updateTotals() {
    let totalDebit = 0, totalCredit = 0;
    document.querySelectorAll('.debit-input').forEach(el => totalDebit += parseFloat(el.value) || 0);
    document.querySelectorAll('.credit-input').forEach(el => totalCredit += parseFloat(el.value) || 0);
    document.getElementById('totalDebit').textContent = totalDebit.toFixed(2);
    document.getElementById('totalCredit').textContent = totalCredit.toFixed(2);

    const badge = document.getElementById('balanceStatus');
    if (totalDebit === 0 && totalCredit === 0) {
        badge.className = 'badge bg-secondary';
        badge.textContent = 'Enter amounts';
    } else if (Math.abs(totalDebit - totalCredit) < 0.01) {
        badge.className = 'badge bg-success';
        badge.textContent = 'Balanced';
    } else {
        badge.className = 'badge bg-danger';
        badge.textContent = 'Difference: ' + Math.abs(totalDebit - totalCredit).toFixed(2);
    }
}

function validateJournal() {
    let totalDebit = 0, totalCredit = 0;
    document.querySelectorAll('.debit-input').forEach(el => totalDebit += parseFloat(el.value) || 0);
    document.querySelectorAll('.credit-input').forEach(el => totalCredit += parseFloat(el.value) || 0);

    if (totalDebit === 0 && totalCredit === 0) {
        alert('Please enter at least one debit and credit amount.');
        return false;
    }
    if (Math.abs(totalDebit - totalCredit) >= 0.01) {
        alert('Total debits (' + totalDebit.toFixed(2) + ') must equal total credits (' + totalCredit.toFixed(2) + ').');
        return false;
    }
    return true;
}
</script>

<?php
// === VIEW ===
elseif ($action === 'view' && $id > 0):
    $stmt = $db->prepare("SELECT * FROM journal_entries WHERE id = ?");
    $stmt->execute([$id]);
    $entry = $stmt->fetch();
    if (!$entry) { setFlash('error', 'Journal entry not found.'); header('Location: ' . BASE_URL . 'journals.php'); exit; }

    $stmt = $db->prepare("SELECT jel.*, ca.account_code, ca.account_name FROM journal_entry_lines jel JOIN chart_of_accounts ca ON jel.account_id = ca.id WHERE jel.journal_entry_id = ? ORDER BY jel.id");
    $stmt->execute([$id]);
    $lines = $stmt->fetchAll();

    $totalDebit = 0;
    $totalCredit = 0;
    foreach ($lines as $line) {
        $totalDebit += $line['debit'];
        $totalCredit += $line['credit'];
    }
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h5 class="mb-0"><?= sanitize($entry['entry_number']) ?></h5>
        <small class="text-muted"><?= formatDate($entry['entry_date']) ?></small>
    </div>
    <div class="d-flex gap-2">
        <?php if ($entry['status'] === 'draft'): ?>
        <form method="POST" action="<?= BASE_URL ?>journals.php?action=post&id=<?= $entry['id'] ?>" class="d-inline">
            <button type="submit" class="btn btn-success" onclick="return confirm('Post this journal entry? Account balances will be updated.')"><i class="fas fa-check me-1"></i> Post Entry</button>
        </form>
        <?php endif; ?>
        <a href="<?= BASE_URL ?>journals.php" class="btn btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i> Back</a>
    </div>
</div>

<div class="row">
    <div class="col-md-12">
        <div class="card mb-3">
            <div class="card-header"><i class="fas fa-info-circle me-2"></i>Entry Details</div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3 mb-3">
                        <label class="form-label text-muted">Entry Number</label>
                        <div class="fw-bold"><?= sanitize($entry['entry_number']) ?></div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label text-muted">Date</label>
                        <div><?= formatDate($entry['entry_date']) ?></div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label text-muted">Status</label>
                        <div><span class="badge status-<?= $entry['status'] ?>"><?= ucfirst($entry['status']) ?></span></div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label text-muted">Reference</label>
                        <div><?= sanitize($entry['reference'] ?? '') ?: '-' ?></div>
                    </div>
                    <div class="col-md-12">
                        <label class="form-label text-muted">Description</label>
                        <div><?= sanitize($entry['description']) ?></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><i class="fas fa-list me-2"></i>Line Items</div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Account</th>
                                <th>Description</th>
                                <th class="text-end">Debit</th>
                                <th class="text-end">Credit</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($lines as $line): ?>
                            <tr>
                                <td><strong><?= sanitize($line['account_code']) ?></strong> - <?= sanitize($line['account_name']) ?></td>
                                <td><?= sanitize($line['description'] ?? '') ?: '-' ?></td>
                                <td class="text-end"><?= $line['debit'] > 0 ? formatCurrency($line['debit']) : '-' ?></td>
                                <td class="text-end"><?= $line['credit'] > 0 ? formatCurrency($line['credit']) : '-' ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr class="fw-bold">
                                <td colspan="2" class="text-end">Totals:</td>
                                <td class="text-end"><?= formatCurrency($totalDebit) ?></td>
                                <td class="text-end"><?= formatCurrency($totalCredit) ?></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php else: ?>
<div class="alert alert-danger">Invalid action.</div>
<a href="<?= BASE_URL ?>journals.php" class="btn btn-outline-secondary">Back to Journal Entries</a>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
