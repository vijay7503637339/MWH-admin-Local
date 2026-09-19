<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
requireAdmin();
require_once __DIR__ . '/../../api/config/database.php';

if (!isset($localRole) || !in_array($localRole, ['staff','contractor'], true)) {
    http_response_code(400);
    exit('Invalid Local account role.');
}

if (empty($_SESSION['local_user_csrf'])) {
    $_SESSION['local_user_csrf'] = bin2hex(random_bytes(32));
}
$csrf = (string)$_SESSION['local_user_csrf'];
$admin = currentAdmin();
$pageTitle = $localRole === 'staff' ? 'Staff / Chefs' : 'Contractors';
$label = $localRole === 'staff' ? 'Staff / Chefs' : 'Contractors';
$search = trim((string)($_GET['search'] ?? ''));
$status = (string)($_GET['status'] ?? 'all');
$viewId = (int)($_GET['view'] ?? 0);
$editId = (int)($_GET['edit'] ?? 0);
$flash = '';
$error = '';
$allowedStatuses = ['pending_verification','verified','rejected','blocked'];

function localE(mixed $value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}
function localStatusLabel(string $status): string {
    return ucwords(str_replace('_', ' ', $status));
}
function localDateTime(?string $value): string {
    if (!$value) return '—';
    $ts = strtotime($value);
    return $ts ? date('d M Y, h:i A', $ts) : $value;
}
function localMaskAadhaar(?string $value): string {
    $digits = preg_replace('/\D+/', '', (string)$value) ?? '';
    if (strlen($digits) !== 12) return $value ?: '—';
    return 'XXXX XXXX ' . substr($digits, -4);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($csrf, (string)($_POST['csrf'] ?? ''))) {
        $error = 'Invalid request token. Please reload and try again.';
    } else {
        $action = (string)($_POST['action'] ?? '');
        $id = (int)($_POST['id'] ?? 0);
        try {
            if ($id <= 0) throw new RuntimeException('Invalid account.');

            $find = $pdo->prepare('SELECT id,name,mobile,email,aadhaar_number,role,account_status FROM local_users WHERE id=? AND role=? AND deleted_at IS NULL LIMIT 1');
            $find->execute([$id,$localRole]);
            $target = $find->fetch(PDO::FETCH_ASSOC);
            if (!$target) throw new RuntimeException('Account not found.');

            if ($action === 'status') {
                $newStatus = (string)($_POST['account_status'] ?? '');
                if (!in_array($newStatus, $allowedStatuses, true)) throw new RuntimeException('Invalid account status.');

                if ($newStatus === 'verified') {
                    $stmt = $pdo->prepare('UPDATE local_users SET account_status=?,verified_at=UTC_TIMESTAMP(),verified_by_admin_id=?,rejection_reason=NULL,blocked_at=NULL,blocked_by_admin_id=NULL,block_reason=NULL,updated_at=UTC_TIMESTAMP() WHERE id=?');
                    $stmt->execute([$newStatus,$admin['id'],$id]);
                } elseif ($newStatus === 'rejected') {
                    $reason = trim((string)($_POST['reason'] ?? ''));
                    $stmt = $pdo->prepare('UPDATE local_users SET account_status=?,rejection_reason=?,verified_at=NULL,verified_by_admin_id=NULL,updated_at=UTC_TIMESTAMP() WHERE id=?');
                    $stmt->execute([$newStatus,$reason !== '' ? $reason : null,$id]);
                } elseif ($newStatus === 'blocked') {
                    $reason = trim((string)($_POST['reason'] ?? ''));
                    $stmt = $pdo->prepare('UPDATE local_users SET account_status=?,blocked_at=UTC_TIMESTAMP(),blocked_by_admin_id=?,block_reason=?,updated_at=UTC_TIMESTAMP() WHERE id=?');
                    $stmt->execute([$newStatus,$admin['id'],$reason !== '' ? $reason : null,$id]);
                } else {
                    $stmt = $pdo->prepare('UPDATE local_users SET account_status=?,updated_at=UTC_TIMESTAMP() WHERE id=?');
                    $stmt->execute([$newStatus,$id]);
                }

                $log = $pdo->prepare('INSERT INTO local_audit_logs(actor_type,actor_id,action,entity_type,entity_id,new_values,ip_address,user_agent) VALUES(?,?,?,?,?,?,?,?,?)');
                $log->execute(['local_admin',$admin['id'],'local_user.status_changed','local_user',$id,json_encode(['status'=>$newStatus,'role'=>$localRole],JSON_UNESCAPED_UNICODE),$_SERVER['REMOTE_ADDR'] ?? null,$_SERVER['HTTP_USER_AGENT'] ?? null]);
                $flash = 'Account status updated successfully.';
            } elseif ($action === 'save') {
                $name = trim((string)($_POST['name'] ?? ''));
                $mobile = preg_replace('/\D+/', '', (string)($_POST['mobile'] ?? '')) ?? '';
                $email = strtolower(trim((string)($_POST['email'] ?? '')));
                $aadhaar = preg_replace('/\D+/', '', (string)($_POST['aadhaar_number'] ?? '')) ?? '';

                if ($name === '' || mb_strlen($name) > 150) throw new RuntimeException('Name is required.');
                if (!preg_match('/^[6-9]\d{9}$/', $mobile)) throw new RuntimeException('Enter a valid 10-digit mobile number.');
                if ($email !== '' && !filter_var($email,FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Enter a valid email.');
                if ($aadhaar !== '' && !preg_match('/^\d{12}$/',$aadhaar)) throw new RuntimeException('Aadhaar must contain 12 digits.');

                $dup=$pdo->prepare('SELECT id FROM local_users WHERE mobile=? AND id<>? AND deleted_at IS NULL LIMIT 1');
                $dup->execute([$mobile,$id]);
                if($dup->fetch()) throw new RuntimeException('Mobile number is already registered.');

                $dup=$pdo->prepare('SELECT id FROM local_users WHERE email=? AND id<>? AND email IS NOT NULL AND deleted_at IS NULL LIMIT 1');
                $dup->execute([$email !== '' ? $email : null,$id]);
                if($email !== '' && $dup->fetch()) throw new RuntimeException('Email address is already registered.');

                $pdo->beginTransaction();
                $stmt=$pdo->prepare('UPDATE local_users SET name=?,mobile=?,email=?,aadhaar_number=?,updated_at=UTC_TIMESTAMP() WHERE id=? AND role=?');
                $stmt->execute([$name,$mobile,$email !== '' ? $email : null,$aadhaar !== '' ? $aadhaar : null,$id,$localRole]);

                if($localRole === 'contractor'){
                    $profile=$pdo->prepare('INSERT INTO local_contractor_profiles(user_id,whatsapp_mobile,business_name,business_type,full_address,city,state,postcode,gstin) VALUES(?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE whatsapp_mobile=VALUES(whatsapp_mobile),business_name=VALUES(business_name),business_type=VALUES(business_type),full_address=VALUES(full_address),city=VALUES(city),state=VALUES(state),postcode=VALUES(postcode),gstin=VALUES(gstin)');
                    $profile->execute([
                        $id,$mobile,
                        trim((string)($_POST['business_name'] ?? '')) ?: null,
                        trim((string)($_POST['business_type'] ?? '')) ?: null,
                        trim((string)($_POST['full_address'] ?? '')) ?: null,
                        trim((string)($_POST['city'] ?? '')) ?: null,
                        trim((string)($_POST['state'] ?? '')) ?: null,
                        trim((string)($_POST['postcode'] ?? '')) ?: null,
                        trim((string)($_POST['gstin'] ?? '')) ?: null,
                    ]);
                } else {
                    $dob=trim((string)($_POST['date_of_birth'] ?? ''));
                    $dob=$dob!=='' ? $dob : null;
                    if($dob!==null && !preg_match('/^\d{4}-\d{2}-\d{2}$/',$dob)) throw new RuntimeException('Invalid date of birth.');
                    $profile=$pdo->prepare('INSERT INTO local_staff_profiles(user_id,date_of_birth,gender,city,full_address,experience_years,skills) VALUES(?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE date_of_birth=VALUES(date_of_birth),gender=VALUES(gender),city=VALUES(city),full_address=VALUES(full_address),experience_years=VALUES(experience_years),skills=VALUES(skills)');
                    $experience=(float)($_POST['experience_years'] ?? 0);
                    if($experience<0 || $experience>60) throw new RuntimeException('Experience must be between 0 and 60 years.');
                    $profile->execute([
                        $id,$dob,
                        trim((string)($_POST['gender'] ?? '')) ?: null,
                        trim((string)($_POST['city'] ?? '')) ?: null,
                        trim((string)($_POST['full_address'] ?? '')) ?: null,
                        $experience,
                        trim((string)($_POST['skills'] ?? '')) ?: null,
                    ]);
                }

                $log=$pdo->prepare('INSERT INTO local_audit_logs(actor_type,actor_id,action,entity_type,entity_id,new_values,ip_address,user_agent) VALUES(?,?,?,?,?,?,?,?,?)');
                $log->execute(['local_admin',$admin['id'],'local_user.updated','local_user',$id,json_encode(['role'=>$localRole],JSON_UNESCAPED_UNICODE),$_SERVER['REMOTE_ADDR'] ?? null,$_SERVER['HTTP_USER_AGENT'] ?? null]);
                $pdo->commit();
                $flash='Account details saved successfully.';
                $viewId=$id;
                $editId=0;
            } elseif ($action === 'delete') {
                $stmt=$pdo->prepare('UPDATE local_users SET deleted_at=UTC_TIMESTAMP(),updated_at=UTC_TIMESTAMP() WHERE id=? AND role=?');
                $stmt->execute([$id,$localRole]);
                $flash=$stmt->rowCount() ? 'Account moved to deleted records.' : 'Account not found.';
            } else {
                throw new RuntimeException('Unknown action.');
            }
        } catch(Throwable $ex) {
            if($pdo->inTransaction()) $pdo->rollBack();
            $error=$ex->getMessage();
        }
    }
}

$where=['role=?','deleted_at IS NULL'];
$params=[$localRole];
if($search!==''){
    $where[]='(name LIKE ? OR mobile LIKE ? OR email LIKE ? OR aadhaar_number LIKE ?)';
    $term='%'.$search.'%';
    array_push($params,$term,$term,$term,$term);
}
if(in_array($status,$allowedStatuses,true)){
    $where[]='account_status=?';
    $params[]=$status;
}
$sql='SELECT id,name,mobile,email,aadhaar_number,role,account_status,created_at,updated_at FROM local_users WHERE '.implode(' AND ',$where).' ORDER BY created_at DESC';
$stmt=$pdo->prepare($sql);$stmt->execute($params);$accounts=$stmt->fetchAll(PDO::FETCH_ASSOC);
$counts=['total'=>count($accounts),'verified'=>0,'pending_verification'=>0,'rejected'=>0,'blocked'=>0];
foreach($accounts as $row){$s=(string)$row['account_status'];if(isset($counts[$s]))$counts[$s]++;}

$selected=null;$selectedProfile=null;
$selectedId=$viewId ?: $editId;
if($selectedId>0){
    $stmt=$pdo->prepare('SELECT * FROM local_users WHERE id=? AND role=? AND deleted_at IS NULL LIMIT 1');
    $stmt->execute([$selectedId,$localRole]);
    $selected=$stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    if($selected){
        if($localRole==='contractor'){
            $stmt=$pdo->prepare('SELECT * FROM local_contractor_profiles WHERE user_id=? LIMIT 1');
        }else{
            $stmt=$pdo->prepare('SELECT * FROM local_staff_profiles WHERE user_id=? LIMIT 1');
        }
        $stmt->execute([$selectedId]);$selectedProfile=$stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
}

require __DIR__ . '/header.php';
?>
<main class="content">
  <div class="d-flex justify-content-between align-items-end gap-3 flex-wrap">
    <div><div class="page-kicker">MWH Local · Account Management</div><h1 class="page-title"><?=$label?></h1><p class="muted mb-0">Manage Local <?=$localRole==='staff'?'staff and chefs':'contractor accounts'?> independently from MWH Hospitality.</p></div>
  </div>

  <?php if($flash!==''): ?><div class="alert alert-success mt-3"><?=localE($flash)?></div><?php endif; ?>
  <?php if($error!==''): ?><div class="alert alert-danger mt-3"><?=localE($error)?></div><?php endif; ?>

  <div class="stat-grid">
    <div class="stat"><div class="stat-label">Matching accounts</div><div class="stat-value"><?=$counts['total']?></div></div>
    <div class="stat"><div class="stat-label">Verified</div><div class="stat-value"><?=$counts['verified']?></div></div>
    <div class="stat"><div class="stat-label">Pending</div><div class="stat-value"><?=$counts['pending_verification']?></div></div>
    <div class="stat"><div class="stat-label">Blocked</div><div class="stat-value"><?=$counts['blocked']?></div></div>
  </div>

  <?php if($selected && $viewId>0): ?>
  <section class="panel mb-3">
    <div class="panel-head"><div><h2>Account #<?=$selected['id']?></h2><p class="muted">Profile details and Local account state.</p></div><div class="d-flex gap-2"><a class="btn-outline-local" href="?edit=<?=$selected['id']?>">Edit</a><a class="btn-outline-local" href="<?=localE(basename($_SERVER['PHP_SELF']))?>">Back</a></div></div>
    <div class="p-3"><div class="row g-3">
      <div class="col-md-6"><div class="detail-box"><span>Name</span><strong><?=localE($selected['name'])?></strong></div></div>
      <div class="col-md-6"><div class="detail-box"><span>Mobile</span><strong><?=localE($selected['mobile'])?></strong></div></div>
      <div class="col-md-6"><div class="detail-box"><span>Email</span><strong><?=localE($selected['email'] ?: '—')?></strong></div></div>
      <div class="col-md-6"><div class="detail-box"><span>Aadhaar</span><strong><?=localE($selected['aadhaar_number'] ?: '—')?></strong></div></div>
      <div class="col-md-6"><div class="detail-box"><span>Status</span><strong><span class="status <?=localE($selected['account_status'])?>"><?=localE(localStatusLabel($selected['account_status']))?></span></strong></div></div>
      <div class="col-md-6"><div class="detail-box"><span>Registered</span><strong><?=localE(localDateTime($selected['created_at']))?></strong></div></div>

      <?php if($localRole==='contractor'): ?>
        <div class="col-md-6"><div class="detail-box"><span>Business Name</span><strong><?=localE($selectedProfile['business_name'] ?? '—')?></strong></div></div>
        <div class="col-md-6"><div class="detail-box"><span>Business Type</span><strong><?=localE($selectedProfile['business_type'] ?? '—')?></strong></div></div>
        <div class="col-md-6"><div class="detail-box"><span>City</span><strong><?=localE($selectedProfile['city'] ?? '—')?></strong></div></div>
        <div class="col-md-6"><div class="detail-box"><span>GSTIN</span><strong><?=localE($selectedProfile['gstin'] ?? '—')?></strong></div></div>
        <div class="col-12"><div class="detail-box"><span>Address</span><strong><?=localE($selectedProfile['full_address'] ?? '—')?></strong></div></div>
      <?php else: ?>
        <div class="col-md-4"><div class="detail-box"><span>Date of Birth</span><strong><?=localE($selectedProfile['date_of_birth'] ?? '—')?></strong></div></div>
        <div class="col-md-4"><div class="detail-box"><span>Gender</span><strong><?=localE($selectedProfile['gender'] ?? '—')?></strong></div></div>
        <div class="col-md-4"><div class="detail-box"><span>Experience</span><strong><?=localE(($selectedProfile['experience_years'] ?? '0').' years')?></strong></div></div>
        <div class="col-md-6"><div class="detail-box"><span>City</span><strong><?=localE($selectedProfile['city'] ?? '—')?></strong></div></div>
        <div class="col-md-6"><div class="detail-box"><span>Skills</span><strong><?=localE($selectedProfile['skills'] ?? '—')?></strong></div></div>
        <div class="col-12"><div class="detail-box"><span>Address</span><strong><?=localE($selectedProfile['full_address'] ?? '—')?></strong></div></div>
      <?php endif; ?>
    </div></div>
  </section>
  <?php endif; ?>

  <?php if($selected && $editId>0): ?>
  <section class="panel mb-3">
    <div class="panel-head"><div><h2>Edit Account #<?=$selected['id']?></h2><p class="muted">Update profile data stored in the Local database.</p></div><a class="btn-outline-local" href="?view=<?=$selected['id']?>">Cancel</a></div>
    <form method="post" class="p-3">
      <input type="hidden" name="csrf" value="<?=localE($csrf)?>"><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?=$selected['id']?>">
      <div class="row g-3">
        <div class="col-md-6"><label class="form-label">Name *</label><input class="form-control" name="name" required value="<?=localE($selected['name'])?>"></div>
        <div class="col-md-6"><label class="form-label">Mobile *</label><input class="form-control" name="mobile" maxlength="10" inputmode="numeric" required value="<?=localE($selected['mobile'])?>"></div>
        <div class="col-md-6"><label class="form-label">Email</label><input class="form-control" type="email" name="email" value="<?=localE($selected['email'])?>"></div>
        <div class="col-md-6"><label class="form-label">Aadhaar</label><input class="form-control" name="aadhaar_number" maxlength="12" inputmode="numeric" value="<?=localE($selected['aadhaar_number'])?>"></div>

        <?php if($localRole==='contractor'): ?>
          <div class="col-md-6"><label class="form-label">Business Name</label><input class="form-control" name="business_name" value="<?=localE($selectedProfile['business_name'] ?? '')?>"></div>
          <div class="col-md-6"><label class="form-label">Business Type</label><input class="form-control" name="business_type" value="<?=localE($selectedProfile['business_type'] ?? '')?>"></div>
          <div class="col-md-4"><label class="form-label">City</label><input class="form-control" name="city" value="<?=localE($selectedProfile['city'] ?? '')?>"></div>
          <div class="col-md-4"><label class="form-label">State</label><input class="form-control" name="state" value="<?=localE($selectedProfile['state'] ?? '')?>"></div>
          <div class="col-md-4"><label class="form-label">Postcode</label><input class="form-control" name="postcode" value="<?=localE($selectedProfile['postcode'] ?? '')?>"></div>
          <div class="col-md-6"><label class="form-label">GSTIN</label><input class="form-control" name="gstin" value="<?=localE($selectedProfile['gstin'] ?? '')?>"></div>
          <div class="col-12"><label class="form-label">Full Address</label><textarea class="form-control" name="full_address" rows="3"><?=localE($selectedProfile['full_address'] ?? '')?></textarea></div>
        <?php else: ?>
          <div class="col-md-4"><label class="form-label">Date of Birth</label><input class="form-control" type="date" name="date_of_birth" value="<?=localE($selectedProfile['date_of_birth'] ?? '')?>"></div>
          <div class="col-md-4"><label class="form-label">Gender</label><select class="form-select" name="gender"><option value="">Select gender</option><?php foreach(['Female','Male','Other'] as $g): ?><option value="<?=$g?>" <?=($selectedProfile['gender'] ?? '')===$g?'selected':''?>><?=$g?></option><?php endforeach; ?></select></div>
          <div class="col-md-4"><label class="form-label">Experience (Years)</label><input class="form-control" type="number" step="0.1" min="0" max="60" name="experience_years" value="<?=localE((string)($selectedProfile['experience_years'] ?? 0))?>"></div>
          <div class="col-md-6"><label class="form-label">City</label><input class="form-control" name="city" value="<?=localE($selectedProfile['city'] ?? '')?>"></div>
          <div class="col-md-6"><label class="form-label">Skills</label><input class="form-control" name="skills" value="<?=localE($selectedProfile['skills'] ?? '')?>"></div>
          <div class="col-12"><label class="form-label">Full Address</label><textarea class="form-control" name="full_address" rows="3"><?=localE($selectedProfile['full_address'] ?? '')?></textarea></div>
        <?php endif; ?>
      </div>
      <div class="mt-3 d-flex gap-2"><button class="btn-local" type="submit">Save Changes</button><a class="btn-outline-local" href="?view=<?=$selected['id']?>">Cancel</a></div>
    </form>
  </section>
  <?php endif; ?>

  <section class="panel">
    <div class="panel-head"><div><h2><?=localE($label)?> Registrations</h2><p class="muted">Only Local <?=$localRole?> accounts are shown.</p></div></div>
    <form class="toolbar" method="get"><div class="row g-2 align-items-end"><div class="col-xl-6 col-md-6"><label class="form-label">Search</label><input class="form-control" name="search" value="<?=localE($search)?>" placeholder="Name, mobile, email or Aadhaar"></div><div class="col-xl-3 col-md-4"><label class="form-label">Status</label><select class="form-select" name="status"><option value="all">All statuses</option><?php foreach($allowedStatuses as $s): ?><option value="<?=localE($s)?>" <?=$status===$s?'selected':''?>><?=localE(localStatusLabel($s))?></option><?php endforeach; ?></select></div><div class="col-xl-3 col-md-2 d-flex gap-2"><button class="btn-local flex-fill" type="submit"><i class="bi bi-funnel"></i>&nbsp; Apply</button><a class="btn-outline-local" href="<?=localE(basename($_SERVER['PHP_SELF']))?>">Reset</a></div></div></form>
    <div class="table-wrap"><table class="table table-hover align-middle"><thead><tr><th>Account</th><th>Mobile</th><th>Aadhaar</th><th>Status</th><th>Registered</th><th>Manage</th></tr></thead><tbody>
      <?php if(!$accounts): ?><tr><td colspan="6" class="text-center muted py-5">No matching Local accounts.</td></tr><?php else: foreach($accounts as $row): ?>
      <tr>
        <td><strong><?=localE($row['name'])?></strong><div class="muted" style="font-size:10px">#<?=localE($row['id'])?></div></td>
        <td><?=localE($row['mobile'])?><div class="muted" style="font-size:10px"><?=localE($row['email'] ?: 'No email')?></div></td>
        <td><?=localE(localMaskAadhaar($row['aadhaar_number']))?></td>
        <td><span class="status <?=localE($row['account_status'])?>"><?=localE(localStatusLabel($row['account_status']))?></span></td>
        <td><?=localE(localDateTime($row['created_at']))?></td>
        <td>
          <div class="d-flex gap-1 flex-wrap">
            <a class="btn-outline-local" href="?view=<?=$row['id']?>"><i class="bi bi-eye"></i> View</a>
            <a class="btn-outline-local" href="?edit=<?=$row['id']?>"><i class="bi bi-pencil"></i> Edit</a>
            <form method="post" class="d-inline-flex gap-1 align-items-center" onsubmit="return confirm('Apply this account status?')">
              <input type="hidden" name="csrf" value="<?=localE($csrf)?>"><input type="hidden" name="action" value="status"><input type="hidden" name="id" value="<?=$row['id']?>">
              <select class="form-select form-select-sm" name="account_status" style="width:150px"><option value="">Status…</option><?php foreach($allowedStatuses as $s): ?><option value="<?=localE($s)?>" <?=$row['account_status']===$s?'selected':''?>><?=localE(localStatusLabel($s))?></option><?php endforeach; ?></select>
              <input class="form-control form-control-sm" name="reason" style="width:160px" placeholder="Reason (optional)">
              <button class="btn-outline-local" type="submit">Set</button>
            </form>
            <form method="post" class="d-inline" onsubmit="return confirm('Move this account to deleted records?')"><input type="hidden" name="csrf" value="<?=localE($csrf)?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?=$row['id']?>"><button class="btn-outline-local" type="submit"><i class="bi bi-trash3"></i></button></form>
          </div>
        </td>
      </tr>
      <?php endforeach; endif; ?>
    </tbody></table></div>
  </section>
</main>
<style>
.detail-box{height:100%;padding:13px 14px;border:1px solid #e7edf5;border-radius:12px;background:#f9fbff}.detail-box span{display:block;color:#7d8aa0;font-size:10px;text-transform:uppercase;letter-spacing:.06em;font-weight:800;margin-bottom:5px}.detail-box strong{display:block;font-size:13px;overflow-wrap:anywhere}
</style>
<?php require __DIR__ . '/footer.php'; ?>
