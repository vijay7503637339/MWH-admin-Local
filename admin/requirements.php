<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
requireAdmin();
require_once __DIR__ . '/../api/config/database.php';

if (empty($_SESSION['local_req_csrf'])) $_SESSION['local_req_csrf']=bin2hex(random_bytes(32));
$csrf=$_SESSION['local_req_csrf'];
$flash=$_SESSION['local_req_flash'] ?? '';
$error=$_SESSION['local_req_error'] ?? '';
unset($_SESSION['local_req_flash'],$_SESSION['local_req_error']);

function reqE(mixed $v): string { return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8'); }
$allowed=['open','active','completed','cancelled','closed'];

if($_SERVER['REQUEST_METHOD']==='POST'){
  if(!hash_equals($csrf,(string)($_POST['csrf']??''))){$_SESSION['local_req_error']='Invalid request token.';header('Location: requirements.php');exit;}
  $id=(int)($_POST['id']??0);$status=(string)($_POST['status']??'');
  try{
    if($id<=0 || !in_array($status,$allowed,true)) throw new RuntimeException('Invalid requirement update.');
    $stmt=$pdo->prepare('UPDATE local_requirements SET status=?,updated_at=UTC_TIMESTAMP() WHERE id=? AND deleted_at IS NULL');
    $stmt->execute([$status,$id]);
    $_SESSION['local_req_flash']=$stmt->rowCount()?'Requirement status updated.':'No changes were needed.';
  }catch(Throwable $e){$_SESSION['local_req_error']=$e->getMessage();}
  header('Location: requirements.php');exit;
}

$search=trim((string)($_GET['search']??''));
$status=(string)($_GET['status']??'all');
$from=trim((string)($_GET['from']??''));
$to=trim((string)($_GET['to']??''));

$where=['lr.deleted_at IS NULL'];
$params=[];
if($search!==''){
  $where[]='(lr.title LIKE ? OR lr.description LIKE ? OR lr.work_location LIKE ? OR u.name LIKE ? OR u.mobile LIKE ? OR lcp.business_name LIKE ? OR ljr.name LIKE ? OR lc.name LIKE ?)';
  $term='%'.$search.'%';array_push($params,$term,$term,$term,$term,$term,$term,$term,$term);
}
if(in_array($status,$allowed,true)){$where[]='lr.status=?';$params[]=$status;}
if(preg_match('/^\d{4}-\d{2}-\d{2}$/',$from)){$where[]='lr.shift_date>=?';$params[]=$from;}
if(preg_match('/^\d{4}-\d{2}-\d{2}$/',$to)){$where[]='lr.shift_date<=?';$params[]=$to;}

$sql='SELECT lr.*,u.name AS contractor_name,u.mobile AS contractor_mobile,lcp.business_name,lc.name AS category_name,ljr.name AS job_role_name,
      (SELECT COUNT(*) FROM local_applications la WHERE la.requirement_id=lr.id) AS applicant_count
      FROM local_requirements lr
      INNER JOIN local_users u ON u.id=lr.contractor_user_id AND u.role="contractor" AND u.deleted_at IS NULL
      LEFT JOIN local_contractor_profiles lcp ON lcp.user_id=u.id
      LEFT JOIN local_categories lc ON lc.id=lr.category_id
      LEFT JOIN local_job_roles ljr ON ljr.id=lr.job_role_id
      WHERE '.implode(' AND ',$where).' ORDER BY lr.created_at DESC';
$stmt=$pdo->prepare($sql);$stmt->execute($params);$requirements=$stmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle='Requirements';
require __DIR__ . '/includes/header.php';
?>
<main class="content">
<div class="d-flex justify-content-between align-items-end gap-3 flex-wrap"><div><div class="page-kicker">MWH Local · Operations</div><h1 class="page-title">Requirements</h1><p class="muted mb-0">Review staffing requirements created by Local contractors.</p></div></div>
<?php if($flash):?><div class="alert alert-success mt-3"><?=reqE($flash)?></div><?php endif;?>
<?php if($error):?><div class="alert alert-danger mt-3"><?=reqE($error)?></div><?php endif;?>
<section class="panel mt-3">
<div class="panel-head"><div><h2>Staffing Requirements</h2><p class="muted">Local-only requirements. Hospitality vacancies are not included.</p></div></div>
<form class="toolbar" method="get"><div class="row g-2 align-items-end">
<div class="col-xl-5 col-md-6"><label class="form-label">Search</label><input class="form-control" name="search" value="<?=reqE($search)?>" placeholder="Title, contractor, role, location"></div>
<div class="col-xl-2 col-md-3"><label class="form-label">Status</label><select class="form-select" name="status"><option value="all">All statuses</option><?php foreach($allowed as $s):?><option value="<?=reqE($s)?>" <?=$status===$s?'selected':''?>><?=reqE(ucwords(str_replace('_',' ',$s)))?></option><?php endforeach;?></select></div>
<div class="col-xl-2 col-md-3"><label class="form-label">From</label><input class="form-control" type="date" name="from" value="<?=reqE($from)?>"></div>
<div class="col-xl-2 col-md-3"><label class="form-label">To</label><input class="form-control" type="date" name="to" value="<?=reqE($to)?>"></div>
<div class="col-xl-1 col-md-3 d-flex"><button class="btn-local w-100" type="submit"><i class="bi bi-search"></i></button></div>
</div></form>
<div class="table-wrap"><table class="table table-hover align-middle"><thead><tr><th>Requirement</th><th>Contractor</th><th>Role</th><th>Location</th><th>Shift</th><th>Openings</th><th>Payout</th><th>Applicants</th><th>Status</th></tr></thead><tbody>
<?php if(!$requirements):?><tr><td colspan="9" class="text-center muted py-5">No requirements found.</td></tr><?php else:foreach($requirements as $r):?>
<tr>
<td><strong><?=reqE($r['title'])?></strong><div class="muted" style="font-size:10px"><?=reqE(mb_strimwidth((string)($r['description']??''),0,90,'…'))?></div></td>
<td><?=reqE($r['business_name'] ?: $r['contractor_name'])?><div class="muted" style="font-size:10px"><?=reqE($r['contractor_mobile'])?></div></td>
<td><?=reqE($r['job_role_name'] ?: ($r['category_name'] ?: '—'))?></td>
<td><?=reqE($r['work_location'])?></td>
<td><?=reqE($r['shift_date'])?><div class="muted" style="font-size:10px"><?=reqE(trim(($r['shift_start']??'').' - '.($r['shift_end']??''),' -'))?></div></td>
<td><?=number_format((int)$r['openings_count'])?></td>
<td>₹<?=number_format((float)$r['payout_amount'],2)?><div class="muted" style="font-size:10px"><?=reqE($r['payout_period'])?></div></td>
<td><?=number_format((int)$r['applicant_count'])?></td>
<td><form method="post"><input type="hidden" name="csrf" value="<?=reqE($csrf)?>"><input type="hidden" name="id" value="<?=$r['id']?>"><select class="form-select form-select-sm" name="status" onchange="this.form.submit()" style="min-width:125px"><?php foreach($allowed as $s):?><option value="<?=reqE($s)?>" <?=$r['status']===$s?'selected':''?>><?=reqE(ucwords(str_replace('_',' ',$s)))?></option><?php endforeach;?></select></form></td>
</tr>
<?php endforeach;endif;?>
</tbody></table></div>
</section></main>
<?php require __DIR__ . '/includes/footer.php'; ?>
