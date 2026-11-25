<?php
header("Content-Type: application/json; charset=utf-8");

require_once __DIR__ . "/db.php";

function respond($status, $success, $message, $extra = []) {
    http_response_code($status);
    echo json_encode(array_merge([
        "success" => $success,
        "message" => $message
    ], $extra), JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    respond(405, false, "طريقة الطلب غير مسموحة.");
}

$data = json_decode(file_get_contents("php://input"), true);

if (!is_array($data)) {
    respond(400, false, "بيانات الطلب غير صحيحة.");
}

$email = trim($data["email"] ?? "");
$password = $data["password"] ?? "";

if (!filter_var($email, FILTER_VALIDATE_EMAIL) ||
    !is_string($password) || $password === "") {
    respond(422, false, "أدخل البريد الإلكتروني وكلمة المرور.");
}

try {
    // [تعديل] إضافة جلب الصلاحية role
    $stmt = $pdo->prepare(
        "SELECT user_id, full_name, email, password_hash, role
         FROM users
         WHERE email = ?
         LIMIT 1"
    );

    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user["password_hash"])) {
        respond(401, false, "البريد الإلكتروني أو كلمة المرور غير صحيحة.");
    }

    session_start();
    session_regenerate_id(true);

    $_SESSION["user_id"] = $user["user_id"];
    $_SESSION["full_name"] = $user["full_name"];
    $_SESSION["role"] = $user["role"]; // [تعديل] حفظ الصلاحية

    echo json_encode([
        "success" => true,
        "message" => "تم تسجيل الدخول بنجاح.",
        "user" => [
            "id" => $user["user_id"],
            "name" => $user["full_name"],
            "role" => $user["role"] // [تعديل] إرجاع الصلاحية
        ]
    ], JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    respond(500, false, "حدث خطأ أثناء تسجيل الدخول.");
}