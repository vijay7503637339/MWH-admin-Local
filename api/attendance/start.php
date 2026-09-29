<?php
declare(strict_types=1);
require_once __DIR__ . '/../auth/helpers.php';
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') localJson(['success'=>false,'message'=>'POST request required'],405);
try{
  $user=localRequireUser($pdo);if((string)$user['role']!=='staff')localJson(['success'=>false,'message'=>'Staff access is required'],403);
  $d=localInput();$id=(int)($d['assignment_id']??0);if($id<=0)localJson(['success'=>false,'message'=>'assignment_id is required'],422);
  $q=$pdo->prepare('SELECT id,status,requirement_id,staff_user_id,start_at FROM local_assignments WHERE id=? AND staff_user_id=? LIMIT 1');$q->execute([$id,(int)$user['id']]);$a=$q->fetch(PDO::FETCH_ASSOC);
  if(!$a)localJson(['success'=>false,'message'=>'Assignment not found'],404);
  if((string)$a['status']!=='arrived')localJson(['success'=>false,'message'=>'Mark arrival before starting the duty'],409);
  $exists=$pdo->prepare('SELECT id FROM local_attendance WHERE assignment_id=? LIMIT 1');$exists->execute([$id]);$attId=(int)($exists->fetchColumn()?:0);
  if($attId>0){
    $pdo->prepare('UPDATE local_assignments SET status="active",start_at=COALESCE(start_at,UTC_TIMESTAMP()),updated_at=UTC_TIMESTAMP() WHERE id=?')->execute([$id]);
    localJson(['success'=>true,'message'=>'Duty is active','data'=>['attendance_id'=>$attId,'assignment_id'=>$id,'status'=>'active']]);
  }
  $pdo->prepare('INSERT INTO local_attendance(assignment_id,staff_user_id,check_in_at,check_in_photo_path,check_in_latitude,check_in_longitude) VALUES(?,?,UTC_TIMESTAMP(),?,?,?)')->execute([
    $id,(int)$user['id'],
    !empty($d['photo_path'])?(string)$d['photo_path']:null,
    isset($d['latitude'])?(float)$d['latitude']:null,
    isset($d['longitude'])?(float)$d['longitude']:null
  ]);
  $attId=(int)$pdo->lastInsertId();
  $pdo->prepare('UPDATE local_assignments SET status="active",start_at=COALESCE(start_at,UTC_TIMESTAMP()),updated_at=UTC_TIMESTAMP() WHERE id=?')->execute([$id]);
  localJson(['success'=>true,'message'=>'Duty started','data'=>['attendance_id'=>$attId,'assignment_id'=>$id,'status'=>'active']]);
}catch(Throwable $e){error_log('MWH Local duty start: '.$e->getMessage());localJson(['success'=>false,'message'=>'Unable to start duty'],500);}
