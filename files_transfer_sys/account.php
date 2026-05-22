<?php
require_once __DIR__ . "/init.php";
require_login();

$msg = $_GET["msg"] ?? "";
$error = $_GET["error"] ?? "";

$userSession = $_SESSION["user"];
$userNo = $userSession["user_no"];

// 讀取最新的使用者資料
$stmt = $pdo->prepare("SELECT id, user_no, password_hash FROM `user_data` WHERE user_no = ? LIMIT 1");
$stmt->execute([$userNo]);
$user = $stmt->fetch();

if (!$user) {
  header("Location: logout.php");
  exit;
}
?>
<!doctype html>
<html lang="zh-Hant">
<head>
  <meta charset="utf-8">
  <title>修改密碼</title>

  <style>
  body {
    margin: 0;
    font-family: Arial, "Microsoft JhengHei", sans-serif;
  }

  body::before {
    content: "";
    position: fixed;
    inset: 0;
    background-size: 80% auto;
    filter: blur(6px) brightness(0.9);
    transform: scale(1.05);
    z-index: -2;
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
    margin: 24px;
    padding: 14px 22px;
    background: rgba(0, 0, 0, 0.45);
    color: #fff;
    border-radius: 12px;
    width: fit-content;
  }
  .page-title img { height: 42px; }

  .box {
    margin: 24px;
    padding: 20px;
    background: rgba(255, 255, 255, 0.94);
    border-radius: 12px;
    box-shadow: 0 12px 32px rgba(0,0,0,0.25);
  }
  .header-box { padding-top: 14px; }

  /* 膠囊按鈕（與 dashboard 登出一致） */
  .nav-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    flex-wrap: wrap;
  }
  .nav-left{
    display:flex;
    align-items:center;
    gap:10px;
    flex-wrap:wrap;
  }
  .pill-btn{
    display: inline-block;
    padding: 6px 14px;
    border-radius: 999px;
    text-decoration: none;
    font-size: 14px;
    font-weight: 600;
    background: rgba(15, 23, 42, 0.92);
    color: #fff;
    border: 1px solid rgba(255,255,255,0.25);
    transition: filter .12s ease, transform .05s ease;
  }
  .pill-btn:hover{ 
    filter: brightness(1.05); 
    transform: translateY(-1px); 
  }

  .pill-btn:active{ 
    transform: translateY(0); 
    filter: brightness(0.98); 
  }

  .pill-btn.secondary{
    background: rgba(255,255,255,0.85);
    color:#0f172a;
    border: 1px solid rgba(15,23,42,0.15);
  }

  .main-box { 
    margin: 24px;
    padding: 20px;
    background: rgba(255, 255, 255, 0.94);
    border-radius: 12px;
    box-shadow: 0 12px 32px rgba(0,0,0,0.25);
  }

  label { 
    font-weight: 700; 
  }

  input[type="password"]{
    width: min(420px, 100%);
    margin-top: 6px;
    padding: 10px 12px;
    border-radius: 10px;
    border: 1px solid rgba(15,23,42,0.18);
    outline: none;
  }

  .btn-primary{
    border: 0;
    border-radius: 10px;
    padding: 10px 14px;
    cursor: pointer;
    background: rgba(15, 23, 42, 0.92);
    color: #fff;
    font-weight: 700;
  }
  .btn-primary:hover{ filter: brightness(1.05); }

  .msg { 
    color: #0b7a2a; 
    font-weight: 700; 
    margin: 0; 
  }

  .err { 
    color: #b00020; 
    font-weight: 700; 
    margin: 0; 
  }

  .hint { 
    color: rgba(15,23,42,0.7); 
    font-size: 12px; 
  }
  </style>
</head>

<body>
  <h1 class="page-title">
    <span>修改密碼</span>
  </h1>

  <!-- header-box：功能列（跟 files_transfer_sys 一樣在主體上方） -->
  <div class="box header-box">
    <div class="nav-bar">
      <div class="nav-left">
        <a class="pill-btn secondary" href="dashboard.php">回首頁</a>
      </div>
      <div class="nav-left">
        <span class="hint">登入者：<?= htmlspecialchars($userSession["name"] ?? "") ?>（<?= htmlspecialchars($userNo) ?>）</span>
      </div>
    </div>
  </div>

  <!-- main-box：表單白框 -->
  <div class="box main-box">
    <?php if ($msg): ?>
      <p class="msg"><?= htmlspecialchars($msg) ?></p>
      <br>
    <?php endif; ?>
    <?php if ($error): ?>
      <p class="err"><?= htmlspecialchars($error) ?></p>
      <br>
    <?php endif; ?>

    <form method="post" action="change_password.php">
      <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
      <div>
        <label>舊密碼：</label><br>
        <input type="password" name="old_password" required>
      </div>
      <br>

      <div>
        <label>新密碼(至少 4 碼)：</label><br>
        <input type="password" name="new_password" required>
      </div>
      <br>

      <div>
        <label>確認新密碼：</label><br>
        <input type="password" name="new_password2" required>
      </div>
      <br>

      <button class="btn-primary" type="submit">更新密碼</button>
    </form>
  </div>
</body>
</html>