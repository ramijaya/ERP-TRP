<?php $pageTitle = 'Settings'; require_once __DIR__ . '/includes/header.php'; ?>
<?php
$db = getDB();
$action = $_GET['action'] ?? '';
$user = getCurrentUser();

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'update_company') {
        $stmt = $db->prepare("UPDATE company_settings SET company_name=?, address=?, phone=?, email=?, website=?, tax_id=?, currency=? WHERE id=1");
        $stmt->execute([$_POST['company_name'], $_POST['address'], $_POST['phone'], $_POST['email'], $_POST['website'], $_POST['tax_id'], $_POST['currency']]);
        setFlash('success', 'Company settings updated successfully');
        header('Location: ' . BASE_URL . 'settings.php'); exit;

    } elseif ($action === 'store_user' && $user['role'] === 'admin') {
        $exists = $db->prepare("SELECT COUNT(*) FROM users WHERE username=?"); $exists->execute([$_POST['username']]);
        if ($exists->fetchColumn() > 0) {
            setFlash('error', 'Username already exists');
        } else {
            $hash = password_hash($_POST['password'], PASSWORD_DEFAULT);
            $stmt = $db->prepare("INSERT INTO users (username, password, full_name, email, role) VALUES (?,?,?,?,?)");
            $stmt->execute([$_POST['username'], $hash, $_POST['full_name'], $_POST['email'], $_POST['role']]);
            setFlash('success', 'User created successfully');
        }
        header('Location: ' . BASE_URL . 'settings.php?tab=users'); exit;

    } elseif ($action === 'update_user' && $user['role'] === 'admin' && isset($_GET['id'])) {
        $stmt = $db->prepare("UPDATE users SET full_name=?, email=?, role=?, is_active=? WHERE id=?");
        $stmt->execute([$_POST['full_name'], $_POST['email'], $_POST['role'], isset($_POST['is_active']) ? 1 : 0, $_GET['id']]);
        setFlash('success', 'User updated successfully');
        header('Location: ' . BASE_URL . 'settings.php?tab=users'); exit;

    } elseif ($action === 'change_password') {
        $targetId = $_GET['id'] ?? $user['id'];
        if ($user['role'] !== 'admin' && $targetId != $user['id']) {
            setFlash('error', 'Permission denied'); header('Location: ' . BASE_URL . 'settings.php'); exit;
        }
        if ($_POST['new_password'] !== $_POST['confirm_password']) {
            setFlash('error', 'Passwords do not match');
        } elseif (strlen($_POST['new_password']) < 6) {
            setFlash('error', 'Password must be at least 6 characters');
        } else {
            $hash = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
            $db->prepare("UPDATE users SET password=? WHERE id=?")->execute([$hash, $targetId]);
            setFlash('success', 'Password changed successfully');
        }
        header('Location: ' . BASE_URL . 'settings.php?tab=users'); exit;
    }
}

// Create user form
if ($action === 'create_user' && $user['role'] === 'admin'): ?>
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fas fa-user-plus me-2"></i>Create New User</span>
        <a href="<?= BASE_URL ?>settings.php?tab=users" class="btn btn-sm btn-outline-secondary">Back</a>
    </div>
    <div class="card-body">
        <form method="POST" action="<?= BASE_URL ?>settings.php?action=store_user">
            <div class="row g-3">
                <div class="col-md-4"><label class="form-label">Username *</label><input type="text" name="username" class="form-control" required pattern="[a-zA-Z0-9_]+" title="Letters, numbers, underscores only"></div>
                <div class="col-md-4"><label class="form-label">Password *</label><input type="password" name="password" class="form-control" required minlength="6"></div>
                <div class="col-md-4"><label class="form-label">Role *</label>
                    <select name="role" class="form-select"><option value="staff">Staff</option><option value="manager">Manager</option><option value="admin">Admin</option></select>
                </div>
                <div class="col-md-6"><label class="form-label">Full Name *</label><input type="text" name="full_name" class="form-control" required></div>
                <div class="col-md-6"><label class="form-label">Email</label><input type="email" name="email" class="form-control"></div>
            </div>
            <div class="mt-3"><button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>Create User</button></div>
        </form>
    </div>
</div>

<?php elseif ($action === 'edit_user' && $user['role'] === 'admin' && isset($_GET['id'])):
    $editUser = $db->prepare("SELECT * FROM users WHERE id=?"); $editUser->execute([$_GET['id']]); $editUser = $editUser->fetch();
?>
<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fas fa-user-edit me-2"></i>Edit User: <?= sanitize($editUser['full_name']) ?></span>
        <a href="<?= BASE_URL ?>settings.php?tab=users" class="btn btn-sm btn-outline-secondary">Back</a>
    </div>
    <div class="card-body">
        <form method="POST" action="<?= BASE_URL ?>settings.php?action=update_user&id=<?= $editUser['id'] ?>">
            <div class="row g-3">
                <div class="col-md-4"><label class="form-label">Username</label><input type="text" class="form-control" value="<?= sanitize($editUser['username']) ?>" disabled></div>
                <div class="col-md-4"><label class="form-label">Full Name *</label><input type="text" name="full_name" class="form-control" required value="<?= sanitize($editUser['full_name']) ?>"></div>
                <div class="col-md-4"><label class="form-label">Email</label><input type="email" name="email" class="form-control" value="<?= sanitize($editUser['email'] ?? '') ?>"></div>
                <div class="col-md-4"><label class="form-label">Role</label>
                    <select name="role" class="form-select">
                        <?php foreach (['staff','manager','admin'] as $r): ?>
                        <option value="<?= $r ?>" <?= $editUser['role'] === $r ? 'selected' : '' ?>><?= ucfirst($r) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4 d-flex align-items-end">
                    <div class="form-check"><input type="checkbox" name="is_active" class="form-check-input" id="isActive" <?= $editUser['is_active'] ? 'checked' : '' ?>><label class="form-check-label" for="isActive">Active</label></div>
                </div>
            </div>
            <div class="mt-3"><button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>Update User</button></div>
        </form>
    </div>
</div>
<div class="card">
    <div class="card-header"><i class="fas fa-key me-2"></i>Change Password</div>
    <div class="card-body">
        <form method="POST" action="<?= BASE_URL ?>settings.php?action=change_password&id=<?= $editUser['id'] ?>">
            <div class="row g-3">
                <div class="col-md-4"><label class="form-label">New Password *</label><input type="password" name="new_password" class="form-control" required minlength="6"></div>
                <div class="col-md-4"><label class="form-label">Confirm Password *</label><input type="password" name="confirm_password" class="form-control" required minlength="6"></div>
                <div class="col-md-4 d-flex align-items-end"><button type="submit" class="btn btn-warning"><i class="fas fa-key me-1"></i>Change Password</button></div>
            </div>
        </form>
    </div>
</div>

<?php else:
    // Main settings view with tabs
    $company = $db->query("SELECT * FROM company_settings WHERE id=1")->fetch();
    $users = $db->query("SELECT * FROM users ORDER BY created_at DESC")->fetchAll();
    $activeTab = $_GET['tab'] ?? 'company';
?>
<ul class="nav nav-tabs mb-4">
    <li class="nav-item"><a class="nav-link <?= $activeTab === 'company' ? 'active' : '' ?>" href="<?= BASE_URL ?>settings.php?tab=company"><i class="fas fa-building me-1"></i>Company</a></li>
    <?php if ($user['role'] === 'admin'): ?>
    <li class="nav-item"><a class="nav-link <?= $activeTab === 'users' ? 'active' : '' ?>" href="<?= BASE_URL ?>settings.php?tab=users"><i class="fas fa-users-cog me-1"></i>Users</a></li>
    <?php endif; ?>
</ul>

<?php if ($activeTab === 'company'): ?>
<div class="card">
    <div class="card-header"><i class="fas fa-building me-2"></i>Company Settings</div>
    <div class="card-body">
        <form method="POST" action="<?= BASE_URL ?>settings.php?action=update_company">
            <div class="row g-3">
                <div class="col-md-6"><label class="form-label">Company Name *</label><input type="text" name="company_name" class="form-control" required value="<?= sanitize($company['company_name'] ?? '') ?>"></div>
                <div class="col-md-3"><label class="form-label">Phone</label><input type="text" name="phone" class="form-control" value="<?= sanitize($company['phone'] ?? '') ?>"></div>
                <div class="col-md-3"><label class="form-label">Currency</label>
                    <select name="currency" class="form-select">
                        <?php foreach (['IDR','USD','EUR','SGD','MYR','JPY','GBP'] as $c): ?>
                        <option value="<?= $c ?>" <?= ($company['currency'] ?? 'IDR') === $c ? 'selected' : '' ?>><?= $c ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6"><label class="form-label">Email</label><input type="email" name="email" class="form-control" value="<?= sanitize($company['email'] ?? '') ?>"></div>
                <div class="col-md-6"><label class="form-label">Website</label><input type="text" name="website" class="form-control" value="<?= sanitize($company['website'] ?? '') ?>"></div>
                <div class="col-md-6"><label class="form-label">Tax ID</label><input type="text" name="tax_id" class="form-control" value="<?= sanitize($company['tax_id'] ?? '') ?>"></div>
                <div class="col-12"><label class="form-label">Address</label><textarea name="address" class="form-control" rows="2"><?= sanitize($company['address'] ?? '') ?></textarea></div>
            </div>
            <div class="mt-3"><button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>Save Settings</button></div>
        </form>
    </div>
</div>

<?php elseif ($activeTab === 'users' && $user['role'] === 'admin'): ?>
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fas fa-users-cog me-2"></i>User Management (<?= count($users) ?>)</span>
        <a href="<?= BASE_URL ?>settings.php?action=create_user" class="btn btn-sm btn-primary"><i class="fas fa-user-plus me-1"></i>Add User</a>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead><tr><th>Username</th><th>Full Name</th><th>Email</th><th>Role</th><th>Last Login</th><th>Status</th><th>Actions</th></tr></thead>
                <tbody>
                <?php foreach ($users as $u): ?>
                <tr>
                    <td><strong><?= sanitize($u['username']) ?></strong></td>
                    <td><?= sanitize($u['full_name']) ?></td>
                    <td><?= sanitize($u['email'] ?? '-') ?></td>
                    <td><span class="badge bg-<?= $u['role']==='admin' ? 'danger' : ($u['role']==='manager' ? 'warning' : 'secondary') ?>"><?= ucfirst($u['role']) ?></span></td>
                    <td><?= $u['last_login'] ? date('d M Y H:i', strtotime($u['last_login'])) : 'Never' ?></td>
                    <td><span class="badge <?= $u['is_active'] ? 'status-active' : 'status-inactive' ?>"><?= $u['is_active'] ? 'Active' : 'Inactive' ?></span></td>
                    <td><a href="<?= BASE_URL ?>settings.php?action=edit_user&id=<?= $u['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="fas fa-edit"></i></a></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; endif; ?>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
