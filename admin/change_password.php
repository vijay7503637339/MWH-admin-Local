<?php
declare(strict_types=1);
require_once __DIR__.'/includes/auth.php';requireAdmin();require_once __DIR__.'/../api/config/database.php';
$admin=currentAdmin();$error='';$flash='';
if($_SERVER['REQUEST_METHOD']==='POST'){
 $current=(string)($_POST['current_password']??'');$new=(string)($_POST['new_password']??'');$confirm=(string)($_POST['confirm_password']??'');
 try{
  if(strlen($new)<8)throw new RuntimeException('New password must be at least 8 characters.');
  if($new!==$confirm)throw new RuntimeException('New passwords do not match.');
  $stmt=$pdo->prepare('SELECT password_hash FROM local_admin_users WHERE id=? AND deleted_at IS NULL LIMIT 1');$stmt->execute([$admin['id']]);$hash=(string)$stmt->fetchColumn();
  if(!$hash||!password_verify($current,$hash))throw new RuntimeException('Current password is incorrect.');
  $pdo->prepare('UPDATE local_admin_users SET password_hash=?,updated_at=UTC_TIMESTAMP() WHERE id=?')->execute([password_hash($new,PASSWORD_DEFAULT),$admin['id']]);
  $flash='Password changed successfully.';
 }catch(Throwable $e){$error=$e->getMessage();}
}
$pageTitle='Change Password';require __DIR__.'/includes/header.php';
?>
<main class="content"><div><div class="page-kicker">MWH Local · Account</div><h1 class="page-title">Change Password</h1><p class="muted">Update your standalone Local admin password.</p></div>
<?php if($flash):?><div class="alert alert-success mt-3"><?=htmlspecialchars($flash,ENT_QUOTES,'UTF-8')?></div><?php endif;?><?php if($error):?><div class="alert alert-danger mt-3"><?=htmlspecialchars($error,ENT_QUOTES,'UTF-8')?></div><?php endif;?>
<section class="panel mt-3 p-4" style="max-width:620px"><form method="post" autocomplete="off"><div class="mb-3"><label class="form-label">Current Password</label><input class="form-control" type="password" name="current_password" required></div><div class="mb-3"><label class="form-label">New Password</label><input class="form-control" type="password" name="new_password" minlength="8" required></div><div class="mb-4"><label class="form-label">Confirm New Password</label><input class="form-control" type="password" name="confirm_password" minlength="8" required></div><button class="btn-local" type="submit"><i class="bi bi-shield-lock"></i>&nbsp; Change Password</button></form></section></main>
<?php require __DIR__.'/includes/footer.php'; ?>
