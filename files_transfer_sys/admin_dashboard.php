<?php
require_once __DIR__ . "/admin_init.php";
require_admin(2);

$user = $_SESSION["user"];
$msg = flash_get("msg");
$err = flash_get("err");
?>
<!doctype html>
<html lang="zh-Hant">
<head>
  <meta charset="utf-8">
  <title>後台管理</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    body{
      font-family:system-ui,-apple-system,"Segoe UI",Roboto,"Noto Sans TC",Arial;
      background:#0b1220; 
      color:#e5e7eb; 
      margin:0;
    }
    .wrap{
      max-width:980px; 
      margin:0 auto; 
      padding:22px;
    }

    .topbar{
      display:flex;
      justify-content:space-between;
      align-items:center;
      padding:14px 22px;
      background:rgba(255,255,255,0.06);
      border-bottom:1px solid rgba(255,255,255,0.10);
    }

    .right{
      display:flex;
      gap:12px;
      align-items:center;
    }

    .muted{
      color:#9ca3af;
      font-size:13px;
    }

    .link{
      color:#e5e7eb;
      text-decoration:none;
      border:1px solid rgba(255,255,255,0.18);
      padding:6px 10px;
      border-radius:10px;
    }

    .link:hover{
      background:rgba(255,255,255,0.08);
    }

    .card{
      background:rgba(255,255,255,0.06); 
      border:1px solid rgba(255,255,255,0.10); 
      border-radius:16px; padding:18px; 
      box-shadow:0 12px 30px rgba(0,0,0,0.35);
    }

    .top{
      display:flex; 
      justify-content:space-between; 
      align-items:center; 
      gap:12px; 
      flex-wrap:wrap;
    }

    .btn{
      display:inline-block; 
      padding:10px 14px; 
      border-radius:12px;
      background:rgba(255,255,255,0.10); 
      border:1px solid rgba(255,255,255,0.16); 
      color:#e5e7eb; 
      text-decoration:none;
    }

    .btn:hover{
      background:rgba(255,255,255,0.16);}
    .grid{
      display:grid; 
      grid-template-columns:repeat(2, minmax(0,1fr)); 
      gap:14px; 
      margin-top:14px;
    }

    @media (max-width:720px){
      .grid{grid-template-columns:1fr;} }
    .msg{
      color:#34d399; 
      font-weight:700;
    }

    .err{
      color:#fb7185; 
      font-weight:700;
    }

    .muted{
      color:#9ca3af;
    }
  </style>
</head>
<body>
  <div class="topbar">
    <div>後台管理</div>
    <div class="right">
      <span class="muted">登入：<?= h($user["user_no"] . " " . $user["name"]) ?></span>
      <a class="link" href="admin_logout.php">登出</a>
    </div>
  </div>
  <div class="wrap">
    <div class="card">
      <div class="top">
        <div>
          <h1 style="margin:0 0 6px 0;">後台管理</h1>
          <div class="muted">登入者：<?= h($user["name"] ?? "") ?>（登錄帳號 <?= h($user["user_no"] ?? "") ?>）</div>
        </div>
        <div style="display:flex; gap:10px; flex-wrap:wrap;">
          <a class="btn" href="dashboard.php">回主選單</a>
          <a class="btn" href="admin_logout.php">登出</a>
        </div>
      </div>

      <?php if ($msg): ?><p class="msg"><?= h($msg) ?></p><?php endif; ?>
      <?php if ($err): ?><p class="err"><?= h($err) ?></p><?php endif; ?>

      <div class="grid">
        <a class="btn" href="admin_files_manage.php" style="padding:16px;">
          <div style="font-size:18px; font-weight:800; margin-bottom:6px;">📄 文件資料管理</div>
          <div class="muted">查詢 / 編輯 / 取消 / 刪除 / 新增</div>
        </a>
        <a class="btn" href="admin_users_manage.php" style="padding:16px;">
          <div style="font-size:18px; font-weight:800; margin-bottom:6px;">👤 使用者管理</div>
          <div class="muted">建立帳號 / 停用啟用 / 重設密碼 / 解鎖帳號</div>
        </a>
        <a class="btn" href="admin_report.php" style="padding:16px;">
          <div style="font-size:18px; font-weight:800; margin-bottom:6px;">📊 資料匯檔案出</div>
          <div class="muted">匯出 ecxel / CSV / print</div>
        </a>
      </div>
      </p>
    </div>
  </div>
</body>
</html>