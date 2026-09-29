<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
requireAdmin();
require_once __DIR__ . '/../api/config/database.php';

if (empty($_SESSION['local_cat_csrf'])) $_SESSION['local_cat_csrf'] = bin2hex(random_bytes(32));
$csrf = $_SESSION['local_cat_csrf'];
$error = '';
$flash = '';

function ce(mixed $v): string {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

$editId = (int)($_GET['edit'] ?? 0);
$editCategory = null;

if ($editId > 0) {
    $stmt = $pdo->prepare(
        'SELECT id,name,description,sort_order
         FROM local_categories
         WHERE id=? AND deleted_at IS NULL
         LIMIT 1'
    );
    $stmt->execute([$editId]);
    $editCategory = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    if (!$editCategory) {
        $error = 'Category not found.';
        $editId = 0;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($csrf, (string)($_POST['csrf'] ?? ''))) {
        $error = 'Invalid request token.';
    } else {
        try {
            $action = (string)($_POST['action'] ?? '');
            $id = (int)($_POST['id'] ?? 0);

            if ($action === 'save') {
                $name = trim((string)($_POST['name'] ?? ''));
                $desc = trim((string)($_POST['description'] ?? ''));
                $sortOrder = (int)($_POST['sort_order'] ?? 0);

                if ($name === '') {
                    throw new RuntimeException('Category name is required.');
                }

                if ($id > 0) {
                    $stmt = $pdo->prepare(
                        'UPDATE local_categories
                         SET name=?,description=?,sort_order=?,updated_at=UTC_TIMESTAMP()
                         WHERE id=? AND deleted_at IS NULL'
                    );
                    $stmt->execute([
                        $name,
                        $desc !== '' ? $desc : null,
                        $sortOrder,
                        $id
                    ]);

                    if ($stmt->rowCount() === 0) {
                        throw new RuntimeException('Category not found or no changes were made.');
                    }

                    $_SESSION['local_cat_flash'] = 'Category updated.';
                } else {
                    $stmt = $pdo->prepare(
                        'INSERT INTO local_categories(name,description,sort_order,is_active)
                         VALUES(?,?,?,1)'
                    );
                    $stmt->execute([
                        $name,
                        $desc !== '' ? $desc : null,
                        $sortOrder
                    ]);

                    $_SESSION['local_cat_flash'] = 'Category added.';
                }

                header('Location: categories.php');
                exit;
            }

            if ($action === 'delete' && $id > 0) {
                $pdo->prepare(
                    'UPDATE local_categories
                     SET deleted_at=UTC_TIMESTAMP(),is_active=0,updated_at=UTC_TIMESTAMP()
                     WHERE id=?'
                )->execute([$id]);

                $_SESSION['local_cat_flash'] = 'Category archived.';
                header('Location: categories.php');
                exit;
            }

            throw new RuntimeException('Invalid category action.');
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    }
}

$flash = $_SESSION['local_cat_flash'] ?? '';
unset($_SESSION['local_cat_flash']);

$categories = $pdo->query(
    'SELECT id,name,description,sort_order,is_active,created_at
     FROM local_categories
     WHERE deleted_at IS NULL
     ORDER BY sort_order,name'
)->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'Categories';
require __DIR__ . '/includes/header.php';
?>
<main class="content">
  <div class="d-flex justify-content-between align-items-end gap-3 flex-wrap">
    <div>
      <div class="page-kicker">MWH Local · Configuration</div>
      <h1 class="page-title">Categories</h1>
      <p class="muted mb-0">Local-specific staff categories used by requirements and job roles.</p>
    </div>
  </div>

  <?php if ($flash): ?>
    <div class="alert alert-success mt-3"><?=ce($flash)?></div>
  <?php endif; ?>
  <?php if ($error): ?>
    <div class="alert alert-danger mt-3"><?=ce($error)?></div>
  <?php endif; ?>

  <div class="row g-3 mt-1">
    <div class="col-xl-4">
      <section class="panel p-3">
        <div class="d-flex justify-content-between align-items-center mb-3">
          <h2 style="font-size:16px;margin:0"><?= $editCategory ? 'Edit Category' : 'Add Category' ?></h2>
          <?php if ($editCategory): ?>
            <a class="btn-outline-local" href="categories.php" title="Cancel edit">
              <i class="bi bi-x-lg"></i>
            </a>
          <?php endif; ?>
        </div>

        <form method="post">
          <input type="hidden" name="csrf" value="<?=ce($csrf)?>">
          <input type="hidden" name="action" value="save">
          <input type="hidden" name="id" value="<?= $editCategory ? (int)$editCategory['id'] : 0 ?>">

          <div class="mb-2">
            <label class="form-label">Name *</label>
            <input
              class="form-control"
              name="name"
              required
              value="<?=ce($editCategory['name'] ?? '')?>"
              placeholder="e.g. Kitchen Staff"
            >
          </div>

          <div class="mb-2">
            <label class="form-label">Description</label>
            <textarea class="form-control" name="description" rows="3" placeholder="Category description"><?=ce($editCategory['description'] ?? '')?></textarea>
          </div>

          <div class="mb-3">
            <label class="form-label">Sort Order</label>
            <input
              class="form-control"
              type="number"
              name="sort_order"
              value="<?=ce($editCategory['sort_order'] ?? 0)?>"
            >
          </div>

          <button class="btn-local" type="submit">
            <i class="bi bi-check2"></i>&nbsp; <?= $editCategory ? 'Update Category' : 'Add Category' ?>
          </button>
        </form>
      </section>
    </div>

    <div class="col-xl-8">
      <section class="panel">
        <div class="panel-head">
          <div>
            <h2>Local Categories</h2>
            <p class="muted">Used by Local requirements and job roles.</p>
          </div>
        </div>

        <div class="table-wrap">
          <table class="table">
            <thead>
              <tr>
                <th>Name</th>
                <th>Description</th>
                <th>Order</th>
                <th>Status</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
            <?php if (!$categories): ?>
              <tr>
                <td colspan="5" class="text-center muted py-5">No categories yet.</td>
              </tr>
            <?php else: foreach ($categories as $c): ?>
              <tr>
                <td><strong><?=ce($c['name'])?></strong></td>
                <td><?=ce($c['description'] ?: '—')?></td>
                <td><?=ce($c['sort_order'])?></td>
                <td><?=!empty($c['is_active']) ? 'Active' : 'Inactive'?></td>
                <td>
                  <div class="d-flex gap-1">
                    <a
                      class="btn-outline-local"
                      href="categories.php?edit=<?=$c['id']?>"
                      title="Edit"
                    >
                      <i class="bi bi-pencil"></i>
                    </a>
                    <form method="post" onsubmit="return confirm('Archive this category?')">
                      <input type="hidden" name="csrf" value="<?=ce($csrf)?>">
                      <input type="hidden" name="action" value="delete">
                      <input type="hidden" name="id" value="<?=$c['id']?>">
                      <button class="btn-outline-local" type="submit" title="Archive">
                        <i class="bi bi-archive"></i>
                      </button>
                    </form>
                  </div>
                </td>
              </tr>
            <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>
      </section>
    </div>
  </div>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
