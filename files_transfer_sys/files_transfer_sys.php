<?php
require_once __DIR__ . "/init.php";
require_login();

$user = $_SESSION["user"];
$permission = (int)($user["permission_level"] ?? 1);

// 回頁
function redirect_with($tab, $msg = "", $error = "") {
  $qs = [];
  if ($tab !== "") $qs["tab"] = $tab;
  if ($msg !== "") $qs["msg"] = $msg;
  if ($error !== "") $qs["error"] = $error;
  $url = basename($_SERVER["PHP_SELF"]);
  if (!empty($qs)) $url .= "?" . http_build_query($qs);
  header("Location: " . $url);
  exit;
}

function normalize_site($s) {
  $s = trim((string)$s);
  if ($s === "中壢") return "中壢";
  return $s;
}

// 讀取可選人員，權限1只看 is_active=1、權限2可看全部
$where = [];
$params = [];
if ($permission === 1) {
  $where[] = "is_active = 1";
}
$sqlUsers = "SELECT user_no, name, site, is_active FROM `user_data`";
if ($where) $sqlUsers .= " WHERE " . implode(" AND ", $where);
$sqlUsers .= " ORDER BY id DESC";
$stmtU = $pdo->prepare($sqlUsers);
$stmtU->execute($params);
$users = $stmtU->fetchAll();

// POST：處理送件/取件/簽收/最終簽收
if ($_SERVER["REQUEST_METHOD"] === "POST") {
  verify_csrf(); // CSRF
  $action = $_POST["action"] ?? "";

  // 送件
  if ($action === "send") {
    $sender_user_no = (string)$user["user_no"];
    $sender_name        = (string)$user["name"];

    // 送件地區:下拉(林口/中壢/其他) + 其他必填
    $sender_site_choice = trim($_POST["sender_site_choice"] ?? "");
    $sender_site_other  = trim($_POST["sender_site_other"] ?? "");
    $sender_site = $sender_site_choice;

    if (!in_array($sender_site_choice, ["林口", "中壢", "其他"], true)) {
      redirect_with("send", "", "請選擇正確的送件地區");
    }
    if ($sender_site_choice === "其他") {
      if ($sender_site_other === "") {
        redirect_with("send", "", "送件地區選「其他」時請填寫內容");
      }
      $sender_site = $sender_site_other;
    }

    $doc_name = trim($_POST["doc_name"] ?? "");
    $doc_type = trim($_POST["doc_type"] ?? "");
    $doc_type_other = trim($_POST["doc_type_other"] ?? "");

    $intended_no = trim($_POST["intended_receiver_user_no"] ?? "");
    $intended_name = trim($_POST["intended_receiver_name"] ?? "");

    // 目的地區
    $dest_site_choice = trim($_POST["dest_site_choice"] ?? "");
    $dest_site_other  = trim($_POST["dest_site_other"] ?? "");
    $dest_site = $dest_site_choice;

    if (!in_array($dest_site_choice, ["林口", "中壢", "其他"], true)) {
      redirect_with("send", "", "請選擇正確的目的地區");
    }
    if ($dest_site_choice === "其他") {
      if ($dest_site_other === "") {
        redirect_with("send", "", "目的地區選「其他」時請填寫內容");
      }
      $dest_site = $dest_site_other;
    }

    $send_remark = trim($_POST["send_remark"] ?? "");
    $send_time = trim($_POST["send_time"] ?? "");

    if ($doc_name === "") {
      redirect_with("send", "", "請輸入文件名稱");
    }
    if (!in_array($doc_type, ["信件", "包裹", "其他"], true)) {
      redirect_with("send", "", "請選擇正確的文件類型");
    }
    if ($doc_type === "其他" && $doc_type_other === "") {
      redirect_with("send", "", "文件類型選「其他」時請填寫內容");
    }
    if ($intended_no === "" || $intended_name === "") {
      redirect_with("send", "", "請選擇簽收人");
    }

    // send_time 若沒填，用 NOW()
    $sendTimeExpr = "NOW()";
    $sendTimeParam = null;
    if ($send_time !== "") {
      $sendTimeExpr = "?";
      $sendTimeParam = $send_time;
    }

    $sql = "INSERT INTO `files_transfer`
    (sender_user_no, sender_name, sender_site, doc_name, doc_type, doc_type_other,
     intended_receiver_user_no, intended_receiver_name, dest_site,
     send_time, send_remark, status)
    VALUES
    (?, ?, ?, ?, ?, ?, ?, ?, ?, {$sendTimeExpr}, ?, 'SENT')";

    $bind = [
      $sender_user_no,
      $sender_name,
      $sender_site,
      $doc_name,
      $doc_type,
      ($doc_type === "其他") ? $doc_type_other : null,
      $intended_no,
      $intended_name,
      $dest_site,
    ];
    if ($sendTimeExpr === "?") $bind[] = $sendTimeParam;
    $bind[] = ($send_remark === "") ? null : $send_remark;

    $stmt = $pdo->prepare($sql);
    $stmt->execute($bind);

    redirect_with("send", "送件完成（已建立待取件文件）");
  }

  // 取件，可勾選多筆
  if ($action === "pick") {
    $picker_user_no = (string)$user["user_no"];
    $picker_name        = (string)$user["name"];

    $route = trim($_POST["route"] ?? "");
    $route_other = trim($_POST["route_other"] ?? "");
    $pick_time = trim($_POST["pick_time"] ?? "");
    $pick_remark = trim($_POST["pick_remark"] ?? "");
    $ids = $_POST["pick_ids"] ?? [];

    if (!is_array($ids) || count($ids) === 0) {
      redirect_with("pick", "", "請勾選要取件的文件");
    }
    if (!in_array($route, ["林口->中壢", "中壢->林口", "其他"], true)) {
      redirect_with("pick", "", "請選擇正確的來去地點");
    }
    if ($route === "其他" && $route_other === "") {
      redirect_with("pick", "", "來去地點選「其他」時請填寫內容");
    }

    // pick_time 若沒填，用 NOW()
    $pickTimeExpr = "NOW()";
    $pickTimeParam = null;
    if ($pick_time !== "") {
      $pickTimeExpr = "?";
      $pickTimeParam = $pick_time;
    }

    // 多筆用 IN
    // 依路徑限制可取件的「送件地區」，其他=不限制
    $allowedSenderSite = null;
    if ($route === "林口->中壢") {
      $allowedSenderSite = "林口";
    } else if ($route === "中壢->林口") {
      $allowedSenderSite = "中壢";
    }

    // 只允許更新 status = SENT 且 pick_time is null，避免重複取件
    // 多筆用 IN
    $placeholders = implode(",", array_fill(0, count($ids), "?"));

    $sql = "UPDATE `files_transfer`
            SET picker_user_no = ?,
                picker_name = ?,
                route = ?,
                route_other = ?,
                pick_time = {$pickTimeExpr},
                pick_remark = ?,
                status = 'PICKED'
            WHERE id IN ({$placeholders})
              AND status = 'SENT'
              AND pick_time IS NULL";

    // 若路徑不是"其他"，後端再加一道送件地區限制（避免繞過前端勾選）
    if ($allowedSenderSite !== null) {
      $sql .= " AND sender_site = ?";
    }

    $bind = [
      $picker_user_no,
      $picker_name,
      $route,
      ($route === "其他") ? $route_other : null,
    ];
    if ($pickTimeExpr === "?") $bind[] = $pickTimeParam;
    $bind[] = ($pick_remark === "") ? null : $pick_remark;

    // 先放 ids（對應 IN 的 ? ? ?）
    foreach ($ids as $id) {
      $bind[] = (int)$id;
    }

    // 最後才放 sender_site，對應 AND sender_site = ?
    if ($allowedSenderSite !== null) {
      $bind[] = $allowedSenderSite;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($bind);
    $affected = $stmt->rowCount();

    if ($affected <= 0) {
      redirect_with("pick", "", "取件失敗：可能已被取件或狀態不符 / 路徑不符合送件地區");
    }
      redirect_with("pick", "取件完成（已更新 {$affected} 筆）");
  }

  // 簽收
  if ($action === "receive") {
    $id = (int)($_POST["receive_id"] ?? 0);
      if ($id <= 0) {
        redirect_with("receive", "", "請選擇要簽收的文件");
      }

    $received_by_user_no = (string)$user["user_no"];
    $received_by_name        = (string)$user["name"];
    $receive_site_choice = trim($_POST["receive_site_choice"] ?? "");
    $receive_site_other  = trim($_POST["receive_site_other"] ?? "");
    $receive_site = $receive_site_choice;

    if (!in_array($receive_site_choice, ["林口", "中壢", "其他"], true)) {
      redirect_with("receive", "", "請選擇正確的簽收地區");
    }
    if ($receive_site_choice === "其他") {
      if ($receive_site_other === "") {
        redirect_with("receive", "", "簽收地區選「其他」時請填寫內容");
      }
      $receive_site = $receive_site_other;
    }

    // 規則：若簽收地區不是"其他"，只允許簽收"目的地區(dest_site) = 簽收地區"的文件，若簽收地區選「其他」，則不限制，全部可簽收
    if ($receive_site_choice !== "其他") {
      $chk = $pdo->prepare("SELECT dest_site FROM `files_transfer` WHERE id=? LIMIT 1");
      $chk->execute([$id]);
      $dest_site = (string)($chk->fetchColumn() ?? "");

      if ($dest_site === "") {
        redirect_with("receive", "", "簽收失敗：找不到該文件");
      }

      if (normalize_site($dest_site) !== normalize_site($receive_site_choice)) {
        redirect_with("receive", "", "簽收失敗：此文件的目的地區為「{$dest_site}」，不符合你選的簽收地區");
      }
    }

    $receive_time = trim($_POST["receive_time"] ?? "");
    $receive_remark = trim($_POST["receive_remark"] ?? "");

    $is_proxy = isset($_POST["is_proxy"]) ? 1 : 0;

    $final_no = trim($_POST["final_receiver_user_no"] ?? "");
    $final_name = trim($_POST["final_receiver_name"] ?? "");

    if ($is_proxy === 1) {
      if ($final_no === "" || $final_name === "") {
        redirect_with("receive", "", "代收時請選擇最終簽收人");
      }
    } else {
      // 非代收就清空
      $final_no = "";
      $final_name = "";
    }

    $receiveTimeExpr = "NOW()";
    $receiveTimeParam = null;
    if ($receive_time !== "") {
      $receiveTimeExpr = "?";
      $receiveTimeParam = $receive_time;
    }

    $newStatus = ($is_proxy === 1) ? "PROXY_RECEIVED" : "RECEIVED";

    // 只允許簽收：status = PICKED
    $sql = "UPDATE `files_transfer`
            SET received_by_user_no = ?,
                received_by_name = ?,
                receive_site = ?,
                receive_time = {$receiveTimeExpr},
                receive_remark = ?,
                is_proxy = ?,
                final_receiver_user_no = ?,
                final_receiver_name = ?,
                status = ?
            WHERE id = ?
              AND status = 'PICKED'";

    $bind = [
      $received_by_user_no,
      $received_by_name,
      $receive_site,
    ];
    if ($receiveTimeExpr === "?") $bind[] = $receiveTimeParam;
      $bind[] = ($receive_remark === "") ? null : $receive_remark;
      $bind[] = $is_proxy;
      $bind[] = ($is_proxy === 1) ? $final_no : null;
      $bind[] = ($is_proxy === 1) ? $final_name : null;
      $bind[] = $newStatus;
      $bind[] = $id;

      $stmt = $pdo->prepare($sql);
      $stmt->execute($bind);

      if ($stmt->rowCount() <= 0) {
        redirect_with("receive", "", "簽收失敗：可能尚未取件或狀態不符");
      }

      redirect_with("receive", "簽收完成（" . ($is_proxy ? "代收，待最終簽收" : "已結案") . "）");
  }

  // 最終簽收人簽收
  if ($action === "final_receive") {
    $id = (int)($_POST["final_id"] ?? 0);
    if ($id <= 0) {
      redirect_with("final", "", "請選擇要最終簽收的文件");
    }

    // 權限規則:預設只允許"最終簽收人本人"簽收；permission_level=2 也可代處理
    $meNo = (string)$user["user_no"];
    $sql = "UPDATE `files_transfer`
            SET final_receive_time = NOW(),
                status = 'COMPLETED'
            WHERE id = ?
              AND status = 'PROXY_RECEIVED'
              AND is_proxy = 1
              AND final_receive_time IS NULL
              AND (final_receiver_user_no = ? OR ?)";

    $isAdmin = ($permission === 2) ? 1 : 0;

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id, $meNo, $isAdmin]);
    if ($stmt->rowCount() <= 0) {
      redirect_with("final", "", "最終簽收失敗：你不是最終簽收人或狀態不符");
    }
      redirect_with("final", "最終簽收人簽收完成（結案）");
  }

  redirect_with("send", "", "未知的操作");
}

// GET:準備畫面資料
$tab = $_GET["tab"] ?? "send";
$self = basename($_SERVER["PHP_SELF"]);
$msg = $_GET["msg"] ?? "";
$error = $_GET["error"] ?? "";

// 取件清單:status=SENT，加入 sender_site 讓前端能依路徑過濾
$stmt = $pdo->prepare("SELECT id, doc_name, doc_type, intended_receiver_name, intended_receiver_user_no,
                              dest_site, send_time, sender_name, sender_user_no, sender_site
                       FROM `files_transfer`
                       WHERE status = 'SENT'
                       ORDER BY id DESC");
$stmt->execute();
$pickList = $stmt->fetchAll();

// 簽收清單:status=PICKED
$stmt = $pdo->prepare("SELECT id, doc_name, doc_type, intended_receiver_name, intended_receiver_user_no, dest_site, pick_time, picker_name, picker_user_no
                       FROM `files_transfer`
                       WHERE status = 'PICKED'
                       ORDER BY id DESC");
$stmt->execute();
$receiveList = $stmt->fetchAll();

// 最終簽收清單:status=PROXY_RECEIVED 且 final_receive_time is null
// 為了方便：顯示「屬於我」的 +（permission=2 顯示全部）
if ($permission === 2) {
  $stmt = $pdo->prepare("SELECT id, doc_name, doc_type, final_receiver_name, final_receiver_user_no, receive_time, received_by_name, received_by_user_no
                         FROM `files_transfer`
                         WHERE status = 'PROXY_RECEIVED'
                          AND is_proxy = 1
                          AND final_receive_time IS NULL
                         ORDER BY id DESC");
  $stmt->execute();
} else {
  $stmt = $pdo->prepare("SELECT id, doc_name, doc_type, final_receiver_name, final_receiver_user_no, receive_time, received_by_name, received_by_user_no
                         FROM `files_transfer`
                         WHERE status = 'PROXY_RECEIVED'
                          AND is_proxy = 1
                          AND final_receive_time IS NULL
                          AND final_receiver_user_no = ?
                         ORDER BY id DESC");
  $stmt->execute([(string)$user["user_no"]]);
}
$finalList = $stmt->fetchAll();

$now = date("Y-m-d H:i");
?>
<!doctype html>
<html lang="zh-Hant">
<head>
  <meta charset="utf-8">
  <title>文件往來登記</title>
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

/* Header box (回首頁/登入者/訊息/Tabs) */
.header-box { padding-top: 14px; }
.header-box p { margin: 8px 0; }
.header-box .msg { color: #0b7a2a; font-weight: 600; }
.header-box .err { color: #b00020; font-weight: 600; }

/* Tabs -> button style */
.tabs { 
  margin-top: 12px; 
  display: flex; 
  gap: 10px; 
  flex-wrap: wrap; 
}
.tabs a{
  display: inline-block;
  text-decoration: none;
  padding: 7px 14px;
  border-radius: 10px;
  background: rgba(255,255,255,0.8);
  color: #0f172a;
  border: 1px solid rgba(15, 23, 42, 0.12);
  transition: transform .05s ease-in-out;
}
.tabs a:hover { 
  transform: translateY(-1px); 
}
.tabs a.active{
  background: rgba(15, 23, 42, 0.92);
  color: #fff;
  border-color: rgba(255,255,255,0.18);
}

/* Forms */
label { 
  font-weight: 600; 
}

input[type="text"], select, textarea{
  border: 1px solid rgba(15,23,42,0.18);
  border-radius: 10px;
  padding: 8px 10px;
  outline: none;
}
textarea { 
  width: min(720px, 100%); 
}

button{
  border: 0;
  border-radius: 10px;
  padding: 7px 14px;
  cursor: pointer;
  background: rgba(15, 23, 42, 0.92);
  color: #fff;
}
button:hover{ 
  filter: brightness(1.05); 
}

.nav-bar {
  display: flex;
  align-items: center;
  gap: 10px;
  flex-wrap: wrap;
}
.nav-btn {
  display: inline-block;
  padding: 6px 14px;
  border-radius: 999px;
  text-decoration: none;
  font-size: 14px;
  font-weight: 600;
  background: rgba(255, 255, 255, 0.85);
  color: #0f172a;
  border:1px solid rgba(15,23,42,0.18);
  transition: filter .1s ease-in-out, transform .05s ease-in-out;
}
.nav-btn:hover {
  filter: brightness(1.05);
  transform: translateY(-1px);
}
.nav-btn:active {
  transform: translateY(0);
  filter: brightness(0.98);
}

/* Tables */
table{
  width: 100%;
  border-collapse: separate;
  border-spacing: 0;
  background: rgba(255,255,255,0.92);
  border-radius: 12px;
  overflow: hidden;
  box-shadow: 0 10px 24px rgba(0,0,0,0.12);
}
th, td{
  border: 0;
  padding: 10px 12px;
  border-bottom: 1px solid rgba(15,23,42,0.10);
}
thead th{
  background: rgba(15, 23, 42, 0.92);
  color: #fff;
  font-weight: 700;
}

tbody tr:nth-child(even) td{ 
  background: rgba(15, 23, 42, 0.03); 
}

tbody tr:last-child td{ 
  border-bottom: 0; 
}

.small{ 
  color: rgba(15,23,42,0.7); 
  }
</style>
</head>
<body>
  <h1 class="page-title"><span>文件往來登記</span></h1>
<div class="box header-box">
<p><a class="nav-btn" href="dashboard.php">回首頁</a></p>

  <p>登入者：<?= htmlspecialchars($user["name"]) ?>（<?= htmlspecialchars($user["user_no"]) ?>）｜地區：<?= htmlspecialchars($user["site"]) ?></p>

  <?php if ($msg): ?><p class="msg"><?= htmlspecialchars($msg) ?></p><?php endif; ?>
  <?php if ($error): ?><p class="err"><?= htmlspecialchars($error) ?></p><?php endif; ?>

  <div class="tabs">
    <a href="<?= htmlspecialchars($self) ?>?tab=send" class="<?= ($tab==="send") ? "active" : "" ?>">送件</a>
    <a href="<?= htmlspecialchars($self) ?>?tab=pick" class="<?= ($tab==="pick") ? "active" : "" ?>">取件</a>
    <a href="<?= htmlspecialchars($self) ?>?tab=receive" class="<?= ($tab==="receive") ? "active" : "" ?>">簽收</a>
    <a href="<?= htmlspecialchars($self) ?>?tab=final" class="<?= ($tab==="final") ? "active" : "" ?>">最終簽收</a>
  </div>
</div>


  <!-- 人員選擇器 -->
  <dialog id="userPicker">
    <h3>選擇人員</h3>
    <p class="small">可搜尋：登錄帳號 / 姓名 / 地別</p>
    <input type="text" id="pickerSearch" placeholder="輸入關鍵字..." style="width: 320px;">
    <div style="max-height: 360px; overflow: auto; margin-top: 10px;">
      <table>
        <thead>
          <tr>
            <th>登錄帳號</th>
            <th>姓名</th>
            <th>地別</th>
            <th>選擇</th>
          </tr>
        </thead>
        <tbody id="pickerBody">
          <?php foreach ($users as $u): ?>
            <tr class="picker-row"
                data-user-no="<?= htmlspecialchars($u["user_no"]) ?>"
                data-name="<?= htmlspecialchars($u["name"]) ?>"
                data-site="<?= htmlspecialchars($u["site"]) ?>">
              <td><?= htmlspecialchars($u["user_no"]) ?></td>
              <td><?= htmlspecialchars($u["name"]) ?></td>
              <td><?= htmlspecialchars($u["site"]) ?></td>
              <td><button type="button" onclick="chooseUser(this)">選擇</button></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <div style="margin-top: 10px;">
      <button type="button" onclick="closePicker()">關閉</button>
    </div>
  </dialog>

  <script>
    let pickerTargetNo = null;
    let pickerTargetName = null;

    function openPicker(targetNoId, targetNameId) {
      pickerTargetNo = document.getElementById(targetNoId);
      pickerTargetName = document.getElementById(targetNameId);
      document.getElementById('pickerSearch').value = '';
      filterPicker('');
      document.getElementById('userPicker').showModal();
    }

    function closePicker() {
      document.getElementById('userPicker').close();
    }

    function chooseUser(btn) {
      const tr = btn.closest('tr');
      const no = tr.getAttribute('data-user-no');
      const name = tr.getAttribute('data-name');
      if (pickerTargetNo) pickerTargetNo.value = no;
      if (pickerTargetName) pickerTargetName.value = name;
      closePicker();
    }

    function filterPicker(kw) {
      kw = (kw || '').toLowerCase();
      const rows = document.querySelectorAll('#pickerBody tr');
      rows.forEach(r => {
        const text = (r.getAttribute('data-user-no') + ' ' + r.getAttribute('data-name') + ' ' + r.getAttribute('data-site')).toLowerCase();
        r.style.display = text.includes(kw) ? '' : 'none';
      });
    }

    document.addEventListener('DOMContentLoaded', () => {
      const input = document.getElementById('pickerSearch');
      if (input) input.addEventListener('input', () => filterPicker(input.value));

      // 簽收:當選擇文件或勾選代收時，自動帶入該筆資料的"簽收人"當作最終簽收人預設值
      const receiveSel = document.querySelector('select[name="receive_id"]');
      if (receiveSel) receiveSel.addEventListener('change', syncFinalReceiverFromSelected);

      // 簽收:依簽收地區過濾可簽收文件，頁面載入時/切換時都要做
      const receiveSiteSel = document.getElementById('receive_site_choice');
      if (receiveSiteSel) {
        receiveSiteSel.addEventListener('change', () => filterReceiveList());
        // 初次載入:若預設有選中，要先過濾一次
        filterReceiveList();
      }

      // 初次載入若已勾選，也同步一次
      syncFinalReceiverFromSelected();
    });
function toggleOther(selectId, otherId) {
      const sel = document.getElementById(selectId);
      const other = document.getElementById(otherId);
      if (!sel || !other) return;

      const isOther = (sel.value === '其他');
      other.style.display = isOther ? '' : 'none';

      // 若有"其他"輸入框：選到其他就必填，否則清空且取消必填
      const input = other.querySelector('input');
      if (input) {
        input.required = isOther;
        if (!isOther) input.value = '';
      }
    }

    // 簽收：依"簽收地區"過濾"可簽收文件"，只顯示目的地區=簽收地區，選其他則全部顯示
    function normalizeSite(s) {
      s = (s || '').trim();
      if (s === '中壢') return '中壢';
      return s;
    }

    function filterReceiveList() {
      const siteSel = document.getElementById('receive_site_choice');
      const fileSel = document.getElementById('receive_id');
      if (!siteSel || !fileSel) return;

      const site = normalizeSite(siteSel.value);

      // 先記住所有 option，除了第一個 placeholder
      if (!fileSel._allOptions) {
        fileSel._allOptions = Array.from(fileSel.querySelectorAll('option')).slice(1).map(o => o.cloneNode(true));
      }
      const allOptions = fileSel._allOptions;

      // 清空目前選項，保留第一個 placeholder
      fileSel.querySelectorAll('option:not(:first-child)').forEach(o => o.remove());

      let nextOptions = [];
      if (site === '') {
        // 未選地區:先不顯示任何文件，避免誤選
        nextOptions = [];
      } else if (site === '其他') {
        // 其他:全部顯示
        nextOptions = allOptions;
      } else {
        // 正常地區:只顯示 dest_site 相同的
        nextOptions = allOptions.filter(opt => normalizeSite(opt.dataset.destSite || '') === site);
      }

      nextOptions.forEach(opt => fileSel.appendChild(opt.cloneNode(true)));
      // 重新過濾後，清空目前選擇，避免保留已被過濾掉的值
      fileSel.value = '';

      // 文件變動後，也同步一次最終簽收人預設值
      syncFinalReceiverFromSelected();
    }

    function syncFinalReceiverFromSelected() {
      const sel  = document.querySelector('select[name="receive_id"]');
      const cb   = document.getElementById('is_proxy');
      const noEl = document.getElementById('final_no');
      const nmEl = document.getElementById('final_name');
      if (!sel || !cb || !noEl || !nmEl) return;

      const opt = sel.options[sel.selectedIndex];
      if (!opt) return;

      if (cb.checked) {
        const intendedNo = opt.getAttribute('data-intended-no') || '';
        const intendedName = opt.getAttribute('data-intended-name') || '';
        // 勾選代收時:預設帶入該筆資料的"簽收人"，送件時記錄的 intended receiver
        if (intendedNo || intendedName) {
          noEl.value = intendedNo;
          nmEl.value = intendedName;
        }
      } else {
        // 沒勾代收就清空
        noEl.value = '';
        nmEl.value = '';
      }
    }

    function toggleProxy() {
      const cb = document.getElementById('is_proxy');
      const box = document.getElementById('finalBox');
      if (!cb || !box) return;
      box.style.display = cb.checked ? '' : 'none';
      syncFinalReceiverFromSelected();
    }

    // 取件清單依 route 過濾
    function filterPickList() {
      const route = document.getElementById('route')?.value || '';
      let allowedSite = '';

      if (route === '林口->中壢') allowedSite = '林口';
      else if (route === '中壢->林口') allowedSite = '中壢';
      else allowedSite = ''; // 其他/未選:全部

      const rows = document.querySelectorAll('tr[data-sender-site]');
      rows.forEach(row => {
        const senderSite = row.getAttribute('data-sender-site') || '';
        const show = (allowedSite === '') || (senderSite === allowedSite);

        row.style.display = show ? '' : 'none';

        const cb = row.querySelector('input[type="checkbox"]');
        if (!show && cb) cb.checked = false;
      });
    }

    document.addEventListener('DOMContentLoaded', () => {
      if (document.getElementById('route')) filterPickList();
    });
  </script>

  <?php if ($tab === "send"): ?>
    <div class="box">
      <h2>送件</h2>
      <form method="post">
        <input type="hidden" name="action" value="send">
        <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">

        <div>
          <label>送件人：</label>
          <input type="text" value="<?= htmlspecialchars($user["user_no"] . " " . $user["name"]) ?>" readonly>
        </div>
        <br>

        <div>
          <label>送件地區：</label>
          <select name="sender_site_choice" id="sender_site_choice"
                  onchange="toggleOther('sender_site_choice','sender_site_other_box')" required>
            <option value="">請選擇</option>
            <option value="林口" <?= ($user["site"]==="林口")?"selected":""; ?>>林口</option>
            <option value="中壢" <?= ($user["site"]==="中壢" || $user["site"]==="中壢")?"selected":""; ?>>中壢</option>
            <option value="其他">其他</option>
          </select>

          <span id="sender_site_other_box" style="display:none;">
            <input type="text" name="sender_site_other" placeholder="請填寫其他地區" style="width:220px;">
          </span>
        </div>
        <br>

        <div>
          <label>文件名稱：</label>
          <input type="text" name="doc_name" required style="width: 360px;">
        </div>
        <br>

        <div>
          <label>文件類型：</label>
          <select name="doc_type" id="doc_type" onchange="toggleOther('doc_type','doc_type_other_box')" required>
            <option value="">請選擇</option>
            <option value="信件">信件</option>
            <option value="包裹">包裹</option>
            <option value="其他">其他</option>
          </select>
          <span id="doc_type_other_box" style="display:none;">
            <input type="text" name="doc_type_other" placeholder="請填寫其他類型" style="width: 220px;">
          </span>
        </div>
        <br>

        <div>
          <label>簽收人（登錄帳號/姓名）：</label>
          <input type="text" name="intended_receiver_user_no" id="intended_no" placeholder="登錄帳號" readonly required>
          <input type="text" name="intended_receiver_name" id="intended_name" placeholder="姓名" readonly required>
          <button type="button" onclick="openPicker('intended_no','intended_name')">選擇簽收人</button>
        </div>
        <br>

        <div>
          <label>目的地區：</label>
          <select name="dest_site_choice" id="dest_site_choice" onchange="toggleOther('dest_site_choice','dest_site_other_box')" required>
            <option value="">請選擇</option>
            <option value="林口">林口</option>
            <option value="中壢">中壢</option>
            <option value="其他">其他</option>
          </select> 
          <span id="dest_site_other_box" style="display:none;">
            <input type="text" name="dest_site_other" placeholder="請填寫其他地區" style="width: 220px;">
          </span>
        </div>
        <br>

        <div>
          <label>送件時間（預設為當下）：</label>
          <input type="text" name="send_time" value="<?= htmlspecialchars($now) ?>">
        </div>
        <br>

        <div>
          <label>備註：</label><br>
          <textarea name="send_remark" rows="3" cols="60"></textarea>
        </div>
        <br>

        <button type="submit">確認送件</button>
      </form>
    </div>

  <?php elseif ($tab === "pick"): ?>
    <div class="box">
      <h2>取件</h2>
      <form method="post">
        <input type="hidden" name="action" value="pick">
        <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">

        <div>
          <label>取件人：</label>
          <input type="text" value="<?= htmlspecialchars($user["user_no"] . " " . $user["name"]) ?>" readonly>
        </div>
        <br>

        <div>
          <label>路徑：</label>
          <select name="route" id="route"
                  onchange="toggleOther('route','route_other_box'); filterPickList();"
                  required>
            <option value="">請選擇</option>
            <option value="林口->中壢">林口->中壢</option>
            <option value="中壢->林口">中壢->林口</option>
            <option value="其他">其他</option>
          </select>
          <span id="route_other_box" style="display:none;">
            <input type="text" name="route_other" placeholder="請填寫其他路線" style="width: 220px;">
          </span>
        </div>
        <br>

        <div>
          <label>取件時間（預設為當下）：</label>
          <input type="text" name="pick_time" value="<?= htmlspecialchars($now) ?>">
        </div>
        <br>

        <div>
          <label>取件備註：</label><br>
          <textarea name="pick_remark" rows="3" cols="60"></textarea>
        </div>
        <br>

        <h3>可取件文件</h3>
        <table>
          <thead>
            <tr>
              <th>勾選</th>
              <th>ID</th>
              <th>文件名稱</th>
              <th>類型</th>
              <th>預期簽收人</th>
              <th>目的地區</th>
              <th>送件時間</th>
              <th>送件人</th>
              <th>送件地區</th>
            </tr>
          </thead>
          <tbody>
            <?php if (count($pickList) === 0): ?>
              <tr><td colspan="9">目前沒有待取件文件</td></tr>
            <?php else: ?>
              <?php foreach ($pickList as $r): ?>
                <tr data-sender-site="<?= htmlspecialchars($r["sender_site"] ?? "") ?>">
                  <td><input type="checkbox" name="pick_ids[]" value="<?= (int)$r["id"] ?>"></td>
                  <td><?= (int)$r["id"] ?></td>
                  <td><?= htmlspecialchars($r["doc_name"]) ?></td>
                  <td><?= htmlspecialchars($r["doc_type"]) ?></td>
                  <td><?= htmlspecialchars($r["intended_receiver_user_no"] . " " . $r["intended_receiver_name"]) ?></td>
                  <td><?= htmlspecialchars($r["dest_site"]) ?></td>
                  <td><?= htmlspecialchars($r["send_time"]) ?></td>
                  <td><?= htmlspecialchars($r["sender_user_no"] . " " . $r["sender_name"]) ?></td>
                  <td><?= htmlspecialchars($r["sender_site"] ?? "") ?></td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>

        <br>
        <button type="submit">確認取件</button>
      </form>
    </div>

  <?php elseif ($tab === "receive"): ?>
    <div class="box">
      <h2>簽收</h2>
      <form method="post">
        <input type="hidden" name="action" value="receive">
        <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">

        <div>
          <label>簽收處理人：</label>
          <input type="text" value="<?= htmlspecialchars($user["user_no"] . " " . $user["name"]) ?>" readonly>
        </div>
        <br>

        <div>
          <label>簽收地區：</label>
          <select name="receive_site_choice" id="receive_site_choice"
                  onchange="toggleOther('receive_site_choice','receive_site_other_box'); filterReceiveList();" required>
            <option value="">請選擇</option>
            <option value="林口" <?= ($user["site"]==="林口")?"selected":""; ?>>林口</option>
            <option value="中壢" <?= ($user["site"]==="中壢" || $user["site"]==="中壢")?"selected":""; ?>>中壢</option>
            <option value="其他">其他</option>
          </select>

          <span id="receive_site_other_box" style="display:none;">
            <input type="text" name="receive_site_other" placeholder="請填寫其他地區" style="width: 220px;">
          </span>
        </div>
        <br>
        
        <div>
          <label>選擇要簽收的文件：</label>
          <select name="receive_id" id="receive_id" required>
            <option value="">請選擇</option>
            <?php foreach ($receiveList as $r): ?>
              <option value="<?= (int)$r["id"] ?>"
                      data-dest-site="<?= htmlspecialchars($r["dest_site"] ?? "") ?>"
                      data-intended-no="<?= htmlspecialchars($r["intended_receiver_user_no"]) ?>"
                      data-intended-name="<?= htmlspecialchars($r["intended_receiver_name"]) ?>">
                #<?= (int)$r["id"] ?>｜文件名稱：<?= htmlspecialchars($r["doc_name"]) ?>｜預計簽收人：<?= htmlspecialchars($r["intended_receiver_name"]) ?>｜目的：<?= htmlspecialchars($r["dest_site"] ?? "") ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <br>

        <div>
          <label>簽收時間（預設為當下）：</label>
          <input type="text" name="receive_time" value="<?= htmlspecialchars($now) ?>">
        </div>
        <br>

        <div>
          <label><input type="checkbox" name="is_proxy" id="is_proxy" onchange="toggleProxy()"> 是否為代收</label>
        </div>

        <div id="finalBox" style="display:none; margin-top: 10px;">
          <label>最終簽收人：</label>
          <input type="text" name="final_receiver_user_no" id="final_no" placeholder="登錄帳號" readonly>
          <input type="text" name="final_receiver_name" id="final_name" placeholder="姓名" readonly>
          <button type="button" onclick="openPicker('final_no','final_name')">選擇最終簽收人</button>
        </div>

        <br>
        <div>
          <label>簽收備註：</label><br>
          <textarea name="receive_remark" rows="3" cols="60"></textarea>
        </div>
        <br>

        <button type="submit">確認簽收</button>
      </form>
    </div>

  <?php elseif ($tab === "final"): ?>
    <div class="box">
      <h2>最終簽收（代收後結案）</h2>

      <form method="post">
        <input type="hidden" name="action" value="final_receive">
        <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">

        <div>
          <label>選擇要最終簽收的文件：</label>
          <select name="final_id" required>
            <option value="">請選擇</option>
            <?php foreach ($finalList as $r): ?>
              <option value="<?= (int)$r["id"] ?>">
                #<?= (int)$r["id"] ?>｜<?= htmlspecialchars($r["doc_name"]) ?>｜最終簽收人:<?= htmlspecialchars($r["final_receiver_name"]) ?>｜代收人:<?= htmlspecialchars($r["received_by_name"]) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <br>
        <button type="submit">確認最終簽收（結案）</button>
      </form>

      <h3 style="margin-top: 18px;">待最終簽收清單</h3>
      <table>
        <thead>
          <tr>
            <th>ID</th>
            <th>文件名稱</th>
            <th>類型</th>
            <th>最終簽收人</th>
            <th>代收時間</th>
            <th>代收人</th>
          </tr>
        </thead>
        <tbody>
          <?php if (count($finalList) === 0): ?>
            <tr><td colspan="6">目前沒有待最終簽收的文件</td></tr>
          <?php else: ?>
            <?php foreach ($finalList as $r): ?>
              <tr>
                <td><?= (int)$r["id"] ?></td>
                <td><?= htmlspecialchars($r["doc_name"]) ?></td>
                <td><?= htmlspecialchars($r["doc_type"]) ?></td>
                <td><?= htmlspecialchars($r["final_receiver_user_no"] . " " . $r["final_receiver_name"]) ?></td>
                <td><?= htmlspecialchars($r["receive_time"]) ?></td>
                <td><?= htmlspecialchars($r["received_by_user_no"] . " " . $r["received_by_name"]) ?></td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>

    </div>

  <?php else: ?>
    <div class="box">
      <p>未知分頁</p>
    </div>
  <?php endif; ?>
</body>
</html>