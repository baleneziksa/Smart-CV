<?php
session_start();
if (!isset($_SESSION['user_id'])) {http_response_code(401);exit('Unauthorized');}
$id=filter_input(INPUT_GET,'id',FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
if (!$id){http_response_code(400);exit('Invalid ID');}
require_once __DIR__.'/db.php';
$stmt=$pdo->prepare('SELECT original_name,stored_name FROM uploaded_resumes WHERE upload_id=? AND user_id=?');
$stmt->execute([$id,(int)$_SESSION['user_id']]);
$r=$stmt->fetch();
if (!$r){http_response_code(404);exit('Not found');}
$stored=basename($r['stored_name']);
if (!preg_match('/^[a-f0-9]{32}\.(pdf|doc|docx)$/',$stored)){http_response_code(404);exit('Not found');}
$legacy=dirname(__DIR__,2).DIRECTORY_SEPARATOR.'smart_cv_private_uploads';
$private=dirname(__DIR__,3).DIRECTORY_SEPARATOR.'smart_cv_private_uploads';
$folder=(int)$_SESSION['user_id'];
$path=$private.DIRECTORY_SEPARATOR.$folder.DIRECTORY_SEPARATOR.$stored;
if (!is_file($path)) $path=$legacy.DIRECTORY_SEPARATOR.$folder.DIRECTORY_SEPARATOR.$stored;
if (!is_file($path)){http_response_code(404);exit('File missing');}
$ext=strtolower(pathinfo($stored,PATHINFO_EXTENSION));
$mimes=['pdf'=>'application/pdf','doc'=>'application/msword','docx'=>'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store');
header('Content-Type: '.$mimes[$ext]);
$disposition=isset($_GET['download']) && $_GET['download']==='1' ? 'attachment' : ($ext==='pdf' ? 'inline' : 'attachment');
$name=str_replace(["\r","\n",'"','\\'],'_', $r['original_name']);
header('Content-Disposition: '.$disposition.'; filename="resume.'.$ext.'"; filename*=UTF-8\'\''.rawurlencode($name));
header('Content-Length: '.filesize($path));
readfile($path);
