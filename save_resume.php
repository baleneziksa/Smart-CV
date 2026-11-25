<?php
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
session_start();
function reply($status, $body) { http_response_code($status); echo json_encode($body, JSON_UNESCAPED_UNICODE); exit; }
if (!isset($_SESSION['user_id'])) reply(401, ['success'=>false,'message'=>'سجل الدخول أولاً.']);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') reply(405, ['success'=>false,'message'=>'طريقة الطلب غير مسموحة.']);
$data = json_decode(file_get_contents('php://input'), true);
if (!is_array($data)) reply(400, ['success'=>false,'message'=>'البيانات غير صحيحة.']);
$name = trim((string)($data['fullName'] ?? ''));
if ($name === '' || mb_strlen($name) > 150) reply(422, ['success'=>false,'message'=>'أدخل الاسم الكامل (حتى 150 حرفًا).']);
$map = [
 'education'=>['table'=>'education','fields'=>['degree'=>'degree','major'=>'major','school'=>'school','year'=>'graduation_year']],
 'experience'=>['table'=>'experiences','fields'=>['position'=>'position','company'=>'company','start'=>'start_date','end'=>'end_date','description'=>'description']],
 'skills'=>['table'=>'skills','fields'=>['name'=>'skill_name']],
 'languages'=>['table'=>'languages','fields'=>['name'=>'language_name','level'=>'proficiency_level']],
 'certifications'=>['table'=>'certifications','fields'=>['title'=>'title','issuer'=>'issuer','date'=>'issue_date','url'=>'certificate_url']],
 'projects'=>['table'=>'projects','fields'=>['title'=>'title','url'=>'project_url','description'=>'description']],
 'volunteering'=>['table'=>'volunteering','fields'=>['role'=>'role_title','organization'=>'organization','start'=>'start_date','end'=>'end_date','description'=>'description']],
 'references'=>['table'=>'resume_references','fields'=>['name'=>'full_name','position'=>'position','organization'=>'organization','contact'=>'contact']]
];
foreach ($map as $key=>$config) {
 if (isset($data[$key]) && (!is_array($data[$key]) || count($data[$key]) > 100)) reply(422, ['success'=>false,'message'=>'عدد العناصر أو نوعها غير صحيح: '.$key]);
}
// التحقق من الأقسام المخصصة وحمايتها قبل الحفظ.
$customSections = $data['customSections'] ?? [];
if (!is_array($customSections) || count($customSections) > 20) {
 reply(422, ['success'=>false,'message'=>'عدد الأقسام الإضافية غير صالح (الحد 20).']);
}
$validatedCustom = [];
foreach ($customSections as $section) {
 if (!is_array($section) || !isset($section['title']) || !is_string($section['title'])) {
  reply(422, ['success'=>false,'message'=>'أدخل اسمًا صحيحًا للقسم الإضافي.']);
 }
 $title = trim($section['title']);
 if ($title === '' || mb_strlen($title, 'UTF-8') > 100) {
  reply(422, ['success'=>false,'message'=>'اسم القسم الإضافي مطلوب (حتى 100 حرف).']);
 }
 $items = $section['items'] ?? [];
 if (!is_array($items) || count($items) > 20) {
  reply(422, ['success'=>false,'message'=>'الحد الأقصى 20 عنصرًا لكل قسم إضافي.']);
 }
 $validatedItems = [];
 foreach ($items as $item) {
  if (!is_array($item)) reply(422, ['success'=>false,'message'=>'بيانات العنصر الإضافي غير صحيحة.']);
  $heading = $item['heading'] ?? '';
  $description = $item['description'] ?? '';
  if (!is_string($heading) || !is_string($description) || mb_strlen($heading, 'UTF-8') > 150 || mb_strlen($description, 'UTF-8') > 2000) {
   reply(422, ['success'=>false,'message'=>'النص في أحد الأقسام الإضافية أطول من المسموح.']);
  }
  $heading = trim($heading);
  $description = trim($description);
  if ($heading !== '' || $description !== '') $validatedItems[] = ['heading'=>$heading,'description'=>$description];
 }
 $validatedCustom[] = ['title'=>$title,'items'=>$validatedItems];
}
$customJson = json_encode($validatedCustom, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
if ($customJson === false) reply(422, ['success'=>false,'message'=>'تعذر قراءة الأقسام الإضافية.']);
require_once __DIR__ . '/db.php';
try {
 $pdo->beginTransaction();
 $userId = (int)$_SESSION['user_id'];
 $resumeId = filter_var($data['resume_id'] ?? null, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]) ?: null;
 $main = [
  'title'=>mb_substr(trim((string)($data['title'] ?? 'سيرتي الذاتية')) ?: 'سيرتي الذاتية',0,150),
  'full_name'=>$name, 'job_title'=>$data['jobTitle'] ?? null, 'email'=>$data['email'] ?? null,
  'phone'=>$data['phone'] ?? null, 'city'=>$data['city'] ?? null,
  'linkedin_url'=>$data['linkedin'] ?? null, 'summary'=>$data['summary'] ?? null, 'custom_sections'=>$customJson
 ];
 if ($resumeId) {
  $owner = $pdo->prepare('SELECT resume_id FROM resumes WHERE resume_id = ? AND user_id = ? FOR UPDATE');
  $owner->execute([$resumeId,$userId]);
  if (!$owner->fetch()) { $pdo->rollBack(); reply(404, ['success'=>false,'message'=>'السيرة غير موجودة أو ليست ملكك.']); }
  $sql='UPDATE resumes SET title=:title, full_name=:full_name, job_title=:job_title, email=:email, phone=:phone, city=:city, linkedin_url=:linkedin_url, summary=:summary, custom_sections=:custom_sections WHERE resume_id=:resume_id AND user_id=:user_id';
  $stmt=$pdo->prepare($sql); $stmt->execute($main+['resume_id'=>$resumeId,'user_id'=>$userId]);
 } else {
  $sql='INSERT INTO resumes (user_id,title,full_name,job_title,email,phone,city,linkedin_url,summary,custom_sections) VALUES (:user_id,:title,:full_name,:job_title,:email,:phone,:city,:linkedin_url,:summary,:custom_sections)';
  $stmt=$pdo->prepare($sql); $stmt->execute(['user_id'=>$userId]+$main);
  $resumeId=(int)$pdo->lastInsertId();
 }
 foreach ($map as $key=>$config) {
  $table=$config['table']; $fields=$config['fields'];
  $pdo->prepare("DELETE FROM `$table` WHERE resume_id = ?")->execute([$resumeId]);
  $columns=array_values($fields);
  $quoted=implode(',',array_map(fn($c)=>"`$c`", $columns));
  $placeholders=implode(',',array_fill(0,count($columns)+1,'?'));
  $insert=$pdo->prepare("INSERT INTO `$table` (resume_id,$quoted) VALUES ($placeholders)");
  foreach (($data[$key] ?? []) as $item) {
   if (!is_array($item)) continue;
   $values=[];
   foreach ($fields as $js=>$db) $values[]=trim((string)($item[$js] ?? ''));
   if (!array_filter($values,fn($v)=>$v!=='')) continue;
   // Mandatory fields in these tables must not be empty.
   if (in_array($key,['skills','languages','certifications','projects'],true) && $values[0]==='') continue;
   $insert->execute(array_merge([$resumeId],array_map(fn($v)=>$v===''?null:$v,$values)));
  }
 }
 // احفظ الربط مع الملف الأصلي بعد أول حفظ، ولا تُنشئ سيرة جديدة كل مرة.
 $originUploadId = filter_var($data['origin_upload_id'] ?? null, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]) ?: null;
 if ($originUploadId) {
   $check = $pdo->prepare('SELECT linked_resume_id FROM uploaded_resumes WHERE upload_id=? AND user_id=? FOR UPDATE');
   $check->execute([$originUploadId,$userId]);
   $uploadRow = $check->fetch(PDO::FETCH_ASSOC);
   if (!$uploadRow) throw new RuntimeException('الملف الأصلي غير موجود أو لا يخص المستخدم.');
   if ($uploadRow['linked_resume_id'] !== null && (int)$uploadRow['linked_resume_id'] !== (int)$resumeId) {
     // قد تشير النسخ القديمة إلى سيرة حُذفت سابقًا؛ لا نمنع إعادة حفظها.
     $oldLinked = $pdo->prepare('SELECT resume_id FROM resumes WHERE resume_id=? AND user_id=?');
     $oldLinked->execute([(int)$uploadRow['linked_resume_id'],$userId]);
     if ($oldLinked->fetch()) {
       throw new RuntimeException('هذا الملف مرتبط بسيرة محفوظة أخرى. افتح السيرة من لوحة التحكم.');
     }
   }
   $link = $pdo->prepare('UPDATE uploaded_resumes SET linked_resume_id=? WHERE upload_id=? AND user_id=?');
   $link->execute([$resumeId,$originUploadId,$userId]);
 }
 $pdo->commit();
 reply(200,['success'=>true,'message'=>'تم حفظ السيرة في قاعدة البيانات بنجاح.','resume_id'=>$resumeId]);
} catch (Throwable $e) {
 if ($pdo->inTransaction()) $pdo->rollBack();
 error_log('SmartCV save: '.$e->getMessage());
 reply(500,['success'=>false,'message'=>'تعذر حفظ السيرة. راجع سجلات PHP إذا استمر الخطأ.']);
}
