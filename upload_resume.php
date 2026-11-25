
<?php
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function reply($status, $success, $message) {
    http_response_code($status);
    echo json_encode([
        'success' => $success,
        'message' => $message
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    reply(405, false, 'طريقة الطلب غير مسموحة.');
}

session_start();

// عدّل اسم مفتاح الجلسة إذا كان مختلفًا في login.php
$userId = $_SESSION['user_id'] ?? null;

if (!$userId || !ctype_digit((string)$userId)) {
    reply(401, false, 'يجب تسجيل الدخول أولًا.');
}

if (!isset($_FILES['resume'])) {
    reply(400, false, 'لم يتم إرسال ملف.');
}

$file = $_FILES['resume'];

if ($file['error'] !== UPLOAD_ERR_OK) {
    reply(400, false, 'حدث خطأ أثناء رفع الملف.');
}

if ($file['size'] <= 0 || $file['size'] > 5 * 1024 * 1024) {
    reply(400, false, 'حجم الملف غير مسموح.');
}

$extension = strtolower(
    pathinfo($file['name'], PATHINFO_EXTENSION)
);

$allowed = [
    'pdf' => 'application/pdf',
    'doc' => 'application/msword',
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
];

if (!isset($allowed[$extension])) {
    reply(400, false, 'نوع الملف غير مدعوم.');
}

$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime = $finfo->file($file['tmp_name']);

$valid = false;

if ($extension === 'pdf') {
    $header = file_get_contents($file['tmp_name'], false, null, 0, 5);
    $valid = ($mime === 'application/pdf' && $header === '%PDF-');
} elseif ($extension === 'docx') {
    $valid = in_array($mime, [
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/zip'
    ], true);

    if ($valid && class_exists('ZipArchive')) {
        $zip = new ZipArchive();
        $valid = $zip->open($file['tmp_name']) === true;

        if ($valid) {
            $valid = $zip->locateName('[Content_Types].xml') !== false
                && $zip->locateName('word/document.xml') !== false;
            $zip->close();
        }
    } else {
        $valid = false;
    }
} else {
    $valid = in_array($mime, [
        'application/msword',
        'application/x-ole-storage',
        'application/octet-stream'
    ], true);
}

if (!$valid || !is_uploaded_file($file['tmp_name'])) {
    reply(400, false, 'محتوى الملف غير صالح.');
}

// التخزين خارج مجلد الموقع العام htdocs
$storageRoot = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR
    . 'smart_cv_private_uploads';

$userFolder = $storageRoot . DIRECTORY_SEPARATOR . (int)$userId;

if (!is_dir($userFolder) &&
    !mkdir($userFolder, 0700, true)) {
    reply(500, false, 'تعذر إنشاء مجلد الحفظ.');
}

$filename = bin2hex(random_bytes(16)) . '.' . $extension;
$destination = $userFolder . DIRECTORY_SEPARATOR . $filename;

if (!move_uploaded_file($file['tmp_name'], $destination)) {
    reply(500, false, 'فشل حفظ الملف.');
}

try {
    require_once __DIR__ . '/db.php';
    $stmt = $pdo->prepare('INSERT INTO uploaded_resumes (user_id, original_name, stored_name, file_size) VALUES (?, ?, ?, ?)');
    $originalName = mb_substr(basename(str_replace('\\', '/', $file['name'])), 0, 255);
    $stmt->execute([(int)$userId, $originalName, $filename, (int)$file['size']]);
} catch (Throwable $e) {
    @unlink($destination);
    error_log('SmartCV upload metadata: '.$e->getMessage());
    reply(500, false, 'تعذر تسجيل الملف في قاعدة البيانات. تأكد من تشغيل ملف SQL الجديد.');
}
reply(200, true, 'تم رفع السيرة الذاتية وحفظها بنجاح.');
