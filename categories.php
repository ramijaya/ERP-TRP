<?php $pageTitle = 'Categories'; require_once __DIR__ . '/includes/header.php'; ?>
<?php
$db = getDB();
$action = $_GET['action'] ?? 'list';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// --- STORE (POST) ---
if ($action === 'store' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $parent_id = !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;

    if (empty($name)) {
        setFlash('error', 'Category name is required.');
        header('Location: ' . BASE_URL . 'categories.php?action=create');
        exit;
    }

    try {
        $stmt = $db->prepare("INSERT INTO product_categories (name, description, parent_id) VALUES (?, ?, ?)");
        $stmt->execute([$name, $description, $parent_id]);
        setFlash('success', 'Category created successfully.');
        header('Location: ' . BASE_URL . 'categories.php');
        exit;
    } catch (PDOException $e) {
        setFlash('error', 'Failed to create category: ' . $e->getMessage());
        header('Location: ' . BASE_URL . 'categories.php?action=create');
        exit;
    }
}

// --- UPDATE (POST) ---
if ($action === 'update' && $_SERVER['REQUEST_METHOD'] === 'POST' && $id > 0) {
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $parent_id = !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;

    // Prevent setting self as parent
    if ($parent_id === $id) {
        setFlash('error', 'A category cannot be its own parent.');
        header('Location: ' . BASE_URL . 'categories.php?action=edit&id=' . $id);
        exit;
    }

    if (empty($name)) {
        setFlash('error', 'Category name is required.');
        header('Location: ' . BASE_URL . 'categories.php?action=edit&id=' . $id);
        exit;
    }

    try {
        $stmt = $db->prepare("UPDATE product_categories SET name=?, description=?, parent_id=? WHERE id=?");
        $stmt->execute([$name, $description, $parent_id, $id]);
        setFlash('success', 'Category updated successfully.');
        header('Location: ' . BASE_URL . 'categories.php');
        exit;
    } catch (PDOException $e) {
        setFlash('error', 'Failed to update category: ' . $e->getMessage());
        header('Location: ' . BASE_URL . 'categories.php?action=edit&id=' . $id);
        exit;
    }
}

// --- DELETE (POST) ---
if ($action === 'delete' && $_SERVER['REQUEST_METHOD'] === 'POST' && $id > 0) {
    // Check if category has products
    $stmt = $db->prepare("SELECT COUNT(*) FROM products WHERE category_id = ?");
    $stmt->execute([$id]);
    $productCount = (int)$stmt->fetchColumn();

    if ($productCount > 0) {
        setFlash('error', 'Cannot delete category. It has ' . $productCount . ' product(s) assigned.');
        header('Location: ' . BASE_URL . 'categories.php');
        exit;
    }

    // Also check for child categories
    $stmt = $db->prepare("SELECT COUNT(*) FROM product_categories WHERE parent_id = ?");
    $stmt->execute([$id]);
    $childCount = (int)$stmt->fetchColumn();

    if ($childCount > 0) {
        setFlash('error', 'Cannot delete category. It has ' . $childCount . ' sub-category(ies).');
        header('Location: ' . BASE_URL . 'categories.php');
        exit;
    }

    $stmt = $db->prepare("DELETE FROM product_categories WHERE id = ?");
    $stmt->execute([$id]);
    setFlash('success', 'Category deleted successfully.');
    header('Location: ' . BASE_URL . 'categories.php');
    exit;
}

// --- CREATE / EDIT FORM ---
if ($action === 'create' || $action === 'edit'):
    $category = null;
    if ($action === 'edit' && $id > 0) {
        $stmt = $db->prepare("SELECT * FROM product_categories WHERE id = ?");
        $stmt->execute([$id]);
        $category = $stmt->fetch();
        if (!$category) { setFlash('error', 'Category not found.'); header('Location: ' . BASE_URL . 'categories.php'); exit; }
    }

    // Get all categories for parent dropdown, excluding self when editing
    if ($action === 'edit') {
        $stmt = $db->prepare("SELECT id, name FROM product_categories WHERE id != ? ORDER BY name");
        $stmt->execute([$id]);
        $parentCategories = $stmt->fetchAll();
    } else {
        $parentCategories = $db->query("SELECT id, name FROM product_categories ORDER BY name")->fetchAll();
    }

    $formAction = $action === 'create' ? 'store' : 'update';
    $formUrl = BASE_URL . 'categories.php?action=' . $formAction . ($action === 'edit' ? '&id=' . $id : '');
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h5 class="mb-0"><i class="fas fa-<?= $action === 'create' ? 'plus' : 'edit' ?> me-2"></i><?= $action === 'create' ? 'Add New Category' : 'Edit Category' ?></h5>
    <a href="<?= BASE_URL ?>categories.php" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i>Back</a>
</div>
<div class="card">
    <div class="card-body">
        <form method="POST" action="<?= $formUrl ?>">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Category Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" name="name" value="<?= sanitize($category['name'] ?? '') ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Parent Category</label>
                    <select class="form-select" name="parent_id">
                        <option value="">-- None (Top Level) --</option>
                        <?php foreach ($parentCategories as $pc): ?>
                            <option value="<?= $pc['id'] ?>" <?= ($category['parent_id'] ?? '') == $pc['id'] ? 'selected' : '' ?>><?= sanitize($pc['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-12">
                    <label class="form-label">Description</label>
                    <textarea class="form-control" name="description" rows="3"><?= sanitize($category['description'] ?? '') ?></textarea>
                </div>
            </div>
            <div class="mt-4">
                <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i><?= $action === 'create' ? 'Create Category' : 'Update Category' ?></button>
                <a href="<?= BASE_URL ?>categories.php" class="btn btn-secondary ms-2">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php
// --- LIST ---
else:
    $categories = $db->query("
        SELECT pc.*,
               parent.name as parent_name,
               (SELECT COUNT(*) FROM products WHERE category_id = pc.id) as product_count
        FROM product_categories pc
        LEFT JOIN product_categories parent ON pc.parent_id = parent.id
        ORDER BY pc.name
    ")->fetchAll();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h5 class="mb-0"><i class="fas fa-tags me-2"></i>Product Categories <span class="badge bg-secondary"><?= count($categories) ?></span></h5>
    <a href="<?= BASE_URL ?>categories.php?action=create" class="btn btn-primary btn-sm"><i class="fas fa-plus me-1"></i>Add Category</a>
</div>

<div class="card">
    <div class="card-body p-0">
        <?php if (empty($categories)): ?>
            <div class="empty-state"><i class="fas fa-tags"></i><p>No categories found</p></div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Parent</th>
                        <th>Description</th>
                        <th>Products Count</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($categories as $cat): ?>
                <tr>
                    <td><?= $cat['id'] ?></td>
                    <td><strong><?= sanitize($cat['name']) ?></strong></td>
                    <td><?= sanitize($cat['parent_name'] ?? '-') ?></td>
                    <td><?= sanitize($cat['description'] ?: '-') ?></td>
                    <td><span class="badge bg-secondary"><?= $cat['product_count'] ?></span></td>
                    <td>
                        <a href="<?= BASE_URL ?>categories.php?action=edit&id=<?= $cat['id'] ?>" class="btn btn-warning btn-sm" title="Edit"><i class="fas fa-edit"></i></a>
                        <form method="POST" action="<?= BASE_URL ?>categories.php?action=delete&id=<?= $cat['id'] ?>" class="d-inline" onsubmit="return confirm('Delete this category?')">
                            <button type="submit" class="btn btn-danger btn-sm" title="Delete"><i class="fas fa-trash"></i></button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php endif; ?>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
