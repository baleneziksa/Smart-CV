<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
session_start();

function out(int $code, array $payload): never {
    http_response_code($code);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') out(405, ['success'=>false,'message'=>'طريقة الطلب غير مسموحة.']);
$userId = $_SESSION['user_id'] ?? null;
if (!$userId || !ctype_digit((string)$userId)) out(401, ['success'=>false,'message'=>'يجب تسجيل الدخول أولًا.']);
if (empty($_SESSION['resume_ai_csrf'])) out(403, ['success'=>false,'message'=>'انتهت صلاحية الطلب؛ حدّث لوحة التحكم.']);
if (($_SERVER['CONTENT_LENGTH'] ?? 0) > 200000) out(413, ['success'=>false,'message'=>'نتيجة التحليل كبيرة جدًا.']);
$raw = file_get_contents('php://input');
$request = json_decode($raw ?: '', true);
if (!is_array($request)) out(400, ['success'=>false,'message'=>'بيانات التحليل غير صحيحة.']);
$token = $request['csrf_token'] ?? null;
if (!is_string($token) || !hash_equals((string)$_SESSION['resume_ai_csrf'], $token)) {
    out(403, ['success'=>false,'message'=>'رمز الطلب غير صحيح. أعد تحميل لوحة التحكم.']);
}
$id = $request['upload_id'] ?? null;
if (!is_int($id) && !is_string($id)) out(400, ['success'=>false,'message'=>'رقم الملف غير صالح.']);
if (!preg_match('/^[1-9][0-9]*$/D', (string)$id)) out(400, ['success'=>false,'message'=>'رقم الملف غير صالح.']);
$resume = $request['resume'] ?? null;
if (!is_array($resume)) out(422, ['success'=>false,'message'=>'لا توجد بيانات سيرة قابلة للحفظ.']);
// نحتفظ فقط بالحقول المعروفة؛ لا نثق بأسماء حقول إضافية قادمة من المتصفح.
$personal = ['fullName','jobTitle','email','phone','city','linkedin','summary'];
$sectionFields = [
    'education'=>['degree','major','school','year'],
    'experience'=>['position','company','start','end','description'],
    'skills'=>['name'],
    'languages'=>['name','level'],
    'certifications'=>['title','issuer','date','url'],
    'projects'=>['title','url','description'],
    'volunteering'=>['role','organization','start','end','description'],
    'references'=>['name','position','organization','contact']
];
$safe = [];
foreach ($personal as $field) {
    $v = $resume[$field] ?? '';
    $safe[$field] = is_string($v) ? mb_substr(trim($v), 0, $field === 'summary' ? 4000 : 300) : '';
}
foreach ($sectionFields as $section => $fields) {
    $safe[$section] = [];
    $records = $resume[$section] ?? [];
    if (!is_array($records)) continue;
    foreach (array_slice($records, 0, 80) as $item) {
        if (is_string($item) && $section === 'skills') $item = ['name'=>$item];
        if (!is_array($item)) continue;
        $record = [];
        foreach ($fields as $field) {
            $value = $item[$field] ?? '';
            $record[$field] = is_string($value) ? mb_substr(trim($value), 0, 4000) : '';
        }
        if (count(array_filter($record, static fn($v) => $v !== '')) > 0) $safe[$section][] = $record;
    }
}
$encoded = json_encode($safe, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
if ($encoded === false || strlen($encoded) > 200000) {
    out(422, ['success'=>false,'message'=>'تعذّر حفظ نتيجة التحليل: حجم البيانات كبير أو غير صالح.']);
}
require_once __DIR__ . '/db.php';
try {
    $pdo->beginTransaction();
    $stmt = $pdo->prepare('SELECT ai_extracted_at,linked_resume_id FROM uploaded_resumes
       WHERE upload_id=? AND user_id=? FOR UPDATE');
    $stmt->execute([(int)$id,(int)$userId]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$existing) {
        $pdo->rollBack();
        out(404, ['success'=>false,'message'=>'الملف غير موجود أو لا يخص المستخدم.']);
    }
    if ($existing['ai_extracted_at'] !== null) {
        $pdo->commit();
        out(200, ['success'=>true,'already_extracted'=>true,'message'=>'تم استخراج هذه السيرة مسبقًا؛ لم نعد التحليل.']);
    }
    if ($existing['linked_resume_id'] !== null) {
        $pdo->rollBack();
        out(409, ['success'=>false,'message'=>'هذه السيرة معدّلة ومحفوظة مسبقًا؛ افتحها بزر تعديل.']);
    }
    $stmt = $pdo->prepare('UPDATE uploaded_resumes SET ai_extracted_json=?, ai_extracted_at=NOW()
      WHERE upload_id=? AND user_id=? AND ai_extracted_at IS NULL');
    $stmt->execute([$encoded,(int)$id,(int)$userId]);
    $pdo->commit();
    out(200, ['success'=>true,'message'=>'حُفظت البيانات المستخرجة. لن يعيد زر تعديل تحليلها.']);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('SmartCV save cached AI: ' . $e->getMessage());
    out(500, ['success'=>false,'message'=>'تعذّر حفظ نتيجة الذكاء الاصطناعي في قاعدة البيانات.']);
}
