<?php
session_start();

// 清空 session 內容
$_SESSION = [];
session_unset();

// 刪掉 session cookie
if (ini_get("session.use_cookies")) {
  $params = session_get_cookie_params();
  setcookie(
    session_name(),
    '',
    time() - 42000,
    $params['path'],
    $params['domain'],
    $params['secure'],
    $params['httponly']
  );
}

// 銷毀 session
session_destroy();
?>

<!doctype html>
<html lang="zh-Hant">
<head>
  <meta charset="utf-8">
  <title>登出</title>
  <meta http-equiv="refresh" content="1;url=login.php">
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
  margin: 24px;
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
  margin: 24px;
  padding: 20px;
  background: rgba(255, 255, 255, 0.94);
  border-radius: 12px;
  box-shadow: 0 12px 32px rgba(0,0,0,0.25);
}
  </style>
</head>
<body>
  <h1 class="page-title">
    <span>已登出</span>
  </h1>

  <div class="box">
    <p>你已成功登出，將自動跳轉回登入頁。</p>
    <p><a href="login.php">若沒有跳轉，請點我回登入頁</a></p>
  </div>
</body>
</html>