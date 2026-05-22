<?php
require_once __DIR__ . "/init.php";
require_login();

$userNo = $_SESSION["user"]["user_no"];

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: account.php");
    exit;
}
verify_csrf();

$old = (string)($_POST["old_password"] ?? "");
$new = (string)($_POST["new_password"] ?? "");
$new2 = (string)($_POST["new_password2"] ?? "");

if ($old === "" || $new === "" || $new2 === "") {
    header("Location: account.php?error=" . urlencode("密碼不可為空"));
    exit;
}

if ($new !== $new2) {
    header("Location: account.php?error=" . urlencode("兩次輸入的新密碼不一致"));
    exit;
}

if ($new === $userNo) {
    header("Location: account.php?error=" . urlencode("新密碼不可與帳號相同"));
    exit;
}

// 新密碼建議至少 4 碼
if (strlen($new) < 4) {
    header("Location: account.php?error=" . urlencode("新密碼至少需要 4 碼"));
    exit;
}

// 取出使用者 hash
$stmt = $pdo->prepare("SELECT id, password_hash FROM `user_data` WHERE user_no = ? LIMIT 1");
$stmt->execute([$userNo]);
$user = $stmt->fetch();

if (!$user) {
    header("Location: logout.php");
    exit;
}

// 驗證舊密碼
if (!password_verify($old, (string)$user["password_hash"])) {
    header("Location: account.php?error=" . urlencode("舊密碼錯誤"));
    exit;
}

// 新密碼不可與舊密碼重複
if (password_verify($new, (string)$user["password_hash"])) {
    header("Location: account.php?error=" . urlencode("新密碼不可與舊密碼重複"));
    exit;
}

// 更新成新 hash
$newHash = password_hash($new, PASSWORD_DEFAULT);

$upd = $pdo->prepare("UPDATE `user_data` SET password_hash = ? WHERE id = ?");
$upd->execute([$newHash, $user["id"]]);

header("Location: account.php?msg=" . urlencode("密碼已更新"));
exit;
