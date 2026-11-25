<?php
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
session_start();
function reply($status,$body) { http_response_code($status); echo json_encode($body,JSON_UNESCAPED_UNICODE); exit; }
if (!isset($_SESSION['user_id'])) reply(401,['success'=>false,'message'=>'سجل الدخول أولاً.']);
require_once __DIR__.'/db.php';
try {
 $id=filter_var($_GET['id'] ?? null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
 if ($id) {
  $stmt=$pdo->prepare('SELECT * FROM resumes WHERE resume_id=? AND user_id=?');
  $stmt->execute([$id,(int)$_SESSION['user_id']]);
 } else {
  $stmt=$pdo->prepare('SELECT * FROM resumes WHERE user_id=? ORDER BY updated_at DESC,resume_id DESC LIMIT 1');
  $stmt->execute([(int)$_SESSION['user_id']]);
 }
 $r=$stmt->fetch();
 if (!$r) reply(200,['success'=>true,'resume'=>null]);
 $data=[
  'resume_id'=>(int)$r['resume_id'],'title'=>$r['title'],
  'fullName'=>$r['full_name'],'jobTitle'=>$r['job_title'],'email'=>$r['email'],
  'phone'=>$r['phone'],'city'=>$r['city'],'linkedin'=>$r['linkedin_url'],'summary'=>$r['summary'],
  'customSections'=>is_string($r['custom_sections'] ?? null) ? (json_decode($r['custom_sections'],true) ?: []) : []
 ];
 $map=[
  'education'=>['education'=>['degree'=>'degree','major'=>'major','school'=>'school','year'=>'graduation_year']],
  'experiences'=>['experience'=>['position'=>'position','company'=>'company','start'=>'start_date','end'=>'end_date','description'=>'description']],
  'skills'=>['skills'=>['name'=>'skill_name']],
  'languages'=>['languages'=>['name'=>'language_name','level'=>'proficiency_level']],
  'certifications'=>['certifications'=>['title'=>'title','issuer'=>'issuer','date'=>'issue_date','url'=>'certificate_url']],
  'projects'=>['projects'=>['title'=>'title','url'=>'project_url','description'=>'description']],
  'volunteering'=>['volunteering'=>['role'=>'role_title','organization'=>'organization','start'=>'start_date','end'=>'end_date','description'=>'description']],
  'resume_references'=>['references'=>['name'=>'full_name','position'=>'position','organization'=>'organization','contact'=>'contact']]
 ];
 foreach ($map as $table=>$spec) {
  $key=array_key_first($spec); $fields=$spec[$key];
  $stmt=$pdo->prepare("SELECT * FROM `$table` WHERE resume_id=?");
  $stmt->execute([$r['resume_id']]);
  $data[$key]=[];
  foreach ($stmt->fetchAll() as $row) {
   $item=[]; foreach ($fields as $js=>$db) $item[$js]=$row[$db] ?? '';
   $data[$key][]=$item;
  }
 }
 reply(200,['success'=>true,'resume'=>$data]);
} catch (Throwable $e) {
 error_log('SmartCV get: '.$e->getMessage());
 reply(500,['success'=>false,'message'=>'تعذر استرجاع السيرة.']);
}
