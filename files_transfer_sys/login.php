<?php
require_once __DIR__ . "/init.php";

$sessionError = $_SESSION["login_error"] ?? "";
$oldNo        = $_SESSION["login_old_user_no"] ?? "";

// 用完就清掉，避免重新整理還一直顯示
unset($_SESSION["login_error"]);

$getError = (string)($_GET["error"] ?? "");

// 最終顯示的錯誤，session 優先
$error = $sessionError !== "" ? $sessionError : $getError;
?>
<!doctype html>
<!-- 哥們先冷靜 -->
<html lang="zh-Hant">
<head>
  <meta charset="utf-8">
  <title>文件往來紀錄系統登入</title>

<style>
body {
  margin: 0;
  font-family: Arial, "Microsoft JhengHei", sans-serif;
}

body::after {
  content: "";
  position: fixed;
  inset: 0;
  background: rgba(15, 23, 42, 0.55);
  z-index: -1;
}

.page-title {
  display: flex;
  align-items: center;
  gap: 14px;
  margin: 40px auto 24px;
  padding: 14px 22px;
  background: rgba(0, 0, 0, 0.45);
  color: #fff;
  border-radius: 12px;
  width: fit-content;
}

.page-title img { 
  height: 42px; 
}

.box {
  max-width: 420px;
  margin: 0 auto;
  padding: 24px;
  background: rgba(255, 255, 255, 0.94);
  border-radius: 14px;
  box-shadow: 0 12px 32px rgba(0,0,0,0.25);
}

label { 
  font-weight: 600; 
}

input[type="text"], input[type="password"]{
  width: 95%;
  margin-top: 6px;
  margin-bottom: 14px;
  padding: 10px;
  border-radius: 10px;
  border: 1px solid rgba(15,23,42,0.2);
}

button{
  width: 100%;
  padding: 12px;
  border-radius: 10px;
  border: 0;
  background: rgba(15, 23, 42, 0.92);
  color: #fff;
  font-size: 16px;
  cursor: pointer;
}
button:hover{ filter: brightness(1.05); }

.err{
  color:#b00020;
  font-weight:600;
  margin: 0 0 14px 0;
  padding: 10px 12px;
  border-radius: 10px;
  background: rgba(176, 0, 32, 0.08);
  border: 1px solid rgba(176, 0, 32, 0.25);
}

.pwd-label{
  display: flex;
  align-items: center;
  gap: 8px;
}

.caps-warning{
  font-size: 10px;
  color: #c22400ff;
  background: rgba(176, 0, 32, 0.08);
  border: 1px solid rgba(255, 0, 0, 0.25);
  padding: 2px 6px;
  border-radius: 6px;
  line-height: 1;
}
</style>

</head>
<body>
  <h1 class="page-title"><span>文件往來系統登入</span></h1>
  <div class="box">
    <form method="post" action="login_check.php">
      <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
      <?php if ($error): ?>
        <div class="err"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <div>
        <label>帳號：</label>
        <input type="text" name="user_no" value="<?= htmlspecialchars($oldNo) ?>" required>
      </div>

      <div class="password-box">
        <label class="pwd-label">
          密碼：
          <span id="caps-warning" class="caps-warning" style="display:none;">
            Caps Lock 已開啟
          </span>
        </label>
        <input type="password" name="password" id="password" required>
        </div>

      <button type="submit">登入</button>
    </form>
  </div>

  <script>
  (function () {
    const pwd = document.getElementById('password');
    const warning = document.getElementById('caps-warning');
    if (!pwd || !warning) return;

    function checkCaps(e) {
      // getModifierState 判斷 Caps Lock 狀態
      const caps = e.getModifierState && e.getModifierState('CapsLock');
      warning.style.display = caps ? 'block' : 'none';
    }

    pwd.addEventListener('keydown', checkCaps);
    pwd.addEventListener('keyup', checkCaps);
    pwd.addEventListener('focus', function (e) {
      checkCaps(e);
    });
  })();
  </script>
</body>
</html>