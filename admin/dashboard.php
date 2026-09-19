<?php
declare(strict_types=1);
require_once __DIR__ . '/../api/config/database.php';

$pageTitle='Dashboard';

$counts = [];
$queries = [
  'contractors' => 'SELECT COUNT(*) FROM local_users WHERE role="contractor" AND deleted_at IS NULL',
  'staff' => 'SELECT COUNT(*) FROM local_users WHERE role="staff" AND deleted_at IS NULL',
  'requirements' => 'SELECT COUNT(*) FROM local_requirements WHERE deleted_at IS NULL',
  'open_requirements' => 'SELECT COUNT(*) FROM local_requirements WHERE status IN ("open","active") AND deleted_at IS NULL',
  'applications' => 'SELECT COUNT(*) FROM local_applications',
  'assignments' => 'SELECT COUNT(*) FROM local_assignments',
  'pending_staff' => 'SELECT COUNT(*) FROM local_users WHERE role="staff" AND account_status="pending_verification" AND deleted_at IS NULL',
  'pending_contractors' => 'SELECT COUNT(*) FROM local_users WHERE role="contractor" AND account_status="pending_verification" AND deleted_at IS NULL',
];
foreach($queries as $key=>$sql){$counts[$key]=(int)$pdo->query($sql)->fetchColumn();}
$recent=$pdo->query(
  'SELECT lr.id,lr.title,lr.work_location,lr.shift_date,lr.openings_count,lr.payout_amount,lr.status,cu.name AS contractor_name
   FROM local_requirements lr
   INNER JOIN local_users cu ON cu.id=lr.contractor_user_id
   WHERE lr.deleted_at IS NULL ORDER BY lr.created_at DESC LIMIT 8'
)->fetchAll(PDO::FETCH_ASSOC);

require __DIR__ . '/includes/header.php';
?>
<main class="content">
  <div class="d-flex justify-content-between align-items-end gap-3 flex-wrap"><div><div class="page-kicker">MWH Local · Operations</div><h1 class="page-title">Dashboard</h1><p class="muted mb-0">Overview of Local contractors, staff and staffing activity.</p></div><a class="btn-local text-decoration-none" href="requirements.php"><i class="bi bi-clipboard-plus"></i>&nbsp; View Requirements</a></div>
  <div class="stat-grid">
    <div class="stat"><div class="stat-label">Contractors</div><div class="stat-value"><?=$counts['contractors']?></div></div>
    <div class="stat"><div class="stat-label">Staff / Chefs</div><div class="stat-value"><?=$counts['staff']?></div></div>
    <div class="stat"><div class="stat-label">Open Requirements</div><div class="stat-value"><?=$counts['open_requirements']?></div></div>
    <div class="stat"><div class="stat-label">Applications</div><div class="stat-value"><?=$counts['applications']?></div></div>
  </div>
  <div class="row g-3">
    <div class="col-xl-8"><section class="panel"><div class="panel-head"><div><h2>Recent Requirements</h2><p class="muted">Latest contractor staffing requests.</p></div><a class="btn-outline-local" href="requirements.php">All requirements <i class="bi bi-arrow-right"></i></a></div><div class="table-wrap"><table class="table table-hover align-middle"><thead><tr><th>Requirement</th><th>Contractor</th><th>Location</th><th>Shift</th><th>Openings</th><th>Status</th></tr></thead><tbody><?php if(!$recent): ?><tr><td colspan="6" class="text-center muted py-5">No requirements yet.</td></tr><?php else: foreach($recent as $r): ?><tr><td><strong><?=htmlspecialchars($r['title'])?></strong><div class="muted" style="font-size:10px">₹<?=number_format((float)$r['payout_amount'],2)?> / shift</div></td><td><?=htmlspecialchars($r['contractor_name'])?></td><td><?=htmlspecialchars($r['work_location'])?></td><td><?=htmlspecialchars($r['shift_date'])?></td><td><?=number_format((int)$r['openings_count'])?></td><td><span class="status <?=htmlspecialchars($r['status'])?>"><?=htmlspecialchars(ucwords(str_replace('_',' ',$r['status'])))?></span></td></tr><?php endforeach; endif;?></tbody></table></div></section></div>
    <div class="col-xl-4"><section class="panel"><div class="panel-head"><div><h2>Review Queue</h2><p class="muted">Accounts awaiting admin action.</p></div></div><div class="p-3"><a class="btn-outline-local w-100 mb-2 justify-content-between" href="staff.php?status=pending_verification">Staff pending <strong><?=$counts['pending_staff']?></strong></a><a class="btn-outline-local w-100 justify-content-between" href="contractors.php?status=pending_verification">Contractors pending <strong><?=$counts['pending_contractors']?></strong></a><hr><div class="muted" style="font-size:11px">Assignments: <strong style="color:#172033"><?=$counts['assignments']?></strong></div><div class="muted mt-2" style="font-size:11px">Total requirements: <strong style="color:#172033"><?=$counts['requirements']?></strong></div></div></section></div>
  </div>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
