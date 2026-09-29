<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
requireAdmin();
require_once __DIR__ . '/../api/config/database.php';

function de(mixed $v): string {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function dutyTime(?string $value): string {
    if (!$value) return '—';
    try {
        $dt = new DateTime($value, new DateTimeZone('UTC'));
        $dt->setTimezone(new DateTimeZone('Asia/Kolkata'));
        return $dt->format('d M Y, h:i A');
    } catch (Throwable) {
        return (string)$value;
    }
}

function dutyStatusLabel(string $status): string {
    return match ($status) {
        'arrived' => 'Arrived',
        'active' => 'Duty Started',
        'completed' => 'Completed',
        'cancelled' => 'Cancelled',
        default => 'Assigned',
    };
}

function dutyAttendanceLabel(string $status): string {
    return match ($status) {
        'arrived' => 'Present · Arrived',
        'active' => 'Present · On Duty',
        'completed' => 'Present · Completed',
        'cancelled' => 'Cancelled',
        default => 'Not Arrived',
    };
}

$search = trim((string)($_GET['search'] ?? ''));
$status = (string)($_GET['status'] ?? 'all');
$from = trim((string)($_GET['from'] ?? ''));
$to = trim((string)($_GET['to'] ?? ''));

$allowedStatuses = ['assigned', 'arrived', 'active', 'completed', 'cancelled'];

$where = ['a.id IS NOT NULL'];
$params = [];

if ($search !== '') {
    $where[] = '(s.name LIKE ? OR s.mobile LIKE ? OR c.name LIKE ? OR r.title LIKE ? OR r.work_location LIKE ? OR ljr.name LIKE ?)';
    $term = '%' . $search . '%';
    array_push($params, $term, $term, $term, $term, $term, $term);
}
if ($status !== 'all' && in_array($status, $allowedStatuses, true)) {
    $where[] = 'a.status = ?';
    $params[] = $status;
}
if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
    $where[] = 'r.shift_date >= ?';
    $params[] = $from;
}
if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
    $where[] = 'r.shift_date <= ?';
    $params[] = $to;
}

$sql = 'SELECT
            a.id AS assignment_id,
            a.status,
            a.assigned_at,
            a.arrival_at,
            a.start_at,
            a.end_at,
            a.payout_amount,
            r.id AS requirement_id,
            r.title,
            r.work_location,
            r.work_address,
            r.shift_date,
            r.shift_start,
            r.shift_end,
            s.id AS staff_id,
            s.name AS staff_name,
            s.mobile AS staff_mobile,
            c.name AS contractor_name,
            lcp.business_name AS contractor_business,
            ljr.name AS job_role_name,
            att.check_in_at,
            att.check_out_at
        FROM local_assignments a
        INNER JOIN local_requirements r ON r.id = a.requirement_id
        INNER JOIN local_users s ON s.id = a.staff_user_id AND s.role = "staff"
        INNER JOIN local_users c ON c.id = a.contractor_user_id AND c.role = "contractor"
        LEFT JOIN local_contractor_profiles lcp ON lcp.user_id = c.id
        LEFT JOIN local_job_roles ljr ON ljr.id = r.job_role_id
        LEFT JOIN local_attendance att ON att.assignment_id = a.id AND att.staff_user_id = a.staff_user_id
        WHERE ' . implode(' AND ', $where) . '
        ORDER BY
            CASE a.status
                WHEN "active" THEN 0
                WHEN "arrived" THEN 1
                WHEN "assigned" THEN 2
                WHEN "completed" THEN 3
                WHEN "cancelled" THEN 4
                ELSE 5
            END,
            r.shift_date DESC,
            a.id DESC
        LIMIT 250';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$duties = $stmt->fetchAll(PDO::FETCH_ASSOC);

$counts = [
    'assigned' => 0,
    'arrived' => 0,
    'active' => 0,
    'completed' => 0,
    'cancelled' => 0,
];
foreach ($duties as $duty) {
    $key = (string)$duty['status'];
    if (isset($counts[$key])) $counts[$key]++;
}

$pageTitle = 'Duty';
require __DIR__ . '/includes/header.php';
?>
<main class="content">
  <div class="d-flex justify-content-between align-items-end gap-3 flex-wrap">
    <div>
      <div class="page-kicker">MWH Local · Operations</div>
      <h1 class="page-title">Duty</h1>
      <p class="muted mb-0">Track staff arrival, duty start and duty completion attendance.</p>
    </div>
    <div class="d-flex gap-2">
      <span class="btn-outline-local" style="cursor:default"><i class="bi bi-arrow-repeat"></i> Auto refresh · 15 sec</span>
      <a class="btn-local text-decoration-none" href="duty.php"><i class="bi bi-arrow-clockwise"></i>&nbsp; Refresh</a>
    </div>
  </div>

  <div class="stat-grid">
    <div class="stat"><div class="stat-label">Assigned</div><div class="stat-value"><?=number_format($counts['assigned'])?></div></div>
    <div class="stat"><div class="stat-label">Arrived</div><div class="stat-value"><?=number_format($counts['arrived'])?></div></div>
    <div class="stat"><div class="stat-label">On Duty</div><div class="stat-value"><?=number_format($counts['active'])?></div></div>
    <div class="stat"><div class="stat-label">Completed</div><div class="stat-value"><?=number_format($counts['completed'])?></div></div>
  </div>

  <section class="panel">
    <div class="panel-head">
      <div>
        <h2>Duty Attendance</h2>
        <p class="muted">Every selected staff assignment is listed here. Arrival/start/completion times are read from the duty attendance records.</p>
      </div>
    </div>

    <form class="toolbar" method="get">
      <div class="row g-2 align-items-end">
        <div class="col-xl-4 col-lg-4 col-md-6">
          <label class="form-label">Search</label>
          <input class="form-control" name="search" value="<?=de($search)?>" placeholder="Staff, contractor, role or location">
        </div>
        <div class="col-xl-2 col-lg-2 col-md-3">
          <label class="form-label">Status</label>
          <select class="form-select" name="status">
            <option value="all">All statuses</option>
            <?php foreach ($allowedStatuses as $s): ?>
              <option value="<?=de($s)?>" <?=$status === $s ? 'selected' : ''?>><?=de(dutyStatusLabel($s))?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-xl-2 col-lg-2 col-md-3">
          <label class="form-label">Shift From</label>
          <input class="form-control" type="date" name="from" value="<?=de($from)?>">
        </div>
        <div class="col-xl-2 col-lg-2 col-md-3">
          <label class="form-label">Shift To</label>
          <input class="form-control" type="date" name="to" value="<?=de($to)?>">
        </div>
        <div class="col-xl-2 col-lg-2 col-md-3 d-flex gap-2">
          <button class="btn-local flex-grow-1" type="submit"><i class="bi bi-search"></i>&nbsp; Filter</button>
          <a class="btn-outline-local" href="duty.php" title="Clear filters"><i class="bi bi-x-lg"></i></a>
        </div>
      </div>
    </form>

    <div class="table-wrap">
      <table class="table table-hover align-middle" style="min-width:1450px">
        <thead>
          <tr>
            <th>Staff</th>
            <th>Contractor</th>
            <th>Duty</th>
            <th>Location</th>
            <th>Shift</th>
            <th>Arrival</th>
            <th>Start Duty</th>
            <th>Completed</th>
            <th>Attendance</th>
            <th>Status</th>
            <th>Payout</th>
          </tr>
        </thead>
        <tbody>
        <?php if (!$duties): ?>
          <tr>
            <td colspan="11" class="text-center muted py-5">
              <div style="font-size:28px"><i class="bi bi-calendar2-x"></i></div>
              <div class="mt-2">No duty records found.</div>
              <div style="font-size:11px">A duty will appear after a staff member is selected for a requirement.</div>
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($duties as $duty): ?>
            <?php
              $statusText = (string)$duty['status'];
              $startTime = $duty['start_at'] ?: $duty['check_in_at'];
              $completeTime = $duty['end_at'] ?: $duty['check_out_at'];
              $contractor = (string)($duty['contractor_business'] ?: $duty['contractor_name']);
              $role = (string)($duty['job_role_name'] ?: 'Staff Duty');
            ?>
            <tr>
              <td>
                <strong><?=de($duty['staff_name'])?></strong>
                <div class="muted" style="font-size:10px"><?=de($duty['staff_mobile'])?></div>
              </td>
              <td>
                <?=de($contractor)?>
                <div class="muted" style="font-size:10px">Assignment #<?=number_format((int)$duty['assignment_id'])?></div>
              </td>
              <td>
                <strong><?=de($duty['title'])?></strong>
                <div class="muted" style="font-size:10px"><?=de($role)?></div>
              </td>
              <td>
                <?=de($duty['work_location'])?>
                <?php if (!empty($duty['work_address'])): ?>
                  <div class="muted" style="font-size:10px"><?=de($duty['work_address'])?></div>
                <?php endif; ?>
              </td>
              <td>
                <?=de($duty['shift_date'])?>
                <div class="muted" style="font-size:10px"><?=de(trim(((string)($duty['shift_start'] ?? '')) . ' - ' . ((string)($duty['shift_end'] ?? '')), ' -'))?></div>
              </td>
              <td><?=de(dutyTime($duty['arrival_at'] ?? null))?></td>
              <td><?=de(dutyTime($startTime))?></td>
              <td><?=de(dutyTime($completeTime))?></td>
              <td><span class="status <?=de($statusText)?>"><?=de(dutyAttendanceLabel($statusText))?></span></td>
              <td><span class="status <?=de($statusText)?>"><?=de(dutyStatusLabel($statusText))?></span></td>
              <td><strong>₹<?=number_format((float)$duty['payout_amount'], 2)?></strong></td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </section>
</main>
<script>
setTimeout(function () {
  window.location.reload();
}, 15000);
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>
