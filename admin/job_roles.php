<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';requireAdmin();require_once __DIR__.'/../api/config/database.php';
if(empty($_SESSION['local_role_csrf']))$_SESSION['local_role_csrf']=bin2hex(random_bytes(32));$csrf=$_SESSION['local_role_csrf'];$error='';$flash='';
function re(mixed $v):string{return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
if($_SERVER['REQUEST_METHOD']==='POST'){
 if(!hash_equals($csrf,(string)($_POST['csrf']??''))){$error='Invalid request token.';}else{
  try{$action=(string)$_POST['action'];$id=(int)($_POST['id']??0);
   if($action==='save'){ $category=(int)$_POST['category_id'];$name=trim((string)$_POST['name']);$desc=trim((string)$_POST['description']);if($category<=0||$name==='')throw new RuntimeException('Category and role name are required.');$stmt=$pdo->prepare('INSERT INTO local_job_roles(category_id,name,description,sort_order,is_active) VALUES(?,?,?,?,1)');$stmt->execute([$category,$name,$desc!==''?$desc:null,(int)$_POST['sort_order']]);$flash='Job role added.';}
   elseif($action==='delete'&&$id>0){$pdo->prepare('UPDATE local_job_roles SET deleted_at=UTC_TIMESTAMP(),is_active=0 WHERE id=?')->execute([$id]);$flash='Job role archived.';}
   else throw new RuntimeException('Invalid role action.');
  }catch(Throwable $e){$error=$e->getMessage();}
 }
}
$categories=$pdo->query('SELECT id,name FROM local_categories WHERE deleted_at IS NULL AND is_active=1 ORDER BY sort_order,name')->fetchAll(PDO::FETCH_ASSOC);
$roles=$pdo->query('SELECT jr.id,jr.name,jr.description,jr.sort_order,jr.is_active,lc.name AS category_name FROM local_job_roles jr INNER JOIN local_categories lc ON lc.id=jr.category_id WHERE jr.deleted_at IS NULL ORDER BY lc.sort_order,lc.name,jr.sort_order,jr.name')->fetchAll(PDO::FETCH_ASSOC);
$pageTitle='Job Roles';require __DIR__.'/includes/header.php';
?>
<main class="content"><div><div class="page-kicker">MWH Local · Configuration</div><h1 class="page-title">Job Roles</h1><p class="muted">Local-specific roles for staff matching and contractor requirements.</p></div>
<?php if($flash):?><div class="alert alert-success mt-3"><?=re($flash)?></div><?php endif;?><?php if($error):?><div class="alert alert-danger mt-3"><?=re($error)?></div><?php endif;?>
<div class="row g-3 mt-1"><div class="col-xl-4"><section class="panel p-3"><h2 style="font-size:16px">Add Job Role</h2><form method="post"><input type="hidden" name="csrf" value="<?=re($csrf)?>"><input type="hidden" name="action" value="save"><div class="mb-2"><label class="form-label">Category *</label><select class="form-select" name="category_id" required><option value="">Select category</option><?php foreach($categories as $c):?><option value="<?=$c['id']?>"><?=re($c['name'])?></option><?php endforeach;?></select></div><div class="mb-2"><label class="form-label">Role Name *</label><input class="form-control" name="name" required></div><div class="mb-2"><label class="form-label">Description</label><textarea class="form-control" name="description" rows="3"></textarea></div><div class="mb-3"><label class="form-label">Sort Order</label><input class="form-control" type="number" name="sort_order" value="0"></div><button class="btn-local" type="submit">Add Role</button></form></section></div>
<div class="col-xl-8"><section class="panel"><div class="panel-head"><div><h2>Local Job Roles</h2><p class="muted">Roles are isolated from MWH Hospitality.</p></div></div><div class="table-wrap"><table class="table"><thead><tr><th>Category</th><th>Role</th><th>Description</th><th>Status</th><th></th></tr></thead><tbody><?php if(!$roles):?><tr><td colspan="5" class="text-center muted py-5">No job roles yet.</td></tr><?php else:foreach($roles as $r):?><tr><td><?=re($r['category_name'])?></td><td><strong><?=re($r['name'])?></strong></td><td><?=re($r['description']?:'—')?></td><td><?=!empty($r['is_active'])?'Active':'Inactive'?></td><td><form method="post" onsubmit="return confirm('Archive this role?')"><input type="hidden" name="csrf" value="<?=re($csrf)?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?=$r['id']?>"><button class="btn-outline-local" type="submit"><i class="bi bi-archive"></i></button></form></td></tr><?php endforeach;endif;?></tbody></table></div></section></div></div></main>
<?php require __DIR__.'/includes/footer.php'; ?>
