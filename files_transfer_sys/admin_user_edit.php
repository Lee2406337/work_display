<?php
require_once __DIR__ . "/admin_init.php";
require_admin(2);

$id = (int)($_GET["id"] ?? 0);
if ($id <= 0) redirect_to("admin_users_manage.php");

// 讀使用者資料加上 site 欄位
$stmt = $pdo->prepare("SELECT id, user_no, name, site FROM `user_data` WHERE id=? LIMIT 1");
$stmt->execute([$id]);
$u = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$u) {
  flash_set("err","找不到使用者");
  redirect_to("admin_users_manage.php");
}

$err = "";
if ($_SERVER["REQUEST_METHOD"] === "POST") {
  verify_csrf();

  // 基本資料
  $user_no = trim((string)($_POST["user_no"] ?? ""));
  $name        = trim((string)($_POST["name"] ?? ""));
  $site_choice = trim((string)($_POST["site_choice"] ?? ""));
  $site_other  = trim((string)($_POST["site_other"] ?? ""));
  $site = ($site_choice === "其他") ? $site_other : $site_choice;


  // 密碼，可選填：空白=不改
  $new  = (string)($_POST["new_password"] ?? "");
  $new2 = (string)($_POST["new_password2"] ?? "");

  // 驗證基本資料
  if ($user_no === "" || $name === "" || $site === "") {
    $err = "登錄帳號、姓名，地區皆為必填";
  }
  // 可選，登錄帳號格式限制：只允許英數底線減號
  elseif (!preg_match('/^[A-Za-z0-9_-]+$/', $user_no)) {
    $err = "登錄帳號格式不正確（僅允許英數、底線、減號）";
  }
  elseif ($site_choice === "其他" && $site_other === "") {
    $err = "地區選擇「其他」時，請填寫其他地區";
  }
  // 檢查登錄帳號是否重複
  else {
    $chk = $pdo->prepare("SELECT COUNT(*) FROM `user_data` WHERE user_no=? AND id<>?");
    $chk->execute([$user_no, $id]);
    if ((int)$chk->fetchColumn() > 0) {
      $err = "此登錄帳號已被使用";
    }
  }

  // 如果要改密碼，再做密碼驗證，允許不填=不改
  if ($err === "" && ($new !== "" || $new2 !== "")) {
    if ($new === "" || $new2 === "") {
      $err = "若要修改密碼，請兩格都輸入";
    } elseif ($new !== $new2) {
      $err = "兩次輸入的新密碼不一致";
    } elseif ($new === $user_no) {
      $err = "新密碼不可與登錄帳號相同";
    } elseif (strlen($new) < 4) {
      $err = "新密碼至少需要 4 碼";
    } else {
      // 不允許與舊密碼相同
      $cur = $pdo->prepare("SELECT password_hash FROM `user_data` WHERE id=?");
      $cur->execute([$id]);
      $curHash = (string)($cur->fetchColumn() ?? "");
      if ($curHash && password_verify($new, $curHash)) {
        $err = "新密碼不可與舊密碼相同";
      }
    }
  }

  // 4) 寫入更新，用 transaction：避免部分成功
  if ($err === "") {
    $pdo->beginTransaction();
    try {
      // 更新基本資料
      $updInfo = $pdo->prepare("UPDATE `user_data` SET user_no=?, name=?, site=? WHERE id=?");
      $updInfo->execute([$user_no, $name, $site, $id]);

      // 若有填新密碼才更新 password_hash
      if ($new !== "" && $new2 !== "") {
        $hash = password_hash($new, PASSWORD_DEFAULT);
        $updPwd = $pdo->prepare("UPDATE `user_data` SET password_hash=? WHERE id=?");
        $updPwd->execute([$hash, $id]);
      }

      $pdo->commit();

      flash_set("msg", "已更新使用者資料：{$user_no}");
      redirect_to("admin_users_manage.php");
    } catch (Throwable $e) {
      $pdo->rollBack();
      $err = "更新失敗，請稍後再試";
    }
  }

  // 更新失敗時，把表單值帶回去
  $u["user_no"] = $user_no;
  $u["name"] = $name;
  $u["site"] = $site;
}

$siteChoiceVal = in_array((string)$u["site"], ["林口","中壢"], true) ? (string)$u["site"] : "其他";
$siteOtherVal  = ($siteChoiceVal === "其他") ? (string)$u["site"] : "";

?>
<!doctype html>
<html lang="zh-Hant">
<head>
  <meta charset="utf-8">
  <title>編輯使用者</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    body{
      font-family:system-ui,-apple-system,"Segoe UI",Roboto,"Noto Sans TC",Arial; 
      background:#0b1220; 
      color:#e5e7eb; 
      margin:0;
    }
    .wrap{ 
      max-width:720px; 
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

    input{
      width:96.5%; 
      padding:10px 12px; 
      border-radius:12px;
      border:1px solid rgba(255,255,255,0.16);
      background:rgba(255,255,255,0.08);
      color:#e5e7eb;
    }

    select{
      width:100%; 
      padding:10px 12px;
      border-radius:12px; 
      border:1px solid rgba(255,255,255,0.16); 
      background:rgba(255,255,255,0.08); 
      color:#6d6d6d;
    }

    label{ 
      display:block; 
      margin:10px 0 6px; 
      color:#cbd5e1; 
    }

    .err{ 
      color:#fb7185; 
      font-weight:700; 
    }

    .muted{ 
      color:#9ca3af; 
    }

    .row{
      display:grid; 
      grid-template-columns:1fr 1fr; 
      gap:12px;
    }

    hr{ 
      border:0; 
      border-top:1px solid rgba(255,255,255,0.10); 
      margin:16px 0; 
    }
  </style>
</head>
<body>
<div class="wrap">
  <div class="card">
    <div style="display:flex; justify-content:space-between; align-items:center; gap:10px; flex-wrap:wrap;">
      <h1 style="margin:0;">編輯使用者</h1>
      <div style="display:flex; gap:10px;">
        <a class="btn" href="admin_users_manage.php">回列表</a>
        <a class="btn" href="admin_dashboard.php">回後台</a>
      </div>
    </div>

    <p class="muted">使用者ID：<?= h((string)$u["id"]) ?></p>
    <?php if ($err): ?><p class="err"><?= h($err) ?></p><?php endif; ?>

    <form method="post" autocomplete="off">
      <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">

      <label>登錄帳號</label>
      <input type="text" name="user_no" value="<?= h((string)$u["user_no"]) ?>" required>

      <label>姓名</label>
      <input type="text" name="name" value="<?= h((string)$u["name"]) ?>" required>

      <div class="row">
        <div>
          <label>地區（必填）</label>
          <select name="site_choice" id="site_choice"
                  onchange="toggleOther('site_choice','site_other_box')" required>
            <option value="林口" <?= ($siteChoiceVal==="林口" ? "selected" : "") ?>>林口</option>
            <option value="中壢" <?= ($siteChoiceVal==="中壢" ? "selected" : "") ?>>中壢</option>
            <option value="其他" <?= ($siteChoiceVal==="其他" ? "selected" : "") ?>>其他</option>
          </select>

          <span id="site_other_box" style="display:none;">
            <input type="text" name="site_other"
                  value="<?= h($siteOtherVal) ?>"
                  placeholder="請填寫其他地區" style="margin-top:8px;">
          </span>
        </div>
      </div>


      <hr>

      <div class="muted" style="margin-bottom:6px;">如不修改密碼，留空即可</div>

      <label>新密碼(至少 4 碼)</label>
      <input type="password" name="new_password">
      

      <label>確認新密碼</label>
      <input type="password" name="new_password2">

      <div style="margin-top:14px; display:flex; gap:10px; flex-wrap:wrap;">
        <button class="btn" type="submit" onclick="return confirm('確定要更新此使用者資料？');">確認更新</button>
        <a class="btn" href="admin_users_manage.php">取消</a>
      </div>
    </form>
  </div>
</div>
<script>
function toggleOther(selectId, boxId){
  const sel = document.getElementById(selectId);
  const box = document.getElementById(boxId);
  if (!sel || !box) return;

  const isOther = sel.value === '其他';
  box.style.display = isOther ? 'inline-block' : 'none';

  const input = box.querySelector('input');
  if (input){
    input.required = isOther;
    if (!isOther) input.value = "";
  }
}

document.addEventListener('DOMContentLoaded', ()=>toggleOther('site_choice','site_other_box'));
</script>
</body>
</html>
