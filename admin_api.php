<?php
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
session_start();

require_once __DIR__ . '/db.php';

// التحقق من أن المستخدم مدير
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'غير مصرح لك بالدخول.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

try {
    // 1. جلب قائمة المستخدمين
    if ($action === 'get_users' && $method === 'GET') {
        $stmt = $pdo->query("SELECT user_id, full_name, email, role, created_at FROM users ORDER BY created_at DESC");
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'users' => $users], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // 2. حذف مستخدم
    if ($action === 'delete_user' && $method === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        $userIdToDelete = (int)($data['user_id'] ?? 0);

        if ($userIdToDelete === (int)$_SESSION['user_id']) {
            echo json_encode(['success' => false, 'message' => 'لا يمكنك حذف حسابك الشخصي!'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // الحذف سيقوم بحذف كل السير والملفات المرتبطة به تلقائياً (Cascade)
        $stmt = $pdo->prepare("DELETE FROM users WHERE user_id = ? AND role != 'admin'");
        $stmt->execute([$userIdToDelete]);

        if ($stmt->rowCount() > 0) {
            echo json_encode(['success' => true, 'message' => 'تم حذف المستخدم بنجاح.'], JSON_UNESCAPED_UNICODE);
        } else {
            echo json_encode(['success' => false, 'message' => 'تعذر الحذف (قد يكون مديراً أو غير موجود).'], JSON_UNESCAPED_UNICODE);
        }
        exit;
    }

    // 3. جلب إعدادات الذكاء الاصطناعي
    if ($action === 'get_settings' && $method === 'GET') {
        $stmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
        $settings = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
        echo json_encode(['success' => true, 'settings' => $settings], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // 4. حفظ إعدادات الذكاء الاصطناعي
    if ($action === 'save_settings' && $method === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        $aiModel = trim($data['ai_model'] ?? '');

        if (empty($aiModel)) {
            echo json_encode(['success' => false, 'message' => 'اسم الموديل مطلوب.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('ai_model', ?) ON DUPLICATE KEY UPDATE setting_value = ?");
        $stmt->execute([$aiModel, $aiModel]);

        echo json_encode(['success' => true, 'message' => 'تم حفظ الإعدادات بنجاح.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'طلب غير صالح.'], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    error_log('Admin API Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'حدث خطأ في الخادم.'], JSON_UNESCAPED_UNICODE);
}