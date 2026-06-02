<?php
declare(strict_types=1);

header("Access-Control-Allow-Origin: http://localhost:5173");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Content-Type: application/json; charset=utf-8");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    exit;
}

require_once __DIR__ . "/init.php";

$data = json_decode(file_get_contents("php://input"), true);

$userNo = trim((string)($data["user_no"] ?? ""));
$password = (string)($data["password"] ?? "");

if ($userNo === "" || $password === "") {
    echo json_encode([
        "ok" => false,
        "message" => "請輸入登錄帳號與密碼"
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

try {

    $stmt = $pdo->prepare("
        SELECT id, user_no, password_hash, name, site,
               is_active, permission_level,
               COALESCE(login_chance, 5) AS login_chance
        FROM user_data
        WHERE user_no = ?
        LIMIT 1
    ");

    $stmt->execute([$userNo]);

    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        echo json_encode([
            "ok" => false,
            "message" => "帳號或密碼錯誤"
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ((int)$user["is_active"] !== 1) {
        echo json_encode([
            "ok" => false,
            "message" => "帳號已停用，請聯絡管理員"
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ((int)$user["login_chance"] <= 0) {
        echo json_encode([
            "ok" => false,
            "message" => "帳號已被鎖定，請聯絡管理員"
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if (!password_verify($password, (string)$user["password_hash"])) {

        $pdo->prepare("
            UPDATE user_data
            SET login_chance =
                CASE
                    WHEN login_chance IS NULL THEN 4
                    WHEN login_chance > 0 THEN login_chance - 1
                    ELSE 0
                END
            WHERE id = ?
        ")->execute([(int)$user["id"]]);

        $stmt2 = $pdo->prepare("
            SELECT login_chance
            FROM user_data
            WHERE id=?
            LIMIT 1
        ");

        $stmt2->execute([(int)$user["id"]]);

        $left = (int)($stmt2->fetchColumn() ?? 0);

        echo json_encode([
            "ok" => false,
            "message" => ($left <= 0)
                ? "帳號已被鎖定，請聯絡管理員"
                : "帳號或密碼錯誤"
        ], JSON_UNESCAPED_UNICODE);

        exit;
    }

    session_regenerate_id(true);

    $pdo->prepare("
        UPDATE user_data
        SET login_chance = 5
        WHERE id=?
    ")->execute([(int)$user["id"]]);

    $_SESSION["user"] = [
        "id"               => (int)$user["id"],
        "user_no"          => $user["user_no"],
        "name"             => $user["name"],
        "site"             => $user["site"],
        "permission_level" => (int)$user["permission_level"],
    ];

    echo json_encode([
        "ok" => true,
        "message" => "登入成功",
        "user" => $_SESSION["user"]
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {

    echo json_encode([
        "ok" => false,
        "message" => "系統忙碌中，請稍後再試"
    ], JSON_UNESCAPED_UNICODE);
}