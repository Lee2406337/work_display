<?php
header("Access-Control-Allow-Origin: http://localhost:5173");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Content-Type: application/json; charset=utf-8");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    exit;
}

require_once __DIR__ . "/init.php";

function json_out(array $data): void {
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

if (!isset($_SESSION["user"])) {
    json_out([
        "ok" => false,
        "message" => "尚未登入"
    ]);
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    json_out([
        "ok" => false,
        "message" => "請使用 POST"
    ]);
}

$data = json_decode(file_get_contents("php://input"), true);

if (!is_array($data)) {
    json_out([
        "ok" => false,
        "message" => "資料格式錯誤"
    ]);
}

$userNo = (string)($_SESSION["user"]["user_no"] ?? "");

$old = (string)($data["old_password"] ?? "");
$new = (string)($data["new_password"] ?? "");
$new2 = (string)($data["new_password2"] ?? "");

if ($old === "" || $new === "" || $new2 === "") {
    json_out([
        "ok" => false,
        "message" => "密碼不可為空"
    ]);
}

if ($new !== $new2) {
    json_out([
        "ok" => false,
        "message" => "兩次輸入的新密碼不一致"
    ]);
}

if ($new === $userNo) {
    json_out([
        "ok" => false,
        "message" => "新密碼不可與帳號相同"
    ]);
}

if (strlen($new) < 4) {
    json_out([
        "ok" => false,
        "message" => "新密碼至少需要 4 碼"
    ]);
}

$stmt = $pdo->prepare("
    SELECT id, password_hash
    FROM user_data
    WHERE user_no = ?
    LIMIT 1
");

$stmt->execute([$userNo]);

$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    session_destroy();

    json_out([
        "ok" => false,
        "message" => "找不到使用者，請重新登入"
    ]);
}

if (!password_verify($old, (string)$user["password_hash"])) {
    json_out([
        "ok" => false,
        "message" => "舊密碼錯誤"
    ]);
}

if (password_verify($new, (string)$user["password_hash"])) {
    json_out([
        "ok" => false,
        "message" => "新密碼不可與舊密碼重複"
    ]);
}

$newHash = password_hash($new, PASSWORD_DEFAULT);

$upd = $pdo->prepare("
    UPDATE user_data
    SET password_hash = ?
    WHERE id = ?
");

$upd->execute([
    $newHash,
    (int)$user["id"]
]);

json_out([
    "ok" => true,
    "message" => "密碼已更新"
]);