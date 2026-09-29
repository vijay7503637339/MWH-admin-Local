<?php
declare(strict_types=1);
require_once __DIR__ . '/../auth/helpers.php';
try{
  $user=localRequireUser($pdo);
  if((string)$user['role']!=='contractor')localJson(['success'=>false,'message'=>'Contractor access is required'],403);
  localJson(['success'=>false,'message'=>'Admin payment controls are available in the Local admin panel'],403);
}catch(Throwable $e){localJson(['success'=>false,'message'=>'Unable to load payment controls'],500);}
