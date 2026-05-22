<?php
declare(strict_types=1);

require_once __DIR__ . "/admin_init.php";

if (isset($_SESSION["user"]) && (int)($_SESSION["user"]["permission_level"] ?? 1) >= 1) {
    $next = (string)($_GET["next"] ?? "admin_dashboard.php");

    if (
        $next === "" ||
        str_starts_with($next, "http://") ||
        str_starts_with($next, "https://") ||
        str_starts_with($next, "//") ||
        str_contains($next, "\n") ||
        str_contains($next, "\r") ||
        str_contains($next, "..")
    ) {
        $next = "admin_dashboard.php";
    }

    header("Location: " . $next);
    exit;
}

$next  = (string)($_GET["next"] ?? "admin_dashboard.php");
$error = (string)($_GET["error"] ?? "");

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    verify_csrf();

    $userNo = trim((string)($_POST["user_no"] ?? ""));
    $password   = (string)($_POST["password"] ?? "");
    $nextPost   = (string)($_POST["next"] ?? "");

    if ($userNo === "" || $password === "") {
        $error = "請輸入登錄帳號與密碼";
    } else {
        $stmt = $pdo->prepare("
            SELECT id, user_no, password_hash, name, site, is_active, permission_level,
                   COALESCE(login_chance, 5) AS login_chance
            FROM user_data
            WHERE user_no = ?
            LIMIT 1
        ");
        $stmt->execute([$userNo]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user || (int)$user["is_active"] !== 1) {
            $error = "帳號或密碼錯誤";
        } elseif ((int)$user["login_chance"] <= 0) {
            $error = "帳號已被鎖定，請聯絡管理員";
        } elseif (!password_verify($password, (string)$user["password_hash"])) {

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

            $stmt2 = $pdo->prepare("SELECT COALESCE(login_chance,0) FROM user_data WHERE id=? LIMIT 1");
            $stmt2->execute([(int)$user["id"]]);
            $left = (int)($stmt2->fetchColumn() ?? 0);

            $error = ($left <= 0) ? "帳號已被鎖定，請聯絡管理員" : "帳號或密碼錯誤";

        } else {
            session_regenerate_id(true);

            $pdo->prepare("UPDATE user_data SET login_chance = 5 WHERE id=?")
                ->execute([(int)$user["id"]]);

            $_SESSION["user"] = [
                "id"               => (int)$user["id"],
                "user_no"      => $user["user_no"],
                "name"             => $user["name"],
                "site"             => $user["site"],
                "permission_level" => (int)$user["permission_level"],
            ];

            $safeNext = $nextPost;
            if (
                $safeNext === "" ||
                str_starts_with($safeNext, "http://") ||
                str_starts_with($safeNext, "https://") ||
                str_starts_with($safeNext, "//") ||
                str_contains($safeNext, "\n") ||
                str_contains($safeNext, "\r") ||
                str_contains($safeNext, "..") ||
                !str_starts_with($safeNext, "admin_")
            ) {
                $safeNext = "admin_dashboard.php";
            }

            header("Location: " . $safeNext);
            exit;
        }
    }
}
?>
<!doctype html>
<html lang="zh-Hant">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>後台登入</title>
  <style>
    body{
        font-family:system-ui,-apple-system,Segoe UI,Roboto,"Noto Sans TC",sans-serif;
        background:#f5f6f8;
        margin:0
    }
    .wrap{
        max-width:420px;
        margin:60px auto;
        background:#fff;
        border-radius:14px;
        box-shadow:0 6px 24px rgba(0,0,0,.08);
        padding:22px
    }
    h1{
        font-size:20px;
        margin:0 0 16px
    }
    label{
        display:block;
        margin:12px 0 6px;
        color:#333;
        font-size:14px
    }
    input{
        width:95%;
        margin-top:6px;
        margin-bottom:14px;
        padding:10px;
        border-radius:10px;
        border:1px solid rgba(15,23,42,0.2)
    }
    button{
        width:100%;
        margin-top:16px;
        padding:10px 12px;
        border:0;
        border-radius:10px;
        background:#111;
        color:#fff;
        font-size:14px;
        cursor:pointer
    }
    .err{
        background:#fff2f2;
        border:1px solid #ffd2d2;
        color:#b00020;
        border-radius:10px;
        padding:10px 12px;
        margin:10px 0
    }
  </style>
</head>
<body>
  <div class="wrap">
    <h1>後台登入</h1>
    <?php if ($error !== ""): ?>
      <div class="err"><?= h($error) ?></div>
    <?php endif; ?>
    <form method="post">
      <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
      <input type="hidden" name="next" value="<?= h($next) ?>">
      <label>登錄帳號</label>
      <input name="user_no" autocomplete="username" required>
      <label>密碼</label>
      <input name="password" type="password" autocomplete="current-password" required>
      <button type="submit">登入</button>
    </form>
  </div>
</body>
</html>
