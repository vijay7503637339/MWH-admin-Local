<?php
declare(strict_types=1);
require_once __DIR__ . '/../auth/helpers.php';
require_once __DIR__ . '/../config/pricing.php';
require_once __DIR__ . '/../config/fcm.php';
if(($_SERVER['REQUEST_METHOD']??'')!=='POST')localJson(['success'=>false,'message'=>'POST request required'],405);
try{
  $user=localRequireUser($pdo);if((string)$user['role']!=='contractor')localJson(['success'=>false,'message'=>'Contractor access is required'],403);
  $d=localInput();$requirementId=(int)($d['requirement_id']??0);$ids=$d['application_ids']??[];
  if($requirementId<=0||!is_array($ids))localJson(['success'=>false,'message'=>'Valid requirement_id and application_ids are required'],422);
  $ids=array_values(array_unique(array_filter(array_map(static fn($x)=>(int)$x,$ids),static fn($x)=>$x>0)));if(!$ids)localJson(['success'=>false,'message'=>'Select at least one applicant'],422);
  $pdo->beginTransaction();
  $rq=$pdo->prepare('SELECT id,title,openings_count,shift_date,shift_start,shift_end,work_location,payout_amount,status,job_role_id,category_id FROM local_requirements WHERE id=? AND contractor_user_id=? AND deleted_at IS NULL LIMIT 1');$rq->execute([$requirementId,(int)$user['id']]);$req=$rq->fetch(PDO::FETCH_ASSOC);
  if(!$req)throw new InvalidArgumentException('Requirement not found.');
  if(!in_array((string)$req['status'],['open','active'],true))throw new InvalidArgumentException('Requirement is not available for staff selection.');
  $grossPerStaff=(int)$req['openings_count']>0 ? (float)$req['payout_amount']/(int)$req['openings_count'] : (float)$req['payout_amount'];
  if($grossPerStaff<=0)throw new InvalidArgumentException('Requirement payout is not configured.');
  $staffPayout=localStaffNetAmount($grossPerStaff);

  $alreadyQ=$pdo->prepare('SELECT COUNT(*) FROM local_applications WHERE requirement_id=? AND status="selected"');$alreadyQ->execute([$requirementId]);$already=(int)$alreadyQ->fetchColumn();
  $max=(int)$req['openings_count']- $already;if($max<=0)throw new InvalidArgumentException('All openings are already filled.');
  if(count($ids)>$max)throw new InvalidArgumentException('You can select up to '.$max.' more staff.');
  $ph=implode(',',array_fill(0,count($ids),'?'));$q=$pdo->prepare('SELECT la.id,la.staff_user_id,la.status,u.name,u.account_status FROM local_applications la INNER JOIN local_users u ON u.id=la.staff_user_id WHERE la.requirement_id=? AND la.id IN ('.$ph.') AND la.status IN ("applied","shortlisted") FOR UPDATE');$q->execute(array_merge([$requirementId],$ids));$rows=$q->fetchAll(PDO::FETCH_ASSOC);
  if(count($rows)!==count($ids))throw new InvalidArgumentException('One or more selected applications are invalid.');
  $upd=$pdo->prepare('UPDATE local_applications SET status="selected",updated_at=UTC_TIMESTAMP() WHERE id=? AND requirement_id=?');
  $assignmentQ=$pdo->prepare('SELECT id FROM local_assignments WHERE application_id=? LIMIT 1');
  $insert=$pdo->prepare('INSERT INTO local_assignments(requirement_id,application_id,staff_user_id,contractor_user_id,status,start_at,payout_amount,staff_payout_amount,notes) VALUES(?,?,?,?,"assigned",?,?,?,NULL)');
  $notify=$pdo->prepare('INSERT INTO local_notifications(user_id,type,title,message,data_json) VALUES(?,?,?,?,?)');
  $pushStaffUserIds=[];
  foreach($rows as $row){
    if((string)$row['account_status']!=='verified')throw new InvalidArgumentException('Selected staff must be verified: '.(string)$row['name']);
    $upd->execute([(int)$row['id'],$requirementId]);
    $assignmentQ->execute([(int)$row['id']]);
    if(!$assignmentQ->fetchColumn()){
      $start=$req['shift_date'].' '.((string)$req['shift_start']!==''?$req['shift_start']:'00:00:00');
      $insert->execute([$requirementId,(int)$row['id'],(int)$row['staff_user_id'],(int)$user['id'],$start,$grossPerStaff,$staffPayout]);
    }
    $timing=trim((string)$req['shift_start'].' - '.(string)$req['shift_end'],' -');
    $message='You have been selected for "'.(string)$req['title'].'". Date: '.(string)$req['shift_date'];if($timing!=='')$message.=' • '.$timing;if((string)$req['work_location']!=='')$message.=' • '.(string)$req['work_location'];
    $notify->execute([(int)$row['staff_user_id'],'selection','You have been selected',$message,json_encode(['requirement_id'=>$requirementId,'application_id'=>(int)$row['id']],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)]);
    $pushStaffUserIds[]=(int)$row['staff_user_id'];
  }
  $reject=$pdo->prepare('UPDATE local_applications SET status="rejected",updated_at=UTC_TIMESTAMP() WHERE requirement_id=? AND id NOT IN ('.$ph.') AND status IN ("applied","shortlisted")');$reject->execute(array_merge([$requirementId],$ids));
  $selectedQ=$pdo->prepare('SELECT COUNT(*) FROM local_applications WHERE requirement_id=? AND status="selected"');$selectedQ->execute([$requirementId]);$selectedTotal=(int)$selectedQ->fetchColumn();
  $newStatus=$selectedTotal>=(int)$req['openings_count']?'active':'open';
  $pdo->prepare('UPDATE local_requirements SET status=?,updated_at=UTC_TIMESTAMP() WHERE id=?')->execute([$newStatus,$requirementId]);
  $pdo->commit();

  $pushReport=['ok'=>true,'requested'=>0,'sent'=>0,'failed'=>0,'message'=>'No push attempted.'];
  try{
    if($pushStaffUserIds){
      $tokens=localFcmTokensForUsers($pdo,$pushStaffUserIds);
      $pushReport=localFcmSendTokens(
        $pdo,
        $tokens,
        'You have been selected',
        'You have been selected for "' . (string)$req['title'] . '" on ' . (string)$req['shift_date'],
        ['screen'=>'application','requirement_id'=>$requirementId]
      );
    }
  }catch(Throwable $notificationError){
    error_log('MWH Local selection push: '.$notificationError->getMessage());
    $pushReport=['ok'=>false,'requested'=>0,'sent'=>0,'failed'=>0,'message'=>'Selection saved, but push notification failed.'];
  }

  localJson(['success'=>true,'message'=>'Staff selection submitted','data'=>['requirement_id'=>$requirementId,'selected_count'=>count($ids),'total_selected'=>$selectedTotal,'status'=>$newStatus,'push_notification'=>$pushReport]]);
}catch(InvalidArgumentException $e){if($pdo->inTransaction())$pdo->rollBack();localJson(['success'=>false,'message'=>$e->getMessage()],422);}
catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();error_log('MWH Local select v2: '.$e->getMessage());localJson(['success'=>false,'message'=>'Unable to submit staff selection'],500);}