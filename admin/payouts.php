<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
requireAdmin();
require_once __DIR__ . '/../api/config/database.php';
require_once __DIR__ . '/../api/config/fcm.php';
$admin=currentAdmin();
if(!in_array((string)$admin['role'],['super_admin','finance_admin'],true)){http_response_code(403);exit('Finance admin access required.');}
if(empty($_SESSION['local_payout_csrf']))$_SESSION['local_payout_csrf']=bin2hex(random_bytes(32));
$csrf=$_SESSION['local_payout_csrf'];$flash='';$error='';
function payoutE(mixed $v):string{return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
if(($_SERVER['REQUEST_METHOD']??'')==='POST'){
 if(!hash_equals($csrf,(string)($_POST['csrf']??''))){$error='Invalid request token.';}else{
  try{
   $id=(int)($_POST['payout_id']??0);$method=(string)($_POST['method']??'upi');$ref=trim((string)($_POST['reference']??''));
   if($id<=0)throw new RuntimeException('Invalid payout.');
   if(!in_array($method,['upi','bank_transfer','cash'],true))throw new RuntimeException('Invalid payment method.');
   $pdo->beginTransaction();
   $q=$pdo->prepare('SELECT sp.id,sp.status,sp.amount,sp.staff_user_id,r.title FROM local_staff_payouts sp INNER JOIN local_assignments a ON a.id=sp.assignment_id INNER JOIN local_requirements r ON r.id=a.requirement_id WHERE sp.id=? LIMIT 1');
   $q->execute([$id]);$row=$q->fetch(PDO::FETCH_ASSOC);
   if(!$row)throw new RuntimeException('Payout not found.');
   if((string)$row['status']!=='pending')throw new RuntimeException('Payout already processed.');
   $u=$pdo->prepare('UPDATE local_staff_payouts SET status="paid",paid_at=UTC_TIMESTAMP(),payment_method=?,transaction_reference=?,entered_by_admin_id=?,updated_at=UTC_TIMESTAMP() WHERE id=?');
   $u->execute([$method,$ref!==''?$ref:null,$admin['id'],$id]);
   $n=$pdo->prepare('INSERT INTO local_notifications(user_id,admin_user_id,type,title,message,data_json) VALUES(?,?,?,?,?,?)');
   $n->execute([(int)$row['staff_user_id'],$admin['id'],'payout_paid','Payout paid','Your payout has been marked paid for "'.(string)$row['title'].'".',json_encode(['payout_id'=>$id],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)]);
   $pdo->commit();
   try{
     $tokens=localFcmTokensForUsers($pdo,[(int)$row['staff_user_id']]);
     if($tokens){
       localFcmSendTokens(
         $pdo,
         $tokens,
         'Payout paid',
         'Your payout has been marked paid for "' . (string)$row['title'] . '".',
         ['screen'=>'earnings','payout_id'=>$id]
       );
     }
   }catch(Throwable $pushError){
     error_log('MWH Local payout push: '.$pushError->getMessage());
   }
   $flash='Payout marked paid.';
  }catch(Throwable $ex){if($pdo->inTransaction())$pdo->rollBack();$error=$ex->getMessage();}
 }
}
$rows=$pdo->query('SELECT sp.id,sp.amount,sp.status,sp.payment_method,sp.transaction_reference,sp.created_at,sp.paid_at,r.title,r.shift_date,r.work_location,s.name AS staff_name FROM local_staff_payouts sp INNER JOIN local_assignments a ON a.id=sp.assignment_id INNER JOIN local_requirements r ON r.id=a.requirement_id INNER JOIN local_users s ON s.id=sp.staff_user_id ORDER BY CASE sp.status WHEN "pending" THEN 0 WHEN "paid" THEN 1 ELSE 2 END,sp.created_at DESC')->fetchAll(PDO::FETCH_ASSOC);
$pageTitle='Staff Payouts';
require __DIR__.'/includes/header.php';
require __DIR__.'/includes/sidebar.php';
?>
<main class="content">
<div class="d-flex justify-content-between align-items-end gap-3 flex-wrap"><div><div class="page-kicker">MWH Local · Finance</div><h1 class="page-title">Staff Payouts</h1><p class="muted mb-0">Process pending staff payouts after completed duties.</p></div></div>
<?php if($flash):?><div class="alert alert-success mt-3"><?=payoutE($flash)?></div><?php endif;?>
<?php if($error):?><div class="alert alert-danger mt-3"><?=payoutE($error)?></div><?php endif;?>
<section class="panel mt-3"><div class="panel-head"><div><h2>Payout queue</h2><p class="muted">Record the actual payout method and reference when staff is paid.</p></div></div>
<div class="table-wrap"><table class="table table-hover align-middle"><thead><tr><th>Staff</th><th>Duty</th><th>Amount</th><th>Status</th><th>Action</th></tr></thead><tbody>
<?php if(!$rows):?><tr><td colspan="5" class="text-center muted py-5">No payout records yet.</td></tr><?php else:foreach($rows as $row):?>
<tr><td><strong><?=payoutE($row['staff_name'])?></strong></td><td><?=payoutE($row['title'])?><div class="muted" style="font-size:10px"><?=payoutE($row['shift_date'])?> · <?=payoutE($row['work_location'])?></div></td><td>₹<?=number_format((float)$row['amount'],2)?></td><td><span class="status <?=payoutE($row['status'])?>"><?=payoutE(ucwords($row['status']))?></span></td><td>
<?php if($row['status']==='pending'):?><form method="post" class="d-flex gap-1"><input type="hidden" name="csrf" value="<?=payoutE($csrf)?>"><input type="hidden" name="payout_id" value="<?=$row['id']?>"><select class="form-select form-select-sm" name="method" style="width:115px"><option value="upi">UPI</option><option value="bank_transfer">Bank</option><option value="cash">Cash</option></select><input class="form-control form-control-sm" name="reference" placeholder="UTR / ref" style="width:150px"><button class="btn-local" type="submit" onclick="return confirm('Mark this payout as paid?')">Mark Paid</button></form><?php else:?><?=payoutE($row['payment_method']?:'Processed')?> · <?=payoutE($row['transaction_reference']?:'—')?><?php endif;?>
</td></tr>
<?php endforeach;endif;?>
</tbody></table></div></section></main>
<?php require __DIR__.'/includes/footer.php'; ?>