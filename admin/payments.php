<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
requireAdmin();
require_once __DIR__ . '/../api/config/database.php';

$admin=currentAdmin();
if(!in_array((string)$admin['role'],['super_admin','finance_admin'],true)){
    http_response_code(403);
    exit('Finance admin access required.');
}

if(empty($_SESSION['local_payment_csrf'])) $_SESSION['local_payment_csrf']=bin2hex(random_bytes(32));
$csrf=$_SESSION['local_payment_csrf'];
$error='';$flash='';

function pe(mixed $v): string { return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8'); }

if(($_SERVER['REQUEST_METHOD']??'')==='POST'){
    if(!hash_equals($csrf,(string)($_POST['csrf']??''))){
        $error='Invalid request token. Please reload.';
    }else{
        $action=(string)($_POST['action']??'');
        try{
            if($action==='payment_status'){
                $paymentId=(int)($_POST['payment_id']??0);
                $newStatus=(string)($_POST['status']??'');
                if($paymentId<=0||!in_array($newStatus,['paid','failed'],true)) throw new RuntimeException('Invalid payment action.');

                $pdo->beginTransaction();
                $find=$pdo->prepare(
                    'SELECT p.id,p.status,p.amount,p.assignment_id,p.staff_user_id,p.contractor_user_id,
                            a.status AS assignment_status,r.title,s.name AS staff_name,c.name AS contractor_name
                     FROM local_payments p
                     INNER JOIN local_assignments a ON a.id=p.assignment_id
                     INNER JOIN local_requirements r ON r.id=a.requirement_id
                     INNER JOIN local_users s ON s.id=p.staff_user_id
                     INNER JOIN local_users c ON c.id=p.contractor_user_id
                     WHERE p.id=? LIMIT 1'
                );
                $find->execute([$paymentId]);
                $payment=$find->fetch(PDO::FETCH_ASSOC);
                if(!$payment) throw new RuntimeException('Payment not found.');
                if((string)$payment['status']!=='pending') throw new RuntimeException('This payment has already been processed.');

                $stmt=$pdo->prepare('UPDATE local_payments SET status=?,entered_by_admin_id=?,updated_at=UTC_TIMESTAMP() WHERE id=?');
                $stmt->execute([$newStatus,$admin['id'],$paymentId]);

                if($newStatus==='paid'){
                    $title='Payment approved';
                    $message='Payment of ₹'.number_format((float)$payment['amount'],2).' for "'.(string)$payment['title'].'" has been approved.';
                    $type='payment_approved';
                    $statusText='paid';
                }else{
                    $title='Payment rejected';
                    $message='Payment of ₹'.number_format((float)$payment['amount'],2).' for "'.(string)$payment['title'].'" was not approved.';
                    $type='payment_rejected';
                    $statusText='failed';
                }

                $notice=$pdo->prepare('INSERT INTO local_notifications(user_id,admin_user_id,type,title,message,data_json) VALUES(?,?,?,?,?,?)');
                $notice->execute([
                    (int)$payment['staff_user_id'],$admin['id'],$type,$title,$message,
                    json_encode(['payment_id'=>$paymentId,'assignment_id'=>(int)$payment['assignment_id'],'status'=>$statusText],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)
                ]);

                $log=$pdo->prepare('INSERT INTO local_audit_logs(actor_type,actor_id,action,entity_type,entity_id,new_values,ip_address,user_agent) VALUES(?,?,?,?,?,?,?,?,?)');
                $log->execute(['local_admin',$admin['id'],'payment.status_changed','local_payment',$paymentId,json_encode(['status'=>$newStatus],JSON_UNESCAPED_UNICODE),$_SERVER['REMOTE_ADDR']??null,$_SERVER['HTTP_USER_AGENT']??null]);

                $pdo->commit();
                $flash=$newStatus==='paid'?'Payment approved successfully.':'Payment rejected.';
            }elseif($action==='settings'){
                $companyName=trim((string)($_POST['company_name']??''));
                $upiId=trim((string)($_POST['upi_id']??''));
                $accountNumber=trim((string)($_POST['account_number']??''));
                $accountName=trim((string)($_POST['account_name']??''));
                $ifsc=strtoupper(trim((string)($_POST['ifsc_code']??'')));
                if($companyName==='') throw new RuntimeException('Company name is required.');
                if($upiId!=='' && mb_strlen($upiId)>190) throw new RuntimeException('UPI ID is too long.');
                if($accountNumber!=='' && mb_strlen($accountNumber)>40) throw new RuntimeException('Account number is too long.');
                if($ifsc!=='' && !preg_match('/^[A-Z]{4}0[A-Z0-9]{6}$/',$ifsc)) throw new RuntimeException('Invalid IFSC code.');

                $qrPath=null;
                if(isset($_FILES['qr_code']) && (int)$_FILES['qr_code']['error']!==UPLOAD_ERR_NO_FILE){
                    if((int)$_FILES['qr_code']['error']!==UPLOAD_ERR_OK) throw new RuntimeException('QR upload failed.');
                    if((int)$_FILES['qr_code']['size']>5*1024*1024) throw new RuntimeException('QR image must be 5 MB or smaller.');
                    $tmp=(string)$_FILES['qr_code']['tmp_name'];
                    $mime=(new finfo(FILEINFO_MIME_TYPE))->file($tmp);
                    $allowed=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
                    if(!isset($allowed[$mime])) throw new RuntimeException('QR image must be JPG, PNG or WEBP.');
                    $dir=dirname(__DIR__).'/uploads/payment';
                    if(!is_dir($dir)&&!mkdir($dir,0755,true)&&!is_dir($dir)) throw new RuntimeException('Unable to create upload directory.');
                    $file='company_qr_'.bin2hex(random_bytes(12)).'.'.$allowed[$mime];
                    if(!move_uploaded_file($tmp,$dir.'/'.$file)) throw new RuntimeException('Unable to save QR image.');
                    $qrPath='uploads/payment/'.$file;
                }

                $existing=$pdo->query('SELECT id,qr_code_path FROM local_payment_settings ORDER BY id DESC LIMIT 1')->fetch(PDO::FETCH_ASSOC);
                if($existing){
                    $stmt=$pdo->prepare('UPDATE local_payment_settings SET company_name=?,upi_id=?,account_number=?,account_name=?,ifsc_code=?,qr_code_path=COALESCE(?,qr_code_path),is_active=1,updated_by_admin_id=?,updated_at=UTC_TIMESTAMP() WHERE id=?');
                    $stmt->execute([$companyName,$upiId?:null,$accountNumber?:null,$accountName?:null,$ifsc?:null,$qrPath,$admin['id'],(int)$existing['id']]);
                }else{
                    $stmt=$pdo->prepare('INSERT INTO local_payment_settings(company_name,upi_id,account_number,account_name,ifsc_code,qr_code_path,is_active,updated_by_admin_id) VALUES(?,?,?,?,?,?,1,?)');
                    $stmt->execute([$companyName,$upiId?:null,$accountNumber?:null,$accountName?:null,$ifsc?:null,$qrPath,$admin['id']]);
                }
                $flash='Company payment settings saved.';
            }else{
                throw new RuntimeException('Unknown payment action.');
            }
        }catch(Throwable $e){
            if($pdo->inTransaction()) $pdo->rollBack();
            $error=$e->getMessage();
        }
    }
}

$payments=$pdo->query(
    'SELECT p.id,p.amount,p.payment_date,p.payment_method,p.transaction_reference,p.status,p.created_at,
            r.title,r.work_location,r.shift_date,
            s.name AS staff_name,c.name AS contractor_name
     FROM local_payments p
     INNER JOIN local_assignments a ON a.id=p.assignment_id
     INNER JOIN local_requirements r ON r.id=a.requirement_id
     INNER JOIN local_users s ON s.id=p.staff_user_id
     INNER JOIN local_users c ON c.id=p.contractor_user_id
     ORDER BY CASE p.status WHEN "pending" THEN 0 WHEN "paid" THEN 1 ELSE 2 END,p.created_at DESC'
)->fetchAll(PDO::FETCH_ASSOC);

$settings=$pdo->query('SELECT * FROM local_payment_settings ORDER BY id DESC LIMIT 1')->fetch(PDO::FETCH_ASSOC) ?: [
    'company_name'=>'Maan World Local','upi_id'=>'','account_number'=>'','account_name'=>'','ifsc_code'=>'','qr_code_path'=>''
];

$pageTitle='Payments';
require __DIR__.'/includes/header.php';
require __DIR__.'/includes/sidebar.php';
?>
<main class="content">
  <div class="d-flex justify-content-between align-items-end gap-3 flex-wrap">
    <div><div class="page-kicker">MWH Local · Finance</div><h1 class="page-title">Payments</h1><p class="muted mb-0">Approve contractor payments and maintain company payment details.</p></div>
  </div>
  <?php if($flash):?><div class="alert alert-success mt-3"><?=pe($flash)?></div><?php endif;?>
  <?php if($error):?><div class="alert alert-danger mt-3"><?=pe($error)?></div><?php endif;?>

  <section class="panel mt-3">
    <div class="panel-head"><div><h2>Company payment details</h2><p class="muted">Shown to contractors when they pay Maan World for a selected duty.</p></div></div>
    <form method="post" enctype="multipart/form-data" class="p-3">
      <input type="hidden" name="csrf" value="<?=pe($csrf)?>"><input type="hidden" name="action" value="settings">
      <div class="row g-3">
        <div class="col-md-6"><label class="form-label">Company Name</label><input class="form-control" name="company_name" value="<?=pe($settings['company_name']??'')?>"></div>
        <div class="col-md-6"><label class="form-label">UPI ID</label><input class="form-control" name="upi_id" value="<?=pe($settings['upi_id']??'')?>"></div>
        <div class="col-md-4"><label class="form-label">Account Number</label><input class="form-control" name="account_number" value="<?=pe($settings['account_number']??'')?>"></div>
        <div class="col-md-4"><label class="form-label">Account Name</label><input class="form-control" name="account_name" value="<?=pe($settings['account_name']??'')?>"></div>
        <div class="col-md-4"><label class="form-label">IFSC Code</label><input class="form-control" name="ifsc_code" value="<?=pe($settings['ifsc_code']??'')?>"></div>
        <div class="col-md-8"><label class="form-label">Company QR Code</label><input class="form-control" type="file" name="qr_code" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"></div>
        <div class="col-md-4 d-flex align-items-end"><button class="btn-local w-100" type="submit">Save payment details</button></div>
      </div>
    </form>
  </section>

  <section class="panel mt-3">
    <div class="panel-head"><div><h2>Payment approval queue</h2><p class="muted">Approve only after verifying the contractor payment.</p></div></div>
    <div class="table-wrap"><table class="table table-hover align-middle"><thead><tr><th>Contractor</th><th>Staff</th><th>Requirement</th><th>Amount</th><th>Method</th><th>Reference</th><th>Status</th><th>Action</th></tr></thead><tbody>
    <?php if(!$payments):?><tr><td colspan="8" class="text-center muted py-5">No payment records yet.</td></tr><?php else:foreach($payments as $p):?>
      <tr>
        <td><?=pe($p['contractor_name'])?></td><td><?=pe($p['staff_name'])?></td>
        <td><strong><?=pe($p['title'])?></strong><div class="muted" style="font-size:10px"><?=pe($p['shift_date'])?> · <?=pe($p['work_location'])?></div></td>
        <td>₹<?=number_format((float)$p['amount'],2)?></td><td><?=pe(ucwords(str_replace('_',' ',$p['payment_method'])))?></td>
        <td><?=pe($p['transaction_reference']?:'—')?></td>
        <td><span class="status <?=pe($p['status'])?>"><?=pe(ucwords($p['status']))?></span></td>
        <td>
          <?php if($p['status']==='pending'):?>
            <form method="post" class="d-flex gap-1">
              <input type="hidden" name="csrf" value="<?=pe($csrf)?>"><input type="hidden" name="action" value="payment_status"><input type="hidden" name="payment_id" value="<?=$p['id']?>">
              <button class="btn-local" name="status" value="paid" onclick="return confirm('Approve this payment?')">Approve</button>
              <button class="btn-outline-local" name="status" value="failed" onclick="return confirm('Reject this payment?')">Reject</button>
            </form>
          <?php else: ?><span class="muted">Processed</span><?php endif;?>
        </td>
      </tr>
    <?php endforeach;endif;?>
    </tbody></table></div>
  </section>
</main>
<?php require __DIR__.'/includes/footer.php'; ?>
