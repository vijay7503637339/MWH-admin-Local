<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
requireAdmin();
require_once __DIR__ . '/../api/config/database.php';

if (empty($_SESSION['local_role_csrf'])) $_SESSION['local_role_csrf'] = bin2hex(random_bytes(32));
$csrf = $_SESSION['local_role_csrf'];
$error = '';
$flash = '';

function re(mixed $v): string {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function periodLabel(string $period): string {
    return $period === 'per_month' ? 'Per Month' : 'Per Day';
}

$editId = (int)($_GET['edit'] ?? 0);
$editRole = null;

if ($editId > 0) {
    $stmt = $pdo->prepare(
        'SELECT id,category_id,name,description,job_amount,amount_period,sort_order
         FROM local_job_roles
         WHERE id=? AND deleted_at IS NULL
         LIMIT 1'
    );
    $stmt->execute([$editId]);
    $editRole = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    if (!$editRole) {
        $error = 'Job role not found.';
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
                $category = (int)($_POST['category_id'] ?? 0);
                $name = trim((string)($_POST['name'] ?? ''));
                $desc = trim((string)($_POST['description'] ?? ''));
                $amount = (float)($_POST['job_amount'] ?? 0);
                $period = (string)($_POST['amount_period'] ?? 'per_day');
                $sortOrder = (int)($_POST['sort_order'] ?? 0);

                if ($category <= 0 || $name === '') {
                    throw new RuntimeException('Category and role name are required.');
                }
                if ($amount <= 0) {
                    throw new RuntimeException('Job amount must be greater than zero.');
                }
                if (!in_array($period, ['per_day', 'per_month'], true)) {
                    throw new RuntimeException('Invalid amount period.');
                }

                if ($id > 0) {
                    $stmt = $pdo->prepare(
                        'UPDATE local_job_roles
                         SET category_id=?,name=?,description=?,job_amount=?,amount_period=?,sort_order=?,updated_at=UTC_TIMESTAMP()
                         WHERE id=? AND deleted_at IS NULL'
                    );
                    $stmt->execute([
                        $category,
                        $name,
                        $desc !== '' ? $desc : null,
                        $amount,
                        $period,
                        $sortOrder,
                        $id
                    ]);
                    if ($stmt->rowCount() === 0) {
                        throw new RuntimeException('Job role not found or no changes were made.');
                    }
                    $_SESSION['local_role_flash'] = 'Job role updated.';
                } else {
                    $stmt = $pdo->prepare(
                        'INSERT INTO local_job_roles
                         (category_id,name,description,job_amount,amount_period,sort_order,is_active)
                         VALUES(?,?,?,?,?,?,1)'
                    );
                    $stmt->execute([
                        $category,
                        $name,
                        $desc !== '' ? $desc : null,
                        $amount,
                        $period,
                        $sortOrder
                    ]);
                    $_SESSION['local_role_flash'] = 'Job role added.';
                }

                header('Location: job_roles.php');
                exit;
            }

            if ($action === 'delete' && $id > 0) {
                $pdo->prepare(
                    'UPDATE local_job_roles
                     SET deleted_at=UTC_TIMESTAMP(),is_active=0,updated_at=UTC_TIMESTAMP()
                     WHERE id=?'
                )->execute([$id]);
                $_SESSION['local_role_flash'] = 'Job role archived.';
                header('Location: job_roles.php');
                exit;
            }

            throw new RuntimeException('Invalid role action.');
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    }
}

$flash = $_SESSION['local_role_flash'] ?? '';
unset($_SESSION['local_role_flash']);

$categories = $pdo->query(
    'SELECT id,name
     FROM local_categories
     WHERE deleted_at IS NULL AND is_active=1
     ORDER BY sort_order,name'
)->fetchAll(PDO::FETCH_ASSOC);

$roles = $pdo->query(
    'SELECT jr.id,jr.name,jr.description,jr.job_amount,jr.amount_period,jr.sort_order,jr.is_active,
            lc.name AS category_name
     FROM local_job_roles jr
     INNER JOIN local_categories lc ON lc.id=jr.category_id
     WHERE jr.deleted_at IS NULL
     ORDER BY lc.sort_order,lc.name,jr.sort_order,jr.name'
)->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'Job Roles';
require __DIR__ . '/includes/header.php';
?>
<main class="content">
  <div>
    <div class="page-kicker">MWH Local · Configuration</div>
    <h1 class="page-title">Job Roles</h1>
    <p class="muted">Local-specific roles with default job amount and billing period for the app.</p>
  </div>

  <?php if ($flash): ?><div class="alert alert-success mt-3"><?=re($flash)?></div><?php endif; ?>
  <?php if ($error): ?><div class="alert alert-danger mt-3"><?=re($error)?></div><?php endif; ?>

  <div class="row g-3 mt-1">
    <div class="col-xl-4">
      <section class="panel p-3">
        <div class="d-flex justify-content-between align-items-center mb-3">
          <h2 style="font-size:16px;margin:0"><?= $editRole ? 'Edit Job Role' : 'Add Job Role' ?></h2>
          <?php if ($editRole): ?><a class="btn-outline-local" href="job_roles.php"><i class="bi bi-x-lg"></i></a><?php endif; ?>
        </div>

        <form method="post">
          <input type="hidden" name="csrf" value="<?=re($csrf)?>">
          <input type="hidden" name="action" value="save">
          <input type="hidden" name="id" value="<?= $editRole ? (int)$editRole['id'] : 0 ?>">

          <div class="mb-2">
            <label class="form-label">Category *</label>
            <select class="form-select" name="category_id" required>
              <option value="">Select category</option>
              <?php foreach ($categories as $c): ?>
                <option value="<?=$c['id']?>" <?=$editRole && (int)$editRole['category_id']===(int)$c['id']?'selected':''?>>
                  <?=re($c['name'])?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="mb-2">
            <label class="form-label">Role Name *</label>
            <input class="form-control" name="name" required value="<?=re($editRole['name'] ?? '')?>" placeholder="e.g. Tandoor Chef">
          </div>

          <div class="row g-2">
            <div class="col-7">
              <label class="form-label">Job Amount *</label>
              <div class="input-group">
                <span class="input-group-text">₹</span>
                <input class="form-control" type="number" name="job_amount" min="1" step="0.01" required value="<?=re($editRole['job_amount'] ?? '')?>" placeholder="1500">
              </div>
            </div>
            <div class="col-5">
              <label class="form-label">Amount Period *</label>
              <select class="form-select" name="amount_period" required>
                <option value="per_day" <?=(!$editRole || ($editRole['amount_period'] ?? '')==='per_day')?'selected':''?>>Per Day</option>
                <option value="per_month" <?=($editRole['amount_period'] ?? '')==='per_month'?'selected':''?>>Per Month</option>
              </select>
            </div>
          </div>

          <div class="mb-2 mt-2">
            <label class="form-label">Description</label>
            <textarea class="form-control" name="description" rows="3" placeholder="Role responsibilities or notes"><?=re($editRole['description'] ?? '')?></textarea>
          </div>

          <div class="mb-3">
            <label class="form-label">Sort Order</label>
            <input class="form-control" type="number" name="sort_order" value="<?=re($editRole['sort_order'] ?? 0)?>">
          </div>

          <button class="btn-local" type="submit">
            <i class="bi bi-check2"></i>&nbsp; <?= $editRole ? 'Update Role' : 'Add Role' ?>
          </button>
        </form>
      </section>
    </div>

    <div class="col-xl-8">
      <section class="panel">
        <div class="panel-head">
          <div>
            <h2>Local Job Roles</h2>
            <p class="muted">Each role has a default amount that can be consumed by the app.</p>
          </div>
        </div>

        <div class="table-wrap">
          <table class="table">
            <thead>
              <tr>
                <th>Category</th>
                <th>Role</th>
                <th>Job Amount</th>
                <th>Period</th>
                <th>Description</th>
                <th>Status</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
            <?php if (!$roles): ?>
              <tr><td colspan="7" class="text-center muted py-5">No job roles yet.</td></tr>
            <?php else: foreach ($roles as $r): ?>
              <tr>
                <td><?=re($r['category_name'])?></td>
                <td><strong><?=re($r['name'])?></strong></td>
                <td><strong>₹<?=number_format((float)$r['job_amount'],2)?></strong></td>
                <td><?=re(periodLabel((string)$r['amount_period']))?></td>
                <td><?=re($r['description'] ?: '—')?></td>
                <td><?=!empty($r['is_active'])?'Active':'Inactive'?></td>
                <td>
                  <div class="d-flex gap-1">
                    <a class="btn-outline-local" href="job_roles.php?edit=<?=$r['id']?>" title="Edit">
                      <i class="bi bi-pencil"></i>
                    </a>
                    <form method="post" onsubmit="return confirm('Archive this role?')">
                      <input type="hidden" name="csrf" value="<?=re($csrf)?>">
                      <input type="hidden" name="action" value="delete">
                      <input type="hidden" name="id" value="<?=$r['id']?>">
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
