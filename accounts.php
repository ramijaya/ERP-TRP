<?php $pageTitle = 'Chart of Accounts'; require_once __DIR__ . '/includes/header.php'; ?>
<?php
$db = getDB();
$action = $_GET['action'] ?? 'list';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'store') {
        $stmt = $db->prepare("INSERT INTO chart_of_accounts (account_code, account_name, account_type, parent_id, description, is_active) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $_POST['account_code'],
            $_POST['account_name'],
            $_POST['account_type'],
            $_POST['parent_id'] ?: null,
            $_POST['description'],
            isset($_POST['is_active']) ? 1 : 0
        ]);
        setFlash('success', 'Account created successfully.');
        header('Location: ' . BASE_URL . 'accounts.php');
        exit;
    }

    if ($action === 'update' && $id > 0) {
        $stmt = $db->prepare("UPDATE chart_of_accounts SET account_code=?, account_name=?, account_type=?, parent_id=?, description=?, is_active=? WHERE id=?");
        $stmt->execute([
            $_POST['account_code'],
            $_POST['account_name'],
            $_POST['account_type'],
            $_POST['parent_id'] ?: null,
            $_POST['description'],
            isset($_POST['is_active']) ? 1 : 0,
            $id
        ]);
        setFlash('success', 'Account updated successfully.');
        header('Location: ' . BASE_URL . 'accounts.php');
        exit;
    }

    if ($action === 'delete' && $id > 0) {
        // Check for journal entry lines
        $stmt = $db->prepare("SELECT COUNT(*) as cnt FROM journal_entry_lines WHERE account_id = ?");
        $stmt->execute([$id]);
        if ($stmt->fetch()['cnt'] > 0) {
            setFlash('error', 'Cannot delete account: has existing journal entry lines.');
        } else {
            $stmt = $db->prepare("DELETE FROM chart_of_accounts WHERE id = ?");
            $stmt->execute([$id]);
            setFlash('success', 'Account deleted successfully.');
        }
        header('Location: ' . BASE_URL . 'accounts.php');
        exit;
    }
}

// === LIST ===
if ($action === 'list'):
    $accountTypes = ['asset', 'liability', 'equity', 'revenue', 'expense'];
    $typeLabels = [
        'asset' => 'Assets',
        'liability' => 'Liabilities',
        'equity' => 'Equity',
        'revenue' => 'Revenue',
        'expense' => 'Expenses'
    ];
    $typeIcons = [
        'asset' => 'fas fa-landmark',
        'liability' => 'fas fa-hand-holding-usd',
        'equity' => 'fas fa-balance-scale',
        'revenue' => 'fas fa-arrow-trend-up',
        'expense' => 'fas fa-arrow-trend-down'
    ];
    $typeBadgeColors = [
        'asset' => 'primary',
        'liability' => 'danger',
        'equity' => 'info',
        'revenue' => 'success',
        'expense' => 'warning'
    ];

    $stmt = $db->query("SELECT * FROM chart_of_accounts ORDER BY account_code ASC");
    $allAccounts = $stmt->fetchAll();

    // Group by type
    $grouped = [];
    foreach ($accountTypes as $type) {
        $grouped[$type] = [];
    }
    foreach ($allAccounts as $acc) {
        $grouped[$acc['account_type']][] = $acc;
    }
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h5 class="mb-0">Chart of Accounts</h5>
        <small class="text-muted"><?= count($allAccounts) ?> total accounts</small>
    </div>
    <a href="<?= BASE_URL ?>accounts.php?action=create" class="btn btn-primary">
        <i class="fas fa-plus me-1"></i> Add Account
    </a>
</div>

<?php foreach ($accountTypes as $type): ?>
<?php if (!empty($grouped[$type])): ?>
<div class="card">
    <div class="card-header d-flex align-items-center">
        <i class="<?= $typeIcons[$type] ?> me-2 text-<?= $typeBadgeColors[$type] ?>"></i>
        <span><?= $typeLabels[$type] ?></span>
        <span class="badge bg-<?= $typeBadgeColors[$type] ?> ms-2"><?= count($grouped[$type]) ?></span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>Account Code</th>
                        <th>Account Name</th>
                        <th>Type</th>
                        <th>Balance</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($grouped[$type] as $acc): ?>
                    <tr>
                        <td><strong><?= sanitize($acc['account_code']) ?></strong></td>
                        <td><?= sanitize($acc['account_name']) ?></td>
                        <td><span class="badge bg-<?= $typeBadgeColors[$acc['account_type']] ?>"><?= ucfirst($acc['account_type']) ?></span></td>
                        <td><?= formatCurrency($acc['balance'] ?? 0) ?></td>
                        <td>
                            <?php if ($acc['is_active']): ?>
                                <span class="badge status-active">Active</span>
                            <?php else: ?>
                                <span class="badge status-inactive">Inactive</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="<?= BASE_URL ?>accounts.php?action=edit&id=<?= $acc['id'] ?>" class="btn btn-sm btn-outline-warning" title="Edit"><i class="fas fa-edit"></i></a>
                            <button type="button" class="btn btn-sm btn-outline-danger" title="Delete" data-bs-toggle="modal" data-bs-target="#deleteModal<?= $acc['id'] ?>"><i class="fas fa-trash"></i></button>
                            <!-- Delete Modal -->
                            <div class="modal fade" id="deleteModal<?= $acc['id'] ?>" tabindex="-1">
                                <div class="modal-dialog modal-sm">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h6 class="modal-title">Confirm Delete</h6>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body">
                                            Delete account <strong><?= sanitize($acc['account_code']) ?> - <?= sanitize($acc['account_name']) ?></strong>?
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                            <form method="POST" action="<?= BASE_URL ?>accounts.php?action=delete&id=<?= $acc['id'] ?>" class="d-inline">
                                                <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>
<?php endforeach; ?>

<?php if (empty($allAccounts)): ?>
<div class="card">
    <div class="card-body">
        <div class="empty-state">
            <i class="fas fa-building-columns"></i>
            <p>No accounts found.</p>
            <a href="<?= BASE_URL ?>accounts.php?action=create" class="btn btn-primary btn-sm">Add First Account</a>
        </div>
    </div>
</div>
<?php endif; ?>

<?php
// === CREATE ===
elseif ($action === 'create'):
    $parentAccounts = $db->query("SELECT id, account_code, account_name FROM chart_of_accounts WHERE is_active = 1 ORDER BY account_code")->fetchAll();
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h5 class="mb-0">Add New Account</h5>
        <small class="text-muted">Create a new chart of accounts entry</small>
    </div>
    <a href="<?= BASE_URL ?>accounts.php" class="btn btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i> Back</a>
</div>

<div class="card">
    <div class="card-body">
        <form method="POST" action="<?= BASE_URL ?>accounts.php?action=store">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Account Code <span class="text-danger">*</span></label>
                    <input type="text" name="account_code" class="form-control" required placeholder="e.g. 1001">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Account Name <span class="text-danger">*</span></label>
                    <input type="text" name="account_name" class="form-control" required placeholder="e.g. Cash on Hand">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Account Type <span class="text-danger">*</span></label>
                    <select name="account_type" class="form-select" required>
                        <option value="">Select Type</option>
                        <option value="asset">Asset</option>
                        <option value="liability">Liability</option>
                        <option value="equity">Equity</option>
                        <option value="revenue">Revenue</option>
                        <option value="expense">Expense</option>
                    </select>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Parent Account</label>
                    <select name="parent_id" class="form-select">
                        <option value="">None (Top Level)</option>
                        <?php foreach ($parentAccounts as $pa): ?>
                        <option value="<?= $pa['id'] ?>"><?= sanitize($pa['account_code']) ?> - <?= sanitize($pa['account_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-12 mb-3">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-control" rows="3"></textarea>
                </div>
                <div class="col-md-6 mb-3">
                    <div class="form-check mt-2">
                        <input class="form-check-input" type="checkbox" name="is_active" id="is_active" checked>
                        <label class="form-check-label" for="is_active">Active</label>
                    </div>
                </div>
            </div>
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i> Save Account</button>
                <a href="<?= BASE_URL ?>accounts.php" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php
// === EDIT ===
elseif ($action === 'edit' && $id > 0):
    $stmt = $db->prepare("SELECT * FROM chart_of_accounts WHERE id = ?");
    $stmt->execute([$id]);
    $acc = $stmt->fetch();
    if (!$acc) { setFlash('error', 'Account not found.'); header('Location: ' . BASE_URL . 'accounts.php'); exit; }

    $parentAccounts = $db->prepare("SELECT id, account_code, account_name FROM chart_of_accounts WHERE is_active = 1 AND id != ? ORDER BY account_code");
    $parentAccounts->execute([$id]);
    $parentAccounts = $parentAccounts->fetchAll();
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h5 class="mb-0">Edit Account</h5>
        <small class="text-muted"><?= sanitize($acc['account_code']) ?> - <?= sanitize($acc['account_name']) ?></small>
    </div>
    <a href="<?= BASE_URL ?>accounts.php" class="btn btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i> Back</a>
</div>

<div class="card">
    <div class="card-body">
        <form method="POST" action="<?= BASE_URL ?>accounts.php?action=update&id=<?= $acc['id'] ?>">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Account Code <span class="text-danger">*</span></label>
                    <input type="text" name="account_code" class="form-control" required value="<?= sanitize($acc['account_code']) ?>">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Account Name <span class="text-danger">*</span></label>
                    <input type="text" name="account_name" class="form-control" required value="<?= sanitize($acc['account_name']) ?>">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Account Type <span class="text-danger">*</span></label>
                    <select name="account_type" class="form-select" required>
                        <option value="asset" <?= $acc['account_type'] === 'asset' ? 'selected' : '' ?>>Asset</option>
                        <option value="liability" <?= $acc['account_type'] === 'liability' ? 'selected' : '' ?>>Liability</option>
                        <option value="equity" <?= $acc['account_type'] === 'equity' ? 'selected' : '' ?>>Equity</option>
                        <option value="revenue" <?= $acc['account_type'] === 'revenue' ? 'selected' : '' ?>>Revenue</option>
                        <option value="expense" <?= $acc['account_type'] === 'expense' ? 'selected' : '' ?>>Expense</option>
                    </select>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Parent Account</label>
                    <select name="parent_id" class="form-select">
                        <option value="">None (Top Level)</option>
                        <?php foreach ($parentAccounts as $pa): ?>
                        <option value="<?= $pa['id'] ?>" <?= ($acc['parent_id'] ?? '') == $pa['id'] ? 'selected' : '' ?>><?= sanitize($pa['account_code']) ?> - <?= sanitize($pa['account_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-12 mb-3">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-control" rows="3"><?= sanitize($acc['description'] ?? '') ?></textarea>
                </div>
                <div class="col-md-6 mb-3">
                    <div class="form-check mt-2">
                        <input class="form-check-input" type="checkbox" name="is_active" id="is_active" <?= $acc['is_active'] ? 'checked' : '' ?>>
                        <label class="form-check-label" for="is_active">Active</label>
                    </div>
                </div>
            </div>
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i> Update Account</button>
                <a href="<?= BASE_URL ?>accounts.php" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php else: ?>
<div class="alert alert-danger">Invalid action.</div>
<a href="<?= BASE_URL ?>accounts.php" class="btn btn-outline-secondary">Back to Chart of Accounts</a>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
