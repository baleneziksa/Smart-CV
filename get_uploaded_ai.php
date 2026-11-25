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
if ($_SERVER['REQUEST_METHOD'] !== 'GET') out(405, ['success'=>false,'message'=>'طريقة الطلب غير مسموحة.']);
$userId = $_SESSION['user_id'] ?? null;
if (!$userId || !ctype_digit((string)$userId)) out(401, ['success'=>false,'message'=>'يجب تسجيل الدخول أولًا.']);
$id = $_GET['id'] ?? null;
if (!is_string($id) || !preg_match('/^[1-9][0-9]*$/D', $id)) out(400, ['success'=>false,'message'=>'رقم الملف غير صالح.']);
require_once __DIR__ . '/db.php';
try {
    // لا نرجع سوى ملفات المستخدم الحالي. فحص وجود السيرة المرتبطة يحمي من الروابط القديمة.
    $stmt = $pdo->prepare('SELECT u.ai_extracted_json,u.ai_extracted_at,r.resume_id AS active_resume_id
        FROM uploaded_resumes u LEFT JOIN resumes r
          ON r.resume_id=u.linked_resume_id AND r.user_id=u.user_id
        WHERE u.upload_id=? AND u.user_id=? LIMIT 1');
    $stmt->execute([(int)$id,(int)$userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) out(404, ['success'=>false,'message'=>'الملف غير موجود أو لا تملك صلاحية الوصول إليه.']);
    $data = is_string($row['ai_extracted_json'] ?? null)
        ? json_decode($row['ai_extracted_json'], true) : null;
    out(200, [
        'success'=>true,
        'extracted'=>$row['ai_extracted_at'] !== null,
        'resume'=>is_array($data) ? $data : null,
        'linked_resume_id'=>$row['active_resume_id'] !== null ? (int)$row['active_resume_id'] : null
    ]);
} catch (Throwable $e) {
    error_log('SmartCV read cached extraction: ' . $e->getMessage());
    out(500, ['success'=>false,'message'=>'تعذّر قراءة الاستخراج المحفوظ. تأكد من تنفيذ ملف تحديث قاعدة البيانات.']);
}
