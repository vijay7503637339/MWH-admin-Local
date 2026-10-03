<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
requireAdmin();
require_once __DIR__ . '/../api/config/database.php';
require_once __DIR__ . '/../api/config/fcm.php';

if (empty($_SESSION['local_notification_csrf'])) {
    $_SESSION['local_notification_csrf'] = bin2hex(random_bytes(32));
}

$csrf = $_SESSION['local_notification_csrf'];
$flash = $_SESSION['local_notification_flash'] ?? '';
$error = $_SESSION['local_notification_error'] ?? '';
unset($_SESSION['local_notification_flash'], $_SESSION['local_notification_error']);

function notificationE(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$users = $pdo->query(
    'SELECT u.id,u.name,u.mobile,u.role,
            (SELECT COUNT(*) FROM local_fcm_tokens ft
             WHERE ft.user_id=u.id AND ft.disabled_at IS NULL) AS device_count
     FROM local_users u
     WHERE u.account_status="verified"
       AND u.deleted_at IS NULL
     ORDER BY u.role ASC,u.name ASC'
)->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($csrf, (string)($_POST['csrf'] ?? ''))) {
        $_SESSION['local_notification_error'] = 'Invalid request token.';
        header('Location: notifications.php');
        exit;
    }

    $target = trim((string)($_POST['target'] ?? ''));
    $title = trim((string)($_POST['title'] ?? ''));
    $message = trim((string)($_POST['message'] ?? ''));

    try {
        if ($title === '' || mb_strlen($title) > 190) {
            throw new RuntimeException('Notification title is required and must be 190 characters or less.');
        }
        if ($message === '') {
            throw new RuntimeException('Notification message is required.');
        }
        if (mb_strlen($message) > 4000) {
            throw new RuntimeException('Notification message is too long.');
        }

        $userIds = [];

        if ($target === 'staff_all') {
            $stmt = $pdo->query(
                'SELECT id FROM local_users
                 WHERE role="staff" AND account_status="verified" AND deleted_at IS NULL'
            );
            $userIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
        } elseif ($target === 'contractors_all') {
            $stmt = $pdo->query(
                'SELECT id FROM local_users
                 WHERE role="contractor" AND account_status="verified" AND deleted_at IS NULL'
            );
            $userIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
        } elseif ($target === 'all_users') {
            $stmt = $pdo->query(
                'SELECT id FROM local_users
                 WHERE account_status="verified" AND deleted_at IS NULL'
            );
            $userIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
        } elseif (str_starts_with($target, 'user_')) {
            $userId = (int)substr($target, 5);
            if ($userId <= 0) {
                throw new RuntimeException('Invalid user selected.');
            }

            $stmt = $pdo->prepare(
                'SELECT id FROM local_users
                 WHERE id=? AND account_status="verified" AND deleted_at IS NULL
                 LIMIT 1'
            );
            $stmt->execute([$userId]);
            $found = $stmt->fetchColumn();
            if (!$found) {
                throw new RuntimeException('Selected user is not an active verified user.');
            }
            $userIds = [$userId];
        } else {
            throw new RuntimeException('Please select a notification recipient.');
        }

        if (!$userIds) {
            throw new RuntimeException('No verified users match the selected recipient group.');
        }

        $result = localNotifyUsers(
            $pdo,
            $userIds,
            'admin',
            $title,
            $message,
            ['screen' => 'notifications', 'source' => 'admin']
        );

        $push = $result['push'] ?? [];
        $pushMessage = (string)($push['message'] ?? 'Push processing completed.');

        $_SESSION['local_notification_flash'] =
            'Notification saved for ' . count($userIds) . ' user(s). '
            . 'Push: ' . $pushMessage;
    } catch (Throwable $e) {
        $_SESSION['local_notification_error'] = $e->getMessage();
    }

    header('Location: notifications.php');
    exit;
}

$pageTitle = 'Notifications';
require __DIR__ . '/includes/header.php';
?>
<main class="content">
  <div class="d-flex justify-content-between align-items-end gap-3 flex-wrap">
    <div>
      <div class="page-kicker">MWH Local · Communication</div>
      <h1 class="page-title">Notifications</h1>
      <p class="muted mb-0">Send an in-app and push notification to verified Local users.</p>
    </div>
  </div>

  <?php if ($flash): ?>
    <div class="alert alert-success mt-3"><?=notificationE($flash)?></div>
  <?php endif; ?>

  <?php if ($error): ?>
    <div class="alert alert-danger mt-3"><?=notificationE($error)?></div>
  <?php endif; ?>

  <section class="panel mt-3">
    <div class="panel-head">
      <div>
        <h2>Send Notification</h2>
        <p class="muted">Push delivery uses Firebase Cloud Messaging HTTP v1.</p>
      </div>
    </div>

    <form method="post" class="p-4">
      <input type="hidden" name="csrf" value="<?=notificationE($csrf)?>">

      <div class="row g-3">
        <div class="col-lg-6">
          <label class="form-label fw-semibold">Recipients</label>
          <select name="target" class="form-select" required>
            <option value="">Select recipients</option>
            <option value="staff_all">All Staff / Chefs</option>
            <option value="contractors_all">All Contractors</option>
            <option value="all_users">All Verified Users</option>
            <option disabled>────────── Individual Users ──────────</option>
            <?php foreach ($users as $user): ?>
              <option value="user_<?=$user['id']?>">
                <?=notificationE(ucfirst((string)$user['role']).' · '.$user['name'].' · '.$user['mobile'])?>
                <?php if ((int)$user['device_count'] > 0): ?> (Push ready)<?php endif; ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-lg-6">
          <label class="form-label fw-semibold">Title</label>
          <input
            name="title"
            class="form-control"
            maxlength="190"
            required
            placeholder="Example: New job available near you">
        </div>

        <div class="col-12">
          <label class="form-label fw-semibold">Message</label>
          <textarea
            name="message"
            class="form-control"
            rows="5"
            maxlength="4000"
            required
            placeholder="Write the notification message..."></textarea>
        </div>

        <div class="col-12 d-flex justify-content-end">
          <button type="submit" class="btn-local">
            <i class="bi bi-send-fill"></i>&nbsp; Send Notification
          </button>
        </div>
      </div>
    </form>
  </section>

  <section class="panel mt-3">
    <div class="panel-head">
      <div>
        <h2>Verified Users</h2>
        <p class="muted">Users with an active FCM device token can receive push notifications.</p>
      </div>
    </div>
    <div class="table-wrap">
      <table class="table table-hover align-middle">
        <thead>
          <tr>
            <th>User</th>
            <th>Role</th>
            <th>Mobile</th>
            <th>Push Devices</th>
          </tr>
        </thead>
        <tbody>
        <?php if (!$users): ?>
          <tr><td colspan="4" class="text-center muted py-5">No verified users.</td></tr>
        <?php else: ?>
          <?php foreach ($users as $user): ?>
            <tr>
              <td><strong><?=notificationE($user['name'])?></strong></td>
              <td><?=notificationE(ucfirst((string)$user['role']))?></td>
              <td><?=notificationE($user['mobile'])?></td>
              <td>
                <?php if ((int)$user['device_count'] > 0): ?>
                  <span class="status verified"><?=number_format((int)$user['device_count'])?> active</span>
                <?php else: ?>
                  <span class="status">Not registered</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </section>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
