<?php
require_once __DIR__ . "/admin_init.php";
require_admin(2);

$q = trim((string)($_GET["q"] ?? ""));
$act = trim((string)($_GET["act"] ?? ""));
$id  = (int)($_GET["id"] ?? 0);

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    verify_csrf();
    $action = (string)($_POST["action"] ?? "");
    $uid = (int)($_POST["id"] ?? 0);

    if ($uid <= 0) redirect_to("admin_users_manage.php", ["q"=>$q]);

    if ($action === "toggle_active") {
        $stmt = $pdo->prepare("
          UPDATE `user_data`
          SET
            deactivate_at = IF(is_active=1, NOW(), NULL),
            is_active = IF(is_active=1,0,1)
          WHERE id=?
        ");
        $stmt->execute([$uid]);
        flash_set("msg", "已更新使用者啟用狀態");
    } elseif ($action === "set_level") {
        $lv = (int)($_POST["permission_level"] ?? 1);
        if ($lv < 1) $lv = 1;
        if ($lv > 2) $lv = 2;
        $stmt = $pdo->prepare("UPDATE `user_data` SET permission_level=? WHERE id=?");
        $stmt->execute([$lv, $uid]);
        flash_set("msg", "已更新使用者權限等級");
    } elseif ($action === "unlock_user") {
        // 解鎖:把登入剩餘次數重設為 5
        $stmt = $pdo->prepare("UPDATE `user_data` SET login_chance = 5 WHERE id=?");
        $stmt->execute([$uid]);
        flash_set("msg", "已解鎖使用者登入次數（重設為 5）");
    } else {
        flash_set("err", "未知操作");
    }

    redirect_to("admin_users_manage.php", ["q"=>$q]);
}

// list
$sql = "SELECT id, user_no, name, site, is_active, permission_level,
               COALESCE(login_chance, 5) AS login_chance
        FROM `user_data`
        WHERE 1=1";
$params = [];
if ($q !== "") {
    $sql .= " AND (user_no LIKE ? OR name LIKE ? OR site LIKE ?)";
    $like = "%{$q}%";
    $params = [$like,$like,$like];
}
$sql .= " ORDER BY id DESC LIMIT 100";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$msg = flash_get("msg");
$err = flash_get("err");

// 權限名稱轉譯
$levels = [
  1 => '一般用戶',
  2 => '管理者',
];
?>
<!doctype html>
<html lang="zh-Hant">
<head>
  <meta charset="utf-8">
  <title>使用者管理</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    body{
      font-family:system-ui,-apple-system,"Segoe UI",Roboto,"Noto Sans TC",Arial;
      background:#0b1220;
      color:#e5e7eb;
      margin:0;
    }

    .wrap{
      max-width:1100px;
      margin:0 auto;
      padding:22px;
    }

    .card{
      background:rgba(255,255,255,0.06);
      border:1px solid rgba(255,255,255,0.10);
      border-radius:16px;
      padding:18px;
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
      background:rgba(255,255,255,0.16);
    }
    
    input{
      padding:10px 12px;
      border-radius:12px;
      border:1px solid rgba(255,255,255,0.16);
      background:rgba(255,255,255,0.08);
      color:#e5e7eb;
    }

    select{
      padding:10px 12px;
      border-radius:12px;
      border:1px solid rgba(255,255,255,0.16);
      background:rgba(255,255,255,0.08);
      color:#6d6d6d;
    }

    table{
      width:100%;
      border-collapse:collapse;
      margin-top:12px;
      background:rgba(255,255,255,0.04);
      border-radius:12px;
      overflow:hidden;
    }

    th,td{
      padding:10px 10px;
      border-bottom:1px solid rgba(255,255,255,0.08);
      vertical-align:top;
    }
    th{
      font-size:12px;
      text-transform:uppercase;
      color:#cbd5e1;
      letter-spacing:0.06em;
      text-align:left;
    }

    .pill{
      display:inline-block;
      padding:4px 10px;
      border-radius:999px;
      border:1px solid rgba(255,255,255,0.16);
    }

    .ok{ 
      color:#34d399; 
    }
    .bad{ 
      color:#fb7185; 
    }
    .muted{
      color:#9ca3af; 
    }
    .msg{ 
      color:#34d399; 
      font-weight:700; 
    }
    .err{ 
      color:#fb7185; 
      font-weight:700; 
    }

    form.inline{ 
      display:inline; 
    }
  </style>
</head>
<body>
<div class="wrap">
  <div class="card">
    <div class="top">
      <div>
        <h1 style="margin:0 0 6px 0;">使用者管理</h1>
        <div class="muted">建立帳號、停用/啟用、調整權限、重設密碼</div>
      </div>
      <div style="display:flex; gap:10px; flex-wrap:wrap;">
        <a class="btn" href="admin_dashboard.php">回後台</a>
        <a class="btn" href="admin_user_create.php">＋建立帳號</a>
      </div>
    </div>

    <?php if ($msg): ?><p class="msg"><?= h($msg) ?></p><?php endif; ?>
    <?php if ($err): ?><p class="err"><?= h($err) ?></p><?php endif; ?>

    <form method="get" style="margin-top:10px; display:flex; gap:10px; flex-wrap:wrap;">
      <input type="text" name="q" value="<?= h($q) ?>" placeholder="搜尋：登錄帳號 / 姓名 / 地區">
      <button class="btn" type="submit">搜尋</button>
      <?php if ($q !== ""): ?><a class="btn" href="admin_users_manage.php">清除</a><?php endif; ?>
    </form>

    <table>
      <thead>
        <tr>
          <th>ID</th>
          <th>登錄帳號</th>
          <th>姓名</th>
          <th>地區</th>
          <th>帳號狀態</th>
          <th>權限</th>
          <th>密碼可試錯次數</th>
          <th>操作</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td><?= (int)$r["id"] ?></td>
          <td><?= h((string)$r["user_no"]) ?></td>
          <td><?= h((string)$r["name"]) ?></td>
          <td><?= h((string)$r["site"]) ?></td>
          <td>
            <?php if ((int)$r["is_active"] === 1): ?>
              <span class="pill ok">啟用</span>
            <?php else: ?>
              <span class="pill bad">停用</span>
            <?php endif; ?>
          </td>
          <td>
            <form class="inline" method="post">
              <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
              <input type="hidden" name="action" value="set_level">
              <input type="hidden" name="id" value="<?= (int)$r["id"] ?>">
              <select name="permission_level" onchange="this.form.submit()">
                <?php foreach ($levels as $lv => $label): ?>
                  <option value="<?= $lv ?>"
                    <?= ((int)$r['permission_level'] === $lv ? 'selected' : '') ?>>
                    <?= htmlspecialchars($label) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </form>
          </td>
          <td><?= (int)$r["login_chance"] ?></td>
          <td style="white-space:nowrap;">
              <a class="btn" href="admin_user_edit.php?id=<?= (int)$r["id"] ?>">編輯使用者</a>

              <form class="inline" method="post" onsubmit="return confirm('確定要切換啟用狀態？');">
                <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
                <input type="hidden" name="action" value="toggle_active">
                <input type="hidden" name="id" value="<?= (int)$r["id"] ?>">
                <button class="btn" type="submit">啟用/停用</button>
              </form>

              <!-- 解鎖:只在已鎖定時顯示 -->
              <?php if ((int)$r["login_chance"] <= 0): ?>
                <form class="inline" method="post" onsubmit="return confirm('確定要解鎖此使用者的登入次數？(重設為 5)');">
                  <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
                  <input type="hidden" name="action" value="unlock_user">
                  <input type="hidden" name="id" value="<?= (int)$r["id"] ?>">
                  <button class="btn" type="submit">解鎖</button>
                </form>
              <?php endif; ?>

          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>

    <p class="muted" style="margin-top:10px;">顯示上限 100 筆；建議用搜尋縮小範圍。</p>
  </div>
</div>
</body>
</html>
