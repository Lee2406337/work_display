<?php
require_once __DIR__ . "/admin_init.php";
require_admin(2);

function table_columns(PDO $pdo, string $table): array {
  $cols = [];
  $stmt = $pdo->prepare("SHOW COLUMNS FROM `$table`");
  $stmt->execute();
  foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $cols[] = (string)$r["Field"];
  }
  return $cols;
}

$cols = table_columns($pdo, "user_data");

$defaultNow = date("Y-m-d\TH:i");

$form = [
  "id" => "",
  "user_no" => "",
  "password_hash" => "",
  "name" => "",
  "site_choice" => "林口",
  "site_other" => "",
  "is_active" => "1",
  "permission_level" => "1",
  "created_at" => $defaultNow,
];

$err = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
  verify_csrf();

  foreach ($form as $k => $_v) {
    $form[$k] = trim((string)($_POST[$k] ?? ""));
  }

  $site = ($form["site_choice"] === "其他")
    ? trim($form["site_other"])
    : trim($form["site_choice"]);

  $idRaw = trim($form["id"]);
  $userNo = trim($form["user_no"]);
  $plainPassword = (string)$form["password_hash"];
  $name = trim($form["name"]);
  $isActive = (int)$form["is_active"];
  $permissionLevel = (int)$form["permission_level"];
  $createdAtRaw = trim($form["created_at"]);

  if ($userNo === "") {
    $err = "請填寫登錄帳號";
  } elseif ($plainPassword === "") {
    $err = "請填寫密碼";
  } elseif ($name === "") {
    $err = "請填寫姓名";
  } elseif ($site === "") {
    $err = "請填寫地區";
  } elseif (!in_array($form["site_choice"], ["林口", "中壢", "其他"], true)) {
    $err = "地區選項不正確";
  } elseif ($form["site_choice"] === "其他" && $form["site_other"] === "") {
    $err = "地區選擇「其他」時，請填寫其他地區";
  } elseif (!in_array($isActive, [0, 1], true)) {
    $err = "帳號狀態不正確";
  } elseif (!in_array($permissionLevel, [1, 2], true)) {
    $err = "權限等級不正確";
  } elseif ($idRaw !== "" && !ctype_digit($idRaw)) {
    $err = "ID 必須是數字，或留空自動生成";
  } elseif ($plainPassword === $userNo) {
    $err = "密碼不可與登錄帳號相同";
  } elseif (strlen($plainPassword) < 4) {
    $err = "密碼至少需要 4 碼";
  }

  $createdAtSql = null;
  if ($err === "") {
    if ($createdAtRaw === "") {
      $createdAtSql = date("Y-m-d H:i:s");
    } else {
      $createdAtSql = str_replace("T", " ", $createdAtRaw);
      if (strlen($createdAtSql) === 16) {
        $createdAtSql .= ":00";
      }
    }
  }

  if ($err === "") {
    $chk = $pdo->prepare("SELECT COUNT(*) FROM `user_data` WHERE user_no=?");
    $chk->execute([$userNo]);
    if ((int)$chk->fetchColumn() > 0) {
      $err = "此登錄帳號已被使用";
    }
  }

  if ($err === "" && $idRaw !== "") {
    $chk = $pdo->prepare("SELECT COUNT(*) FROM `user_data` WHERE id=?");
    $chk->execute([(int)$idRaw]);
    if ((int)$chk->fetchColumn() > 0) {
      $err = "此 ID 已存在，請更換或留空自動生成";
    }
  }

  if ($err === "") {
    try {
      $passwordHash = password_hash($plainPassword, PASSWORD_DEFAULT);

      $insertCols = [];
      $placeholders = [];
      $params = [];

      if ($idRaw !== "") {
        $insertCols[] = "id";
        $placeholders[] = "?";
        $params[] = (int)$idRaw;
      }

      $data = [
        "user_no" => $userNo,
        "password_hash" => $passwordHash,
        "name" => $name,
        "site" => $site,
        "is_active" => $isActive,
        "permission_level" => $permissionLevel,
        "created_at" => $createdAtSql,
      ];

      if (in_array("login_chance", $cols, true)) {
        $data["login_chance"] = 5;
      }

      if (in_array("deactivate_at", $cols, true)) {
        $data["deactivate_at"] = ($isActive === 0) ? date("Y-m-d H:i:s") : null;
      }

      foreach ($data as $col => $val) {
        if (in_array($col, $cols, true)) {
          $insertCols[] = $col;
          $placeholders[] = "?";
          $params[] = $val;
        }
      }

      $sql = "
        INSERT INTO `user_data`
          (`" . implode("`, `", $insertCols) . "`)
        VALUES
          (" . implode(", ", $placeholders) . ")
      ";

      $stmt = $pdo->prepare($sql);
      $stmt->execute($params);

      $newId = ($idRaw !== "") ? (int)$idRaw : (int)$pdo->lastInsertId();

      flash_set("msg", "已新增使用者：{$userNo}（ID: {$newId}）");
      redirect_to("admin_users_manage.php");

    } catch (Throwable $e) {
      $err = "新增失敗，請確認 user_data 欄位是否完整";
    }
  }
}
?>
<!doctype html>
<html lang="zh-Hant">
<head>
  <meta charset="utf-8">
  <title>建立使用者</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    body{
      font-family:system-ui,-apple-system,"Segoe UI",Roboto,"Noto Sans TC",Arial;
      background:#0b1220;
      color:#e5e7eb;
      margin:0;
    }

    .wrap{
      max-width:820px;
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
      gap:10px;
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
      cursor:pointer;
    }

    .btn:hover{
      background:rgba(255,255,255,0.16);
    }

    input, select{
      width:100%;
      padding:10px 12px;
      border-radius:12px;
      border:1px solid rgba(255,255,255,0.16);
      background:rgba(255,255,255,0.08);
      color:#e5e7eb;
      outline:none;
      box-sizing:border-box;
    }

    select{
      color:#6d6d6d;
    }

    label{
      display:block;
      margin:10px 0 6px;
      color:#cbd5e1;
    }

    .row{
      display:grid;
      grid-template-columns:1fr 1fr;
      gap:12px;
    }

    @media(max-width:760px){
      .row{
        grid-template-columns:1fr;
      }
    }

    .hint{
      color:#9ca3af;
      font-size:12px;
      margin-top:6px;
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
<div class="wrap">
  <div class="card">
    <div class="top">
      <div>
        <h1 style="margin:0;">建立使用者</h1>
        <div class="muted">新增帳號、設定地區、啟用狀態與權限</div>
      </div>
      <div style="display:flex; gap:10px; flex-wrap:wrap;">
        <a class="btn" href="admin_users_manage.php">回列表</a>
        <a class="btn" href="admin_dashboard.php">回後台</a>
      </div>
    </div>

    <?php if ($err): ?>
      <p class="err"><?= h($err) ?></p>
    <?php endif; ?>

    <form method="post" autocomplete="off">
      <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">

      <div class="row">
        <div>
          <label>ID</label>
          <input type="text" name="id" value="<?= h($form["id"]) ?>" placeholder="例如：1001">
          <div class="hint">留空＝資料庫自動編號；填入需為數字且不可重複。</div>
        </div>

        <div>
          <label>建立時間</label>
          <input type="datetime-local" name="created_at" value="<?= h($form["created_at"]) ?>">
          <div class="hint">預設為現在，可手動修改。</div>
        </div>
      </div>

      <label>登錄帳號</label>
      <input type="text" name="user_no" value="<?= h($form["user_no"]) ?>" required>

      <label>密碼</label>
      <input type="password" name="password_hash" value="" required>
      
      <label>姓名</label>
      <input type="text" name="name" value="<?= h($form["name"]) ?>" required>

      <div class="row">
        <div>
          <label>地區</label>
          <select name="site_choice" id="site_choice" onchange="toggleOther()" required>
            <option value="林口" <?= ($form["site_choice"] === "林口") ? "selected" : "" ?>>林口</option>
            <option value="中壢" <?= ($form["site_choice"] === "中壢") ? "selected" : "" ?>>中壢</option>
            <option value="其他" <?= ($form["site_choice"] === "其他") ? "selected" : "" ?>>其他</option>
          </select>

          <div id="site_other_box" style="display:none; margin-top:8px;">
            <input type="text" name="site_other" id="site_other"
                   value="<?= h($form["site_other"]) ?>"
                   placeholder="請填寫其他地區">
          </div>
        </div>

        <div>
          <label>帳號狀態</label>
          <select name="is_active" required>
            <option value="1" <?= ($form["is_active"] === "1") ? "selected" : "" ?>>啟用（1）</option>
            <option value="0" <?= ($form["is_active"] === "0") ? "selected" : "" ?>>停用（0）</option>
          </select>
        </div>
      </div>

      <div class="row">
        <div>
          <label>權限</label>
          <select name="permission_level" required>
            <option value="1" <?= ($form["permission_level"] === "1") ? "selected" : "" ?>>一般用戶（1）</option>
            <option value="2" <?= ($form["permission_level"] === "2") ? "selected" : "" ?>>管理者（2）</option>
          </select>
        </div>
      </div>

      <div style="margin-top:14px; display:flex; gap:10px; flex-wrap:wrap;">
        <button class="btn" type="submit" onclick="return confirm('確定要新增此使用者？');">確認新增</button>
        <a class="btn" href="admin_users_manage.php">取消</a>
      </div>
    </form>
  </div>
</div>

<script>
function toggleOther(){
  const sel = document.getElementById('site_choice');
  const box = document.getElementById('site_other_box');
  const input = document.getElementById('site_other');

  if (!sel || !box || !input) return;

  const isOther = sel.value === '其他';
  box.style.display = isOther ? 'block' : 'none';
  input.required = isOther;

  if (!isOther) {
    input.value = '';
  }
}

document.addEventListener('DOMContentLoaded', toggleOther);
</script>
</body>
</html>