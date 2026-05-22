<?php
require_once __DIR__ . "/admin_init.php";
require_admin(2);

$allowedStatus = ["SENT","PICKED","RECEIVED","PROXY_RECEIVED","COMPLETED","CANCELED","ERROR"];

function status_label(string $s): string {
  if (in_array($s, ["COMPLETED","RECEIVED","PROXY_RECEIVED"], true)) return "完成";
  if (in_array($s, ["SENT","PICKED"], true)) return "進行中";
  if ($s==="CANCELED") return "取消";
  if ($s==="ERROR") return "錯誤";
  return $s;
}

// 取得人員清單（給選人 dialog & 後端驗證）
$usersStmt = $pdo->query("
  SELECT user_no, name, site
  FROM `user_data`
  ORDER BY site, user_no
");
$users = $usersStmt->fetchAll(PDO::FETCH_ASSOC);

// quick map: 後端驗證/補資料用
$userMap = [];
foreach ($users as $u) {
  $userMap[(string)$u["user_no"]] = $u;
}

function sql_to_dt_local(?string $sql): string {
  // "YYYY-mm-dd HH:ii:ss" -> "YYYY-mm-ddTHH:ii"
  if (!$sql) return "";
  $sql = trim($sql);
  if ($sql === "0000-00-00 00:00:00") return "";
  $sql = str_replace(" ", "T", $sql);
  return substr($sql, 0, 16);
}

function split_site_choice(string $site): array {
  $site = trim((string)$site);
  if ($site === "" ) return ["林口", ""];
  if (in_array($site, ["林口","中壢"], true)) return [$site, ""];
  return ["其他", $site];
}

function split_route_choice(string $route): array {
  $route = trim((string)$route);
  $known = ["林口->中壢","中壢->林口","林口->新竹地","新竹地->林口","林口->台南地","台南地->林口","其他"];
  if ($route === "" ) return ["林口->中壢", ""];
  if (in_array($route, $known, true)) return [$route, ""];
  return ["其他", $route];
}

$id = (int)($_GET["id"] ?? 0);
if ($id <= 0) redirect_to("admin_files_manage.php");

$stmt = $pdo->prepare("SELECT * FROM `files_transfer` WHERE id=? LIMIT 1");
$stmt->execute([$id]);
$r = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$r) {
  flash_set("err","找不到文件資料");
  redirect_to("admin_files_manage.php");
}

$defaultNow = date("Y-m-d\\TH:i");

// 將 DB 資料帶入表單（表單順序/欄位跟 add 一樣）
list($sender_site_choice, $sender_site_other) = split_site_choice((string)($r["sender_site"] ?? ""));
list($dest_site_choice, $dest_site_other)     = split_site_choice((string)($r["dest_site"] ?? ""));
list($receive_site_choice, $receive_site_other) = split_site_choice((string)($r["receive_site"] ?? ""));
list($route_choice, $route_other) = split_route_choice((string)($r["route"] ?? ""));

$form = [
  "id" => (string)($r["id"] ?? ""),
  "doc_name" => (string)($r["doc_name"] ?? ""),
  "doc_type" => (string)($r["doc_type"] ?? "信件"),
  "doc_type_other" => (string)($r["doc_type_other"] ?? ""),

  // 由選人 dialog 填入（readonly）
  "sender_user_no" => (string)($r["sender_user_no"] ?? ""),
  "sender_name" => (string)($r["sender_name"] ?? ""),
  "sender_site" => (string)($r["sender_site"] ?? ""),

  "received_by_user_no" => (string)($r["received_by_user_no"] ?? ""),
  "received_by_name" => (string)($r["received_by_name"] ?? ""),
  "received_by_site" => "",

  "picker_user_no" => (string)($r["picker_user_no"] ?? ""),
  "picker_name" => (string)($r["picker_name"] ?? ""),
  "picker_site" => "",

  // 送件地區
  "sender_site_choice" => $sender_site_choice,
  "sender_site_other" => $sender_site_other,

  // 目的地區
  "dest_site_choice" => $dest_site_choice,
  "dest_site_other" => $dest_site_other,

  // 簽收地區
  "receive_site" => $receive_site_choice,
  "receive_site_other" => $receive_site_other,

  // 路徑
  "route" => $route_choice,
  "route_other" => (string)($r["route_other"] ?? $route_other),

  // 時間
  "send_time" => sql_to_dt_local($r["send_time"] ?? "") ?: $defaultNow,
  "pick_time" => sql_to_dt_local($r["pick_time"] ?? "") ?: $defaultNow,
  "receive_time" => sql_to_dt_local($r["receive_time"] ?? "") ?: $defaultNow,

  // 是否代收
  "is_proxy" => (string)($r["is_proxy"] ?? "0"),

  // 代收顯示欄位（若 DB 沒有欄位也不會報錯：只用在前端；更新時會嘗試存）
  "final_receiver_user_no" => (string)($r["final_receiver_user_no"] ?? ""),
  "final_receiver_name" => (string)($r["final_receiver_name"] ?? ""),
  "final_receive_time" => sql_to_dt_local($r["final_receive_time"] ?? "") ?: $defaultNow,

  "status" => (string)($r["status"] ?? "SENT"),
];

$err = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
  verify_csrf();

  // 讀表單
  foreach ($form as $k => $_v) {
    $form[$k] = trim((string)($_POST[$k] ?? $form[$k]));
  }

  // checkbox:沒勾不會送出
  $form["is_proxy"] = isset($_POST["is_proxy"]) ? "1" : "0";

  // 基本驗證
  if ($form["doc_name"] === "") $err = "請填寫文件名稱";
  if ($err === "" && !in_array($form["status"], $allowedStatus, true)) $err = "狀態不正確";
  if ($err === "" && $form["doc_type"] === "") $err = "請選擇文件類型";

  // 文件類型其他:只有選"其他"才保留
  $docTypeOther = "";
  if ($form["doc_type"] === "其他") {
    $docTypeOther = $form["doc_type_other"];
    if ($docTypeOther === "") $err = "文件類型選擇「其他」時，請填寫其他類型";
  }

  // 地區 送件 / 目的 / 簽收
  $sender_site = $form["sender_site_choice"];
  if ($sender_site === "其他") {
    $sender_site = trim($form["sender_site_other"]);
    if ($sender_site === "") $err = "送件地區選擇「其他」時，請填寫其他地區";
  }

  $dest_site = $form["dest_site_choice"];
  if ($dest_site === "其他") {
    $dest_site = trim($form["dest_site_other"]);
    if ($dest_site === "") $err = "送件地區選擇「其他」時，請填寫其他地區";
  }

  $receive_site = $form["receive_site"];
  if ($receive_site === "其他") {
    $receive_site = trim($form["receive_site_other"]);
    if ($receive_site === "") $err = "簽收地區選擇「其他」時，請填寫其他地區";
  }

  // 路徑 必填
  $route = $form["route"];
  $route_other2 = "";
  if ($route === "其他") {
    $route_other2 = trim($form["route_other"]);
    if ($route_other2 === "") $err = "路徑選擇「其他」時，請填寫其他路徑";
  }

  // 人員 用大選單選，後端驗證存在
  if ($err === "") {
    $senderNo = $form["sender_user_no"];
    $recvNo   = $form["received_by_user_no"];
    $pickerNo = $form["picker_user_no"];

    if ($senderNo === "" || $recvNo === "" || $pickerNo === "") {
      $err = "送件人 / 簽收人 / 取件人皆為必填，請使用「選擇」從人員清單選取";
    } elseif (!isset($userMap[$senderNo]) || !isset($userMap[$recvNo]) || !isset($userMap[$pickerNo])) {
      $err = "選擇的人員不存在，請重新選擇（可能人員資料已更新）";
    } else {
      // 用 DB 的姓名覆蓋避免前端改值
      $sender = $userMap[$senderNo];
      $recv   = $userMap[$recvNo];
      $picker = $userMap[$pickerNo];

      $form["sender_name"] = (string)$sender["name"];
      $form["received_by_name"] = (string)$recv["name"];
      $form["picker_name"] = (string)$picker["name"];
      $form["sender_site"] = (string)$sender_site;
    }
  }

  // 是否代收:勾選才要求最終簽收人/時間
  $finalRecvNo = "";
  $finalRecvName = "";
  $finalReceiveTimeSql = null;

  if ($err === "" && $form["is_proxy"] === "1") {
    $finalRecvNo = trim($form["final_receiver_user_no"]);
    if ($finalRecvNo === "") {
      $err = "勾選「是否代收」時，請選擇最終簽收人";
    } elseif (!isset($userMap[$finalRecvNo])) {
      $err = "最終簽收人不存在，請重新選擇";
    } else {
      $finalRecvName = (string)$userMap[$finalRecvNo]["name"];
      $form["final_receiver_name"] = $finalRecvName;
      if (trim($form["final_receive_time"]) === "") {
        $err = "勾選「是否代收」時，請填寫最終簽收時間";
      } else {
        $finalReceiveTimeSql = str_replace("T", " ", $form["final_receive_time"]) . ":00";
      }
    }
  }

  if ($err === "") {
    // datetime-local -> datetime
    $sendTimeSql    = str_replace("T", " ", $form["send_time"]) . ":00";
    $pickTimeSql    = str_replace("T", " ", $form["pick_time"]) . ":00";
    $receiveTimeSql = str_replace("T", " ", $form["receive_time"]) . ":00";

    // 簽收人 = 預計簽收人 `沿用 add 行為
    $intendedNo   = $form["received_by_user_no"];
    $intendedName = $form["received_by_name"];

    // 允許改 ID：留空 = 不改
    $newIdRaw = trim((string)($_POST["id"] ?? ""));
    $newId = $id;
    if ($newIdRaw !== "") {
      if (!ctype_digit($newIdRaw)) {
        $err = "ID 必須為數字";
      } else {
        $newId = (int)$newIdRaw;
          if ($newId <= 0) $err = "ID 必須為正整數";
      }
    }

    // ID 重複檢查（排除自己）
    if ($err === "" && $newId !== $id) {
        $chk = $pdo->prepare("SELECT COUNT(*) FROM `files_transfer` WHERE id=?");
        $chk->execute([$newId]);
        if ((int)$chk->fetchColumn() > 0) $err = "此 ID 已存在，請換一個";
    }

    if ($err === "") {
      // 先更新主要欄位（不含 final_*）
      $sqlBase = "
        UPDATE `files_transfer` SET
          id=?,
          doc_name=?,
          doc_type=?,
          doc_type_other=?,
          sender_user_no=?,
          sender_name=?,
          sender_site=?,
          intended_receiver_user_no=?,
          intended_receiver_name=?,
          received_by_user_no=?,
          received_by_name=?,
          picker_user_no=?,
          picker_name=?,
          dest_site=?,
          receive_site=?,
          route=?,
          route_other=?,
          send_time=?,
          pick_time=?,
          receive_time=?,
          is_proxy=?,
          status=?
        WHERE id=?
      ";

      $params = [
        (int)$newId,
        $form["doc_name"],
        $form["doc_type"],
        $docTypeOther,
        $form["sender_user_no"],
        $form["sender_name"],
        $sender_site,
        $intendedNo,
        $intendedName,
        $form["received_by_user_no"],
        $form["received_by_name"],
        $form["picker_user_no"],
        $form["picker_name"],
        $dest_site,
        $receive_site,
        $route,
        ($route === "其他") ? $route_other2 : "",
        $sendTimeSql,
        $pickTimeSql,
        $receiveTimeSql,
        (int)$form["is_proxy"],
        $form["status"],
        (int)$id,
      ];

      $upd = $pdo->prepare($sqlBase);
      $upd->execute($params);

      // 嘗試更新 final_*（若 DB 沒欄位，忽略不讓整個編輯壞掉）
      try {
        $upd2 = $pdo->prepare("
          UPDATE `files_transfer` SET
            final_receiver_user_no=?,
            final_receiver_name=?,
            final_receive_time=?
          WHERE id=?        
        ");
        if ($form["is_proxy"] === "1") {
          $upd2->execute([$finalRecvNo, $finalRecvName, $finalReceiveTimeSql, (int)$newId]);
        } else {
          $upd2->execute([null, null, null, (int)$newId]);
        }
      } catch (Throwable $e) {
        // 若資料表沒有 final_* 欄位就略過
      }

      flash_set("msg","已更新文件資料（ID: {$newId}）");
      redirect_to("admin_files_manage.php");
    }
  }
}
?>
<!doctype html>
<html lang="zh-Hant">
<head>
  <meta charset="utf-8">
  <title>編輯文件資料</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    body{
      font-family:system-ui,-apple-system,"Segoe UI",Roboto,"Noto Sans TC",Arial;
      background:#0b1220;
      color:#e5e7eb;
      margin:0;
    }

    .wrap{ 
      max-width:900px; 
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
      width:100%;
      padding:10px 12px;
      border-radius:12px;
      border:1px solid rgba(255,255,255,0.16);
      background:rgba(255,255,255,0.08);
      color:#6d6d6d;
      outline:none;
      box-sizing:border-box;
    }

    label{ 
      display:block; 
      margin:10px 0 6px; 
      color:#cbd5e1; }
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

    .label-inline{
      display:flex;
      align-items:center;
      gap:8px;
    }

    .err{ 
      color:#fb7185; 
      font-weight:700; 
    }

    .muted{ 
      color:#9ca3af; 
    }

    .hint{ 
      color:#9ca3af; 
      font-size:12px;
      margin-top:6px; 
    }

    .pickline{
      display:flex; 
      gap:10px; 
      align-items:stretch;
    }
    .pickline input{ 
      flex: 1; 
    }
    .pickbtn{
      white-space:nowrap;
      padding:10px 14px;
      border-radius:12px;
      background:rgba(255,255,255,0.10);
      border:1px solid rgba(255,255,255,0.16);
      color:#e5e7eb;
      cursor:pointer;
    }
    .pickbtn:hover{ 
      background:rgba(255,255,255,0.16); 
    }

    /* User Picker Dialog */
    .picker-dialog{
      border:none;
      padding:0;
      width:min(560px, calc(100vw - 28px));
      border-radius:18px;
      overflow:hidden;
      background:transparent;
    }
    .picker-dialog::backdrop{
      background: rgba(15, 23, 42, 0.55);
    }
    .picker-card{
      background: rgba(255,255,255,0.96);
      border-radius: 18px;
      padding: 18px 18px 16px;
      box-shadow: 0 16px 40px rgba(0,0,0,0.28);
      color:#0f172a;
    }
    .picker-title{ 
      margin:0 0 6px 0; 
      font-size:28px; 
      letter-spacing:1px; 
    }
    .picker-sub{ 
      margin:0 0 12px 0; 
      color: rgba(15,23,42,0.65); 
      font-size:14px; 
    }
    .picker-search{
      width:100%;
      padding:12px 14px;
      border-radius:12px;
      border:1px solid rgba(15,23,42,0.18);
      outline:none;
      font-size:16px;
      box-sizing:border-box;
      background:#fff;
      color:#0f172a;
    }
    .picker-table-wrap{
      max-height: 420px;
      overflow:auto;
      margin-top:14px;
      border-radius:14px;
      background:#fff;
      border:1px solid rgba(15,23,42,0.10);
    }
    .picker-table{ 
      width:100%; 
      border-collapse:collapse; 
    }
    .picker-table thead th{
      position: sticky; 
      top:0; 
      z-index:1;
      background: rgba(15, 23, 42, 0.92);
      color:#fff;
      text-align:left;
      padding:14px 14px;
      font-weight:700;
      letter-spacing:.5px;
    }
    .picker-table tbody td{
      padding:16px 14px;
      border-bottom:1px solid rgba(15,23,42,0.08);
      font-size:18px;
    }
    .picker-table tbody tr:nth-child(even) td{ 
      background: rgba(15, 23, 42, 0.03); 
    }
    .picker-table tbody tr:last-child td{ 
      border-bottom:0; 
    }

    .picker-btn{
      padding:10px 16px;
      border-radius:16px;
      border:0;
      background: rgba(15, 23, 42, 0.92);
      color:#fff;
      font-weight:700;
      cursor:pointer;
    }
    .picker-btn:hover{ 
      filter:brightness(1.06); 
    }

    .picker-actions{ 
      margin-top:14px; 
      display:flex; 
      justify-content:flex-start; 
    }
    .picker-close{
      padding:10px 16px;
      border-radius:16px;
      border:0;
      background: rgba(15, 23, 42, 0.92);
      color:#fff;
      font-weight:700;
      cursor:pointer;
    }
    .picker-close:hover{ 
      filter:brightness(1.06); 
    }

    .proxy-wrap{
      display: flex;
      flex-direction: column;
      align-items: flex-start;
    }
    .proxy-line{
      display: inline-flex;
      align-items: center;
      gap: 0px;
      cursor: pointer;
      white-space: nowrap;
    }
  </style>
</head>
<body>
<div class="wrap">
  <div class="card">
    <div style="display:flex; justify-content:space-between; align-items:center; gap:10px; flex-wrap:wrap;">
      <h1 style="margin:0;">編輯文件資料</h1>
      <div style="display:flex; gap:10px; flex-wrap:wrap;">
        <a class="btn" href="admin_files_manage.php">回列表</a>
        <a class="btn" href="admin_dashboard.php">回後台</a>
      </div>
    </div>

    <?php if ($err): ?><p class="err"><?= h($err ?? "") ?></p><?php endif; ?>

    <form method="post" autocomplete="off">
      <input type="hidden" name="_csrf" value="<?= h(csrf_token() ?? "") ?>">

      <div class="row">
        <div>
          <label>ID（可留空自動生成）</label>
          <input name="id" value="<?= h($form["id"] ?? "") ?>" placeholder="例如：1001">
          <div class="hint">留空＝資料庫自動編號；填入需為數字且不可重複。</div>
        </div>
        <div>
          <label>狀態（必填）</label>
          <select name="status" required>
            <?php foreach ($allowedStatus as $s): ?>
              <option value="<?= h($s ?? "") ?>" <?= (($form["status"] ?? "")===$s ? "selected" : "") ?>>
                <?= h(status_label($s) ?? "") ?>（<?= h($s ?? "") ?>）
              </option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <label>文件名稱（必填）</label>
      <input name="doc_name" value="<?= h($form["doc_name"] ?? "") ?>" required placeholder="文件名稱">

      <div class="row">
        <div>
          <label>文件類型（必填）</label>
          <select name="doc_type" id="doc_type" required onchange="toggleDocTypeOther()">
            <option value="信件" <?= (($form["doc_type"] ?? "")==="信件" ? "selected" : "") ?>>信件</option>
            <option value="包裹" <?= (($form["doc_type"] ?? "")==="包裹" ? "selected" : "") ?>>包裹</option>
            <option value="其他" <?= (($form["doc_type"] ?? "")==="其他" ? "selected" : "") ?>>其他</option>
          </select>
        </div>

        <div id="doc_type_other_wrap" style="display:none;">
          <label>其他類型</label>
          <input id="doc_type_other" name="doc_type_other"
                 value="<?= h($form["doc_type_other"] ?? "") ?>"
                 placeholder="例如：客訴單">
        </div>
      </div>

      <label>送件人（必填）</label>
      <div class="pickline">
        <input id="sender_user_no" name="sender_user_no" value="<?= h($form["sender_user_no"] ?? "") ?>" placeholder="登錄帳號" readonly required>
        <input id="sender_name" name="sender_name" value="<?= h($form["sender_name"] ?? "") ?>" placeholder="姓名" readonly>
        <button type="button" class="pickbtn" onclick="openPicker('sender_user_no','sender_name','sender_site')">選擇</button>
      </div>

      <label>取件人（必填）</label>
      <div class="pickline">
        <input id="picker_user_no" name="picker_user_no" value="<?= h($form["picker_user_no"] ?? "") ?>" placeholder="登錄帳號" readonly required>
        <input id="picker_name" name="picker_name" value="<?= h($form["picker_name"] ?? "") ?>" placeholder="姓名" readonly>
        <button type="button" class="pickbtn" onclick="openPicker('picker_user_no','picker_name','picker_site')">選擇</button>
      </div>

      <label>簽收人（必填）</label>
      <div class="pickline">
        <input id="received_by_user_no" name="received_by_user_no" value="<?= h($form["received_by_user_no"] ?? "") ?>" placeholder="登錄帳號" readonly required>
        <input id="received_by_name" name="received_by_name" value="<?= h($form["received_by_name"] ?? "") ?>" placeholder="姓名" readonly>
        <button type="button" class="pickbtn" onclick="openPicker('received_by_user_no','received_by_name','received_by_site')">選擇</button>
      </div>

      <div class="row">
        <div>
          <label>送件地區（必填，預設林口）</label>
          <select name="sender_site_choice" id="sender_site_choice" onchange="toggleOther('sender_site_choice','sender_site_other_box')" required>
            <option value="林口" <?= (($form["sender_site_choice"] ?? "")==="林口" ? "selected" : "") ?>>林口</option>
            <option value="中壢" <?= (($form["sender_site_choice"] ?? "")==="中壢" ? "selected" : "") ?>>中壢</option>
            <option value="其他" <?= (($form["sender_site_choice"] ?? "")==="其他" ? "selected" : "") ?>>其他</option>
          </select>

          <span id="sender_site_other_box" style="display:none;">
            <input type="text" name="sender_site_other"
                   value="<?= h($form["sender_site_other"] ?? "") ?>"
                   placeholder="請填寫其他地區" style="margin-top:8px;">
          </span>
        </div>

        <div> 
          <label>目的地區（必填，預設林口）</label>
          <select name="dest_site_choice" id="dest_site_choice" onchange="toggleOther('dest_site_choice','dest_site_other_box')" required>
            <option value="林口" <?= (($form["dest_site_choice"] ?? "")==="林口" ? "selected" : "") ?>>林口</option>
            <option value="中壢" <?= (($form["dest_site_choice"] ?? "")==="中壢" ? "selected" : "") ?>>中壢</option>
            <option value="其他" <?= (($form["dest_site_choice"] ?? "")==="其他" ? "selected" : "") ?>>其他</option>
          </select>

          <span id="dest_site_other_box" style="display:none;">
            <input type="text" name="dest_site_other"
                   value="<?= h($form["dest_site_other"] ?? "") ?>"
                   placeholder="請填寫其他地區" style="margin-top:8px;">
          </span>
        </div>

        <div> 
          <label>簽收地區（必填，預設林口）</label>
          <select name="receive_site" id="receive_site" onchange="toggleOther('receive_site','receive_site_other_box')" required>
            <option value="林口" <?= (($form["receive_site"] ?? "")==="林口" ? "selected" : "") ?>>林口</option>
            <option value="中壢" <?= (($form["receive_site"] ?? "")==="中壢" ? "selected" : "") ?>>中壢</option>
            <option value="其他" <?= (($form["receive_site"] ?? "")==="其他" ? "selected" : "") ?>>其他</option>
          </select>

          <span id="receive_site_other_box" style="display:none;">
            <input type="text" name="receive_site_other"
                   value="<?= h($form["receive_site_other"] ?? "") ?>"
                   placeholder="請填寫其他地區" style="margin-top:8px;">
          </span>
        </div>
      </div>

      <div class="row">
        <div>
          <label>路徑（必填）</label>
          <select name="route" id="route" onchange="toggleRouteOther(); filterPickList();" required>
            <option value="林口->中壢" <?= (($form["route"] ?? "")==="林口->中壢") ? "selected" : "" ?>>林口->中壢</option>
            <option value="中壢->林口" <?= (($form["route"] ?? "")==="中壢->林口") ? "selected" : "" ?>>中壢->林口</option>
            <option value="其他" <?= (($form["route"] ?? "")==="其他") ? "selected" : "" ?>>其他</option>
          </select>
        </div>

        <div id="route_other_wrap" style="display:none;">
          <label>其他路徑</label>
          <input id="route_other" name="route_other"
                 value="<?= h($form["route_other"] ?? "") ?>"
                 placeholder="請填寫其他路線">
        </div>
      </div>

      <div class="row">
        <div>
          <label>送件時間（必填）</label>
          <input type="datetime-local" name="send_time" value="<?= h($form["send_time"] ?? "") ?>" required>
          <div class="hint">預設為現在</div>
        </div>

        <div>
          <label>取件時間（必填）</label>
          <input type="datetime-local" name="pick_time" value="<?= h($form["pick_time"] ?? "") ?>" required>
          <div class="hint">預設為現在</div>
        </div>

        <div>
          <label>簽收時間（必填）</label>
          <input type="datetime-local" name="receive_time" value="<?= h($form["receive_time"] ?? "") ?>" required>
          <div class="hint">預設為現在</div>
        </div>

        </div>
        <div class="proxy-wrap">
          <!-- 是否代收 -->
          <div class="proxy-wrap" style="margin-top:12px;">
            <div class="proxy-line">
              <label style="display:inline-flex; align-items:center; gap:8px;">
                是否代收
                <input type="checkbox"
                       id="is_proxy"
                       name="is_proxy"
                       value="1"
                       <?= (($form["is_proxy"] ?? "")==="1" ? "checked" : "") ?> >
              </label>
            </div>
            <div class="hint">勾選表示取件人為代收人員</div>
          </div>

          <!-- 勾選代收才顯示 -->
          <div id="final_wrap" style="display:none; margin-top:10px;">
            <!-- 最終收件人 -->
            <label>最終收件人（必填）</label>
            <div class="pickline">
              <input id="final_receiver_user_no" name="final_receiver_user_no"
                    value="<?= h($form["final_receiver_user_no"] ?? "") ?>"
                    placeholder="登錄帳號" readonly>
              <input id="final_receiver_name" name="final_receiver_name"
                    value="<?= h($form["final_receiver_name"] ?? "") ?>"
                    placeholder="姓名" readonly>
              <button type="button" class="pickbtn"
                      onclick="openPicker('final_receiver_user_no','final_receiver_name')">
                選擇
              </button>
            </div>

            <div style="margin-top:10px;">
              <label>最終收件時間（必填）</label>
              <input type="datetime-local" id="final_receive_time" name="final_receive_time"
                    value="<?= h($form["final_receive_time"] ?? "") ?>">
              <div class="hint">預設為現在</div>
            </div>
          </div>
      </div>

      <div style="margin-top:14px; display:flex; gap:10px; flex-wrap:wrap;">
        <button class="btn" type="submit" onclick="return confirm('確定要更新這筆文件資料？');">確認更新</button>
        <a class="btn" href="admin_files_manage.php">取消</a>
      </div>
    </form>
  </div>
</div>

<!-- 人員選擇 Dialog -->
<dialog id="userPicker" class="picker-dialog">
  <div class="picker-card">
    <h3 class="picker-title">選擇人員</h3>
    <p class="picker-sub">可搜尋：登錄帳號 / 姓名 / 地區</p>

    <input type="text" id="pickerSearch" class="picker-search" placeholder="輸入關鍵字...">

    <div class="picker-table-wrap">
      <table class="picker-table">
        <thead>
          <tr>
            <th style="width:28%;">登錄帳號</th>
            <th style="width:26%;">姓名</th>
            <th style="width:22%;">地區</th>
            <th style="width:24%;">選擇</th>
          </tr>
        </thead>
        <tbody id="pickerBody">
          <?php foreach ($users as $u): ?>
            <tr class="picker-row"
                data-user-no="<?= h($u["user_no"]) ?>"
                data-name="<?= h($u["name"]) ?>"
                data-site="<?= h($u["site"]) ?>">
              <td><?= h($u["user_no"]) ?></td>
              <td><?= h($u["name"]) ?></td>
              <td><?= h($u["site"]) ?></td>
              <td style="text-align:right;">
                <button type="button" class="picker-btn" onclick="chooseUser(this)">選擇</button>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <div class="picker-actions">
      <button type="button" class="picker-close" onclick="closePicker()">關閉</button>
    </div>
  </div>
</dialog>

<script>
/* 1. doc_type=其他 才顯示 doc_type_other */
function toggleDocTypeOther(){
  const sel = document.getElementById('doc_type');
  const wrap = document.getElementById('doc_type_other_wrap');
  const input = document.getElementById('doc_type_other');
  if (!sel || !wrap || !input) return;

  const isOther = sel.value === '其他';
  wrap.style.display = isOther ? 'block' : 'none';
  input.required = isOther;
  if (!isOther) input.value = "";
}

/* 2. route=其他 才顯示 route_other */
function toggleRouteOther(){
  const sel = document.getElementById('route');
  const wrap = document.getElementById('route_other_wrap');
  const input = document.getElementById('route_other');
  if (!sel || !wrap || !input) return;

  const isOther = sel.value === '其他';
  wrap.style.display = isOther ? 'block' : 'none';
  input.required = isOther;
  if (!isOther) input.value = "";
}

/* 3. 是否為代收：勾選才顯示 最終收件人 / 最終收件時間 */
function toggleFinalFields(clear=false){
  const cb = document.getElementById('is_proxy');
  const wrap = document.getElementById('final_wrap');

  const finalNo = document.getElementById('final_receiver_user_no');
  const finalName = document.getElementById('final_receiver_name');
  const finalTime = document.getElementById('final_receive_time');

  if (!cb || !wrap) return;

  const on = cb.checked;
  wrap.style.display = on ? 'block' : 'none';

  // 代收時才必填
  if (finalNo) finalNo.required = on;
  if (finalTime) finalTime.required = on;

  // 只有「使用者取消勾選」才清空，避免一進頁面就把原資料清掉
  if (!on && clear){
    if (finalNo) finalNo.value = "";
    if (finalName) finalName.value = "";
    if (finalTime) finalTime.value = "";
  } else if (on) {
    // 勾選時若時間為空，自動補現在，datetime-local 格式
    if (finalTime && !finalTime.value){
      const d = new Date();
      const pad = n => String(n).padStart(2,'0');
      finalTime.value = d.getFullYear() + "-" + pad(d.getMonth()+1) + "-" + pad(d.getDate()) + "T" + pad(d.getHours()) + ":" + pad(d.getMinutes());
    }
  }
}

/* 4. Picker logic */
let pickerTarget = { noId:null, nameId:null, siteId:null };

function openPicker(noInputId, nameInputId, siteInputId){
  pickerTarget = { noId:noInputId, nameId:nameInputId, siteId:siteInputId };

  const dlg = document.getElementById('userPicker');
  const search = document.getElementById('pickerSearch');
  if (!dlg) return;

  if (typeof dlg.showModal === 'function') dlg.showModal();
  else dlg.setAttribute('open','open');

  if (search){
    search.value = "";
    filterPickerRows("");
    setTimeout(()=>search.focus(), 30);
  }
}

function closePicker(){
  const dlg = document.getElementById('userPicker');
  if (!dlg) return;
  if (typeof dlg.close === 'function') dlg.close();
  else dlg.removeAttribute('open');
}

function chooseUser(btn){
  const tr = btn.closest('tr');
  if (!tr) return;

  const no = tr.dataset.userNo || "";
  const name = tr.dataset.name || "";
  const site = tr.dataset.site || "";

  const noEl = document.getElementById(pickerTarget.noId);
  const nameEl = document.getElementById(pickerTarget.nameId);
  const siteEl = document.getElementById(pickerTarget.siteId);

  if (noEl) noEl.value = no;
  if (nameEl) nameEl.value = name;
  if (siteEl) siteEl.value = site;

  closePicker();
}

function filterPickerRows(q){
  q = (q || "").trim().toLowerCase();
  document.querySelectorAll('#pickerBody .picker-row').forEach(row=>{
    const no = (row.dataset.userNo || "").toLowerCase();
    const name = (row.dataset.name || "").toLowerCase();
    const site = (row.dataset.site || "").toLowerCase();
    const hit = no.includes(q) || name.includes(q) || site.includes(q);
    row.style.display = hit ? "" : "none";
  });
}

/* 5. Init - DOMContentLoaded */
document.addEventListener('DOMContentLoaded', ()=>{
  // 初始化「依下拉切換顯示」的區塊
  toggleDocTypeOther();
  toggleRouteOther();
  toggleFinalFields(false);
  const cb = document.getElementById('is_proxy');
  if (cb) cb.addEventListener('change', ()=>toggleFinalFields(true));

  // 綁 Picker 搜尋事件
  const search = document.getElementById('pickerSearch');
  if (search){
    search.addEventListener('input', ()=>filterPickerRows(search.value));
  }

  // dialog 關閉時清掉 target
  const dlg = document.getElementById('userPicker');
  if (dlg){
    dlg.addEventListener('close', ()=>{ pickerTarget = {noId:null, nameId:null, siteId:null}; });
  }
});
</script>
</body>
</html>
