<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

function answer(int $status, bool $success, string $message): never {
    http_response_code($status);
    echo json_encode(['success' => $success, 'message' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    answer(405, false, 'طريقة الطلب غير مسموحة.');
}

session_start();
$userId = $_SESSION['user_id'] ?? null;
if (!$userId || !ctype_digit((string)$userId)) {
    answer(401, false, 'يجب تسجيل الدخول أولًا.');
}

// رمز خاص بجلسة المستخدم، يصل للواجهة عبر list_resumes.php.
$token = $_POST['csrf_token'] ?? '';
if (!is_string($token) || !isset($_SESSION['resume_delete_csrf']) ||
    !hash_equals($_SESSION['resume_delete_csrf'], $token)) {
    answer(403, false, 'انتهت صلاحية طلب الحذف. حدّث الصفحة وحاول مرة أخرى.');
}

$type = $_POST['type'] ?? '';
$id = $_POST['id'] ?? '';
if (!is_string($type) || !in_array($type, ['created', 'uploaded'], true) ||
    !is_string($id) || !preg_match('/^[1-9][0-9]*$/D', $id)) {
    answer(400, false, 'بيانات الحذف غير صحيحة.');
}

require_once __DIR__ . '/db.php';
$userId = (int)$userId;
$id = (int)$id;
$renamedFrom = null;
$renamedTo = null;

try {
    $pdo->beginTransaction();

    if ($type === 'created') {
        // الأقسام المرتبطة تحذف تلقائيًا بواسطة ON DELETE CASCADE.
        $stmt = $pdo->prepare('SELECT resume_id FROM resumes WHERE resume_id = ? AND user_id = ? FOR UPDATE');
        $stmt->execute([$id, $userId]);
        if (!$stmt->fetch()) {
            $pdo->rollBack();
            answer(404, false, 'السيرة غير موجودة أو لا تملك صلاحية حذفها.');
        }
        $stmt = $pdo->prepare('DELETE FROM resumes WHERE resume_id = ? AND user_id = ?');
        $stmt->execute([$id, $userId]);
    } else {
        $stmt = $pdo->prepare('SELECT stored_name FROM uploaded_resumes WHERE upload_id = ? AND user_id = ? FOR UPDATE');
        $stmt->execute([$id, $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            $pdo->rollBack();
            answer(404, false, 'الملف غير موجود أو لا تملك صلاحية حذفه.');
        }

        $storedName = (string)$row['stored_name'];
        if (!preg_match('/^[a-f0-9]{32}\.(pdf|doc|docx)$/iD', $storedName)) {
            throw new RuntimeException('اسم الملف المخزن غير آمن.');
        }
        $filePath = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR
            . 'smart_cv_private_uploads' . DIRECTORY_SEPARATOR
            . $userId . DIRECTORY_SEPARATOR . $storedName;

        // نقل مؤقت قبل حذف السجل حتى لا يعود الملف للظهور عبر استيراد الملفات القديمة.
        if (is_file($filePath)) {
            $renamedFrom = $filePath;
            $renamedTo = $filePath . '.deleting-' . bin2hex(random_bytes(8));
            if (!rename($renamedFrom, $renamedTo)) {
                throw new RuntimeException('تعذر تجهيز الملف للحذف.');
            }
        }

        $stmt = $pdo->prepare('DELETE FROM uploaded_resumes WHERE upload_id = ? AND user_id = ?');
        $stmt->execute([$id, $userId]);
    }

    $pdo->commit();

    if ($renamedTo !== null && is_file($renamedTo) && !unlink($renamedTo)) {
        error_log('SmartCV delete: leftover file ' . basename($renamedTo));
    }
    answer(200, true, 'تم حذف السيرة الذاتية بنجاح.');
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    if ($renamedFrom !== null && $renamedTo !== null && is_file($renamedTo)) {
        if (!rename($renamedTo, $renamedFrom)) {
            error_log('SmartCV delete: failed to restore ' . basename($renamedTo));
        }
    }
    error_log('SmartCV delete: ' . $e->getMessage());
    answer(500, false, 'تعذر حذف السيرة. حاول مرة أخرى.');
}
