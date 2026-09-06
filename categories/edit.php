<?php
// =============================================================================
// ChainTrack — Item Category Management: Edit Category
// categories/edit.php
// =============================================================================

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole('administrator');

$db = getMySQL();
$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Invalid category ID.'];
    header('Location: /categories/index.php');
    exit;
}

$cat = getCategoryById($db, $id);
if (!$cat) {
    $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Category not found.'];
    header('Location: /categories/index.php');
    exit;
}

$errors = [];
$v = [
    'cat_name'    => $cat['cat_name'] ?? '',
    'description' => $cat['description'] ?? '',
    'is_active'   => (int)$cat['is_active'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $v['cat_name']    = trim($_POST['cat_name'] ?? '');
    $v['description'] = trim($_POST['description'] ?? '');
    $v['is_active']   = isset($_POST['is_active']) ? (int)$_POST['is_active'] : 1;

    validateRequired($v['cat_name'], 'Category name', $errors);

    if ($v['cat_name'] !== '' && !isCategoryNameUnique($db, $v['cat_name'], $id)) {
        $errors[] = "A category named \"{$v['cat_name']}\" already exists.";
    }

    if (empty($errors)) {
        try {
            $desc = $v['description'] !== '' ? $v['description'] : null;
            $active = (int)$v['is_active'];

            $stmt = $db->prepare(
                "UPDATE item_categories
                 SET cat_name = ?, description = ?, is_active = ?
                 WHERE id = ?"
            );
            $stmt->bind_param('ssii', $v['cat_name'], $desc, $active, $id);
            $stmt->execute();
            $stmt->close();

            logAudit($db, sessionUserId(), 'category.updated', 'item_category', $id, "Updated category {$v['cat_name']} (ID: {$id})");

            $_SESSION['flash'] = [
                'type'    => 'success',
                'message' => "Category \"{$v['cat_name']}\" updated successfully.",
            ];
            header('Location: /categories/index.php');
            exit;
        } catch (Throwable $e) {
            $errors[] = 'Database error: ' . $e->getMessage();
        }
    }
}

$pageTitle = 'Edit Category: ' . $cat['cat_name'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= esc($pageTitle) ?> — <?= APP_NAME ?></title>
  <meta name="description" content="Edit item category details.">
  <link rel="stylesheet" href="/public/css/chaintrack.css">
</head>
<body>

<div id="ct-sidebar-wrap">
<?php require_once __DIR__ . '/../views/layouts/sidebar.php'; ?>
<div id="ct-main">

  <div id="ct-topbar">
    <span class="page-title">Edit Category</span>
    <div class="topbar-right">
      <div class="ct-user-pill">
        <div class="ct-avatar"><?= strtoupper(substr($_SESSION['user_name'] ?? 'U', 0, 1)) ?></div>
        <span><?= esc($_SESSION['user_name'] ?? '') ?></span>
      </div>
      <a href="/views/auth/logout.php" class="ct-btn ct-btn-secondary ct-btn-sm" style="border-radius:99px;">Sign Out</a>
    </div>
  </div>

  <div id="ct-content">

    <div class="mb-2">
      <div class="small text-muted mb-1">
        <a href="/categories/index.php" style="color:var(--ct-muted);text-decoration:none;">Categories</a> › <span>Edit</span>
      </div>
      <h2 style="margin:0;font-size:1.25rem;">Edit Category: <?= esc($cat['cat_name']) ?></h2>
    </div>

    <div class="ct-card" style="max-width:680px;">
      <div class="ct-card-header">Category Details (ID: #<?= (int)$id ?>)</div>
      <div class="ct-card-body">

        <?php if (!empty($errors)): ?>
        <div class="ct-alert ct-alert-danger mb-2">
          <ul style="margin:0;padding-left:18px;">
            <?php foreach ($errors as $err): ?>
            <li><?= esc($err) ?></li>
            <?php endforeach; ?>
          </ul>
        </div>
        <?php endif; ?>

        <form method="POST">
          <div class="ct-form-group">
            <label class="ct-label">Category Name <span style="color:var(--ct-danger);">*</span></label>
            <input type="text" name="cat_name" class="ct-input" required maxlength="100"
                   value="<?= esc($v['cat_name']) ?>">
          </div>

          <div class="ct-form-group">
            <label class="ct-label">Status</label>
            <select name="is_active" class="ct-select">
              <option value="1" <?= (int)$v['is_active'] === 1 ? 'selected' : '' ?>>Active</option>
              <option value="0" <?= (int)$v['is_active'] === 0 ? 'selected' : '' ?>>Inactive</option>
            </select>
          </div>

          <div class="ct-form-group">
            <label class="ct-label">Description</label>
            <textarea name="description" class="ct-textarea" style="min-height:90px;"><?= esc($v['description']) ?></textarea>
          </div>

          <div class="d-flex gap-2 mt-2">
            <button type="submit" class="ct-btn ct-btn-primary">Update Category</button>
            <a href="/categories/index.php" class="ct-btn ct-btn-secondary">Cancel</a>
          </div>
        </form>

      </div>
    </div>

  </div>
</div>
</div>

</body>
</html>
