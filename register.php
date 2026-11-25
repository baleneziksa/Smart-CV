
<?php
header("Content-Type: application/json; charset=utf-8");

require_once __DIR__ . "/db.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo json_encode(["success" => false, "message" => "طريقة الطلب غير مسموحة"]);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);

$name = trim($data["full_name"] ?? "");
$email = trim($data["email"] ?? "");
$password = $data["password"] ?? "";

if ($name === "" || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 8) {
    http_response_code(422);
    echo json_encode([
        "success" => false,
        "message" => "تأكد من الاسم والبريد وكلمة المرور (8 أحرف على الأقل)."
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $stmt = $pdo->prepare(
        "INSERT INTO users (full_name, email, password_hash)
         VALUES (?, ?, ?)"
    );

    $stmt->execute([
        $name,
        $email,
        password_hash($password, PASSWORD_DEFAULT)
    ]);

    echo json_encode([
        "success" => true,
        "message" => "تم إنشاء الحساب بنجاح"
    ], JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    if ($e->getCode() === "23000") {
        http_response_code(409);
        $message = "البريد الإلكتروني مستخدم مسبقًا.";
    } else {
        http_response_code(500);
        $message = "حدث خطأ أثناء إنشاء الحساب.";
    }

    echo json_encode([
        "success" => false,
        "message" => $message
    ], JSON_UNESCAPED_UNICODE);
}
