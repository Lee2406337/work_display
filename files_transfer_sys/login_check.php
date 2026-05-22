<?php
declare(strict_types=1);
require_once __DIR__ . "/init.php";


if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: login.php");
    exit;
}

$userNo = trim((string)($_POST["user_no"] ?? ""));
$password   = (string)($_POST["password"] ?? "");

$_SESSION["login_old_user_no"] = $userNo;

if ($userNo === "" || $password === "") {
    $_SESSION["login_error"] = "請輸入登錄帳號與密碼";
    header("Location: login.php");
    exit;
}

verify_csrf();

try {
    // 取用戶資料（NULL 視為 5）
    $stmt = $pdo->prepare("
        SELECT id, user_no, password_hash, name, site, is_active, permission_level,
               COALESCE(login_chance, 5) AS login_chance
        FROM user_data
        WHERE user_no = ?
        LIMIT 1
    ");
    $stmt->execute([$userNo]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // 帳號不存在：統一訊息（防枚舉）
    if (!$user) {
        $_SESSION["login_error"] = "帳號或密碼錯誤";
        header("Location: login.php");
        exit;
    }

    // 停用帳號
    if ((int)$user["is_active"] !== 1) {
        $_SESSION["login_error"] = "帳號已停用，請聯絡管理員";
        header("Location: login.php");
        exit;
    }

    // 已鎖定（<=0）
    if ((int)$user["login_chance"] <= 0) {
        $_SESSION["login_error"] = "帳號已被鎖定，請聯絡管理員";
        header("Location: login.php");
        exit;
    }

    // 密碼錯：先扣 1（保證執行），再依剩餘次數回訊息
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

        // 再查一次剩餘次數，決定回覆
        $stmt2 = $pdo->prepare("SELECT login_chance FROM user_data WHERE id=? LIMIT 1");
        $stmt2->execute([(int)$user["id"]]);
        $left = (int)($stmt2->fetchColumn() ?? 0);

        $_SESSION["login_error"] = ($left <= 0)
            ? "帳號已被鎖定，請聯絡管理員"
            : "帳號或密碼錯誤";

        header("Location: login.php");
        exit;
    }

    // 密碼正確：登入成功
    session_regenerate_id(true);

    // 成功重設回 5
    $pdo->prepare("UPDATE user_data SET login_chance = 5 WHERE id=?")
        ->execute([(int)$user["id"]]);

    $_SESSION["user"] = [
        "id"               => (int)$user["id"],
        "user_no"      => $user["user_no"],
        "name"             => $user["name"],
        "site"             => $user["site"],
        "permission_level" => (int)$user["permission_level"],
    ];

    unset($_SESSION["login_error"], $_SESSION["login_old_user_no"]);

    header("Location: dashboard.php");
    exit;

} catch (Throwable $e) {
    $_SESSION["login_error"] = "系統忙碌中，請稍後再試";
    header("Location: login.php");
    exit;
}