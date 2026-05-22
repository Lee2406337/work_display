<?php
require_once __DIR__ . "/init.php";
require_login();

$user = $_SESSION["user"];
?>

<!doctype html>
<!-- 哥們別摸魚 -->
<html lang="zh-Hant">
<head>
<meta charset="utf-8">
<title>Dashboard</title>
  
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

.menu {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
  gap: 14px;
  margin-top: 14px;
}
.menu a.btn {
  display: flex;
  align-items: center;
  justify-content: center;
  text-decoration: none;
  padding: 25px 16px;
  border-radius: 12px;
  background: rgba(15, 23, 42, 0.92);
  color: #fff;
  font-weight: 700;
  letter-spacing: .5px;
  box-shadow: 0 10px 22px rgba(0,0,0,0.18);
  transition: transform .05s ease-in-out, filter .1s ease-in-out;
  font-size: 25px;
}
.menu a.btn:hover { 
  transform: translateY(-1px); 
  filter: brightness(1.05); 
}
.menu a.btn:active { 
  transform: translateY(0px); 
  filter: brightness(0.98); 
}

.user-bar{
  display:flex;
  align-items:center;
  gap:12px;
}
.user-bar .user-btn{
  padding:6px 12px;
  border-radius:999px;
  text-decoration:none;
  background: rgba(255,255,255,0.85);
  color:#0f172a;
  border:1px solid rgba(15,23,42,0.18);
  font-size:14px;
  font-weight:600;
}
.user-bar .user-btn:hover{
  filter:brightness(0.95);
}
</style>
</head>

<body>

<h1 class="page-title">
  <span>文件往來系統</span>
</h1>

<div class="box">
  <div class="user-bar"><p>歡迎登入：<?= htmlspecialchars($user["user_no"]) ?> <?= htmlspecialchars($user["name"]) ?></p>
  <a class="user-btn" href="logout.php">登出</a><a class="user-btn" href="account.php">修改密碼</a><a class="user-btn" href="cancel_file.php">我想抽單</a></div> 

  <div class="menu">
  <a class="btn" href="files_transfer_sys.php">文件往來登記</a>
  <a class="btn" href="files_list.php">查看登記紀錄</a>
  <a class="btn" href="users_list.php">使用者資料查詢</a>
  
</div>
</div>
</body>
</html>