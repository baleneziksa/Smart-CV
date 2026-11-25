<?php
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
session_start();
if (!isset($_SESSION['user_id'])) { http_response_code(401); echo json_encode(['success'=>false,'message'=>'سجل الدخول أولاً.'],JSON_UNESCAPED_UNICODE); exit; }
require_once __DIR__.'/db.php';
try {
 $userId=(int)$_SESSION['user_id'];
 if (empty($_SESSION['resume_delete_csrf'])) {
   $_SESSION['resume_delete_csrf'] = bin2hex(random_bytes(32));
 }
 if (empty($_SESSION['resume_ai_csrf'])) {
   $_SESSION['resume_ai_csrf'] = bin2hex(random_bytes(32));
 }
 // Import files uploaded before metadata tracking was introduced.
 $legacy=dirname(__DIR__,2).DIRECTORY_SEPARATOR.'smart_cv_private_uploads'.DIRECTORY_SEPARATOR.$userId;
 if (is_dir($legacy)) {
   $known=$pdo->prepare('SELECT upload_id FROM uploaded_resumes WHERE user_id=? AND stored_name=? LIMIT 1');
   $insert=$pdo->prepare('INSERT INTO uploaded_resumes (user_id,original_name,stored_name,file_size) VALUES (?,?,?,?)');
   foreach (scandir($legacy) as $file) {
     if (!preg_match('/^[a-f0-9]{32}\.(pdf|doc|docx)$/',$file)) continue;
     $known->execute([$userId,$file]);
     if (!$known->fetch()) $insert->execute([$userId,'ملف سابق - '.$file,$file,filesize($legacy.DIRECTORY_SEPARATOR.$file)]);
   }
 }

 $s=$pdo->prepare('SELECT resume_id,title,full_name,updated_at FROM resumes WHERE user_id=? ORDER BY updated_at DESC,resume_id DESC');
 $s->execute([$userId]);
 $created=$s->fetchAll();
 $s=$pdo->prepare('SELECT u.upload_id,u.original_name,u.file_size,u.created_at,u.ai_extracted_at,r.resume_id AS linked_resume_id
  FROM uploaded_resumes u LEFT JOIN resumes r ON r.resume_id=u.linked_resume_id AND r.user_id=u.user_id
  WHERE u.user_id=? ORDER BY u.created_at DESC,u.upload_id DESC');
 $s->execute([$userId]);
 echo json_encode(['success'=>true,'created'=>$created,'uploaded'=>$s->fetchAll(),'delete_csrf'=>$_SESSION['resume_delete_csrf'],'ai_csrf'=>$_SESSION['resume_ai_csrf']],JSON_UNESCAPED_UNICODE);
} catch(Throwable $e){error_log('SmartCV list: '.$e->getMessage());http_response_code(500);echo json_encode(['success'=>false,'message'=>'تعذر تحميل قائمة السير.'],JSON_UNESCAPED_UNICODE);}
