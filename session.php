<?php
header("Content-Type: application/json; charset=utf-8");
header("Cache-Control: no-store");

session_start();

if (!isset($_SESSION["user_id"])) {
    http_response_code(401);

    echo json_encode([
        "authenticated" => false,
        "message" => "يجب تسجيل الدخول أولاً."
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

echo json_encode([
    "authenticated" => true,
    "user" => [
        "id" => $_SESSION["user_id"],
        "name" => $_SESSION["full_name"] ?? "",
        "role" => $_SESSION["role"] ?? "user" // [تعديل] إرجاع الصلاحية
    ]
], JSON_UNESCAPED_UNICODE);
?>