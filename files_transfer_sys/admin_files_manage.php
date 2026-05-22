<?php
require_once __DIR__ . "/admin_init.php";
require_admin(2);

$q = trim((string)($_GET["q"] ?? ""));

// 狀態顯示對照（與 files_list 一致）
$statusDisplayMap = [
  "SENT"           => "進行中",
  "PICKED"         => "進行中",
  "RECEIVED"       => "完成",
  "PROXY_RECEIVED" => "代收中",
  "COMPLETED"      => "完成",
  "CANCELED"       => "取消",
  "ERROR"          => "錯誤",
];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
  verify_csrf();
  $action = (string)($_POST["action"] ?? "");
  $id = (int)($_POST["id"] ?? 0);
  if ($id <= 0) redirect_to("admin_files_manage.php");

  if ($action === "cancel") {
    $stmt = $pdo->prepare("UPDATE `files_transfer` SET status='CANCELED' WHERE id=?");
    $stmt->execute([$id]);
    flash_set("msg","已將文件狀態設為取消");
  } elseif ($action === "delete") {
    $stmt = $pdo->prepare("DELETE FROM `files_transfer` WHERE id=?");
    $stmt->execute([$id]);
    flash_set("msg","已刪除文件資料");
  } else {
    flash_set("err","未知操作");
  }
  redirect_to("admin_files_manage.php", ["q"=>$q]);
}

// 查詢
$sql = "SELECT id, sender_user_no, sender_name, sender_site,
               doc_name, doc_type, doc_type_other,
               intended_receiver_user_no, intended_receiver_name, dest_site,
               send_time, status
        FROM `files_transfer`
        WHERE 1=1";
$params = [];

if ($q !== "") {
  $sql .= " AND (
    sender_user_no LIKE ? OR sender_name LIKE ? OR sender_site LIKE ?
    OR doc_name LIKE ? OR intended_receiver_user_no LIKE ? OR intended_receiver_name LIKE ?
    OR dest_site LIKE ?
  )";
  $like = "%{$q}%";
  $params = [$like,$like,$like,$like,$like,$like,$like];
}

$sql .= " ORDER BY id DESC LIMIT 100";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$msg = flash_get("msg");
$err = flash_get("err");
?>
<!doctype html>
<html lang="zh-Hant">
<head>
  <meta charset="utf-8">
  <title>文件資料管理</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    body{
      font-family:system-ui,-apple-system,"Segoe UI",Roboto,"Noto Sans TC",Arial;
      background:#0b1220;
      color:#e5e7eb;
      margin:0;
    }

    .wrap{
      max-width:1200px;
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

    table{
      width:100%;
      border-collapse:collapse;
      margin-top:12px;
      background:rgba(255,255,255,0.04);
      border-radius:12px;
      overflow:hidden;
    }

    th,td{
      padding:10px;
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
    form.inline{display:inline;}
    .pill{
      display:inline-block;
      padding:4px 10px;
      border-radius:999px;
      border:1px solid rgba(255,255,255,0.16);
    }
    .actions{
      display:flex;
      gap:10px;
      flex-wrap:wrap;
    }
  </style>
</head>
<body>
<div class="wrap">
  <div class="card">
    <div class="top">
      <h1 style="margin:0;">文件資料管理</h1>
      <div class="actions">
        <a class="btn" href="admin_dashboard.php">回後台</a>
        <a class="btn" href="admin_files_add.php">新增文件資料</a>
      </div>
    </div>

    <?php if ($msg): ?><p class="msg"><?= h($msg) ?></p><?php endif; ?>
    <?php if ($err): ?><p class="err"><?= h($err) ?></p><?php endif; ?>

    <form method="get" style="margin-top:10px; display:flex; gap:10px; flex-wrap:wrap;">
      <input type="text" name="q" value="<?= h($q) ?>" placeholder="搜尋：寄件/簽收/文件名稱/地區…">
      <button class="btn" type="submit">搜尋</button>
      <?php if ($q !== ""): ?><a class="btn" href="admin_files_manage.php">清除</a><?php endif; ?>
    </form>

    <table>
      <thead>
        <tr>
          <th>ID</th>
          <th>文件</th>
          <th>寄件人</th>
          <th>預計簽收人</th>
          <th>目的地區</th>
          <th>寄件時間</th>
          <th>狀態</th>
          <th>操作</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td><?= (int)$r["id"] ?></td>
          <td>
            <b><?= h($r["doc_name"]) ?></b>
            <div class="muted">
              <?= h($r["doc_type"]) ?><?= ($r["doc_type"]==="其他" ? " - ".h($r["doc_type_other"]) : "") ?>
            </div>
          </td>
          <td>
            <?= h($r["sender_name"]) ?><br>
            <span class="muted"><?= h($r["sender_user_no"]) ?> / <?= h($r["sender_site"]) ?></span>
          </td>
          <td>
            <?= h($r["intended_receiver_name"]) ?><br>
            <span class="muted"><?= h($r["intended_receiver_user_no"]) ?></span>
          </td>
          <td><?= h($r["dest_site"]) ?></td>
          <td><?= h($r["send_time"]) ?></td>
          <td><span class="pill"><?= h($statusDisplayMap[$r["status"]] ?? $r["status"]) ?></span></td>
          <td style="white-space:nowrap;">
            <a class="btn" href="admin_files_edit.php?id=<?= (int)$r["id"] ?>">編輯</a>
            <form class="inline" method="post" onsubmit="return confirm('確定要將此文件設為取消？');">
              <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
              <input type="hidden" name="action" value="cancel">
              <input type="hidden" name="id" value="<?= (int)$r["id"] ?>">
              <button class="btn" type="submit">取消</button>
            </form>
            <form class="inline" method="post" onsubmit="return confirm('確定要刪除？此動作不可復原');">
              <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?= (int)$r["id"] ?>">
              <button class="btn" type="submit">刪除</button>
            </form>
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