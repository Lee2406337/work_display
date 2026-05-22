<?php
require_once __DIR__ . "/init.php";
require_login();
$user = $_SESSION["user"];
$permission = (int)($user["permission_level"] ?? 1);

// 篩選條件
$q = trim($_GET["q"] ?? "");
$status = trim($_GET["status"] ?? "");
$site = trim($_GET["site"] ?? "");
$date_from = trim($_GET["date_from"] ?? "");
$date_to = trim($_GET["date_to"] ?? "");

$statusDisplayMap = [
  "SENT"            => "進行中",
  "PICKED"          => "進行中",
  "RECEIVED"        => "完成",
  "PROXY_RECEIVED"  => "完成",
  "COMPLETED"       => "完成",
  "CANCELED"        => "取消",
  "ERROR"           => "錯誤",
];

// 組 SQL
$where = [];
$params = [];

if ($q !== "") {
  $where[] = "(doc_name LIKE :kw
               OR sender_user_no LIKE :kw OR sender_name LIKE :kw
               OR intended_receiver_user_no LIKE :kw OR intended_receiver_name LIKE :kw
               OR picker_user_no LIKE :kw OR picker_name LIKE :kw
               OR received_by_user_no LIKE :kw OR received_by_name LIKE :kw
               OR final_receiver_user_no LIKE :kw OR final_receiver_name LIKE :kw)";
  $params[":kw"] = "%{$q}%";
}

if ($status !== "") {
  $where[] = "status = :status";
  $params[":status"] = $status;
}

if ($site !== "") {
  // 送件地區/目的地區/簽收地區 任一符合
  $where[] = "(sender_site = :site OR dest_site = :site OR receive_site = :site)";
  $params[":site"] = $site;
}

if ($date_from !== "") {
  $where[] = "send_time >= :date_from";
  $params[":date_from"] = $date_from . " 00:00:00";
}
if ($date_to !== "") {
  $where[] = "send_time <= :date_to";
  $params[":date_to"] = $date_to . " 23:59:59";
}

// 權限 1:不限制文件紀錄
$sql = "SELECT *
        FROM `files_transfer`";
if ($where) $sql .= " WHERE " . implode(" AND ", $where);
$sql .= " ORDER BY id DESC LIMIT 100";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();
?>
<!doctype html>
<html lang="zh-Hant">
<head>
  <meta charset="utf-8">
  <title>查看登記紀錄</title>
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

/* Header box (回首頁/登入者/訊息/功能列) */
.header-box { 
  padding-top: 14px; 
}

.header-box p { 
  margin: 8px 0; 
}

.header-box .msg { 
  color: #0b7a2a; font-weight: 600; 
}

.header-box .err {
  color: #b00020; font-weight: 600; 
}

/* Links as buttons */
.navbar { 
  display:flex; 
  gap:10px; 
  flex-wrap:wrap; 
  margin-top:12px; 
}

.navbar a{
  display:inline-block;
  text-decoration:none;
  padding:10px 14px;
  border-radius:10px;
  background: rgba(255,255,255,0.8);
  color:#0f172a;
  border:1px solid rgba(15,23,42,0.12);
}
.navbar a:hover{ 
  filter: brightness(0.98); 
}

/* Forms */
label { 
  font-weight: 600; 
}

input[type="text"], input[type="password"], select, textarea{
  border: 1px solid rgba(15,23,42,0.18);
  border-radius: 10px;
  padding: 8px 10px;
  outline: none;
}

textarea { 
  width: min(720px, 100%); 
}

button, input[type="submit"]{
  border: 0;
  border-radius: 10px;
  padding: 10px 14px;
  cursor: pointer;
  background: rgba(15, 23, 42, 0.92);
  color: #fff;
}
button:hover, input[type="submit"]:hover{ 
  filter: brightness(1.05); 
}

/* Tables */
table{
  width: 100%;
  border-collapse: collapse;
  border-spacing: 0;
  background: rgba(255,255,255,0.92);
  border-radius: 12px;
  overflow: hidden;
  box-shadow: 0 10px 24px rgba(0,0,0,0.12);
}
th, td{
  border: 1px solid rgba(15,23,42,0.10);
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
  font-size: 12px; 
}

/* header-button */
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

.main-box{ 
  margin-top: 0; 
}
</style>
</head>
<body>
  <h1 class="page-title">
    <span>查看登記紀錄</span>
  </h1>

  <div class="box header-box">
    <div class="nav-bar">
      <a class="nav-btn" href="dashboard.php">回首頁</a>
      <a class="nav-btn" href="files_transfer_sys.php">
        去登記（送件 / 取件 / 簽收）
      </a>
    </div>
  </div>

<div class="box main-box">
<form method="get" style="margin: 12px 0;">
    <div>
      <label>關鍵字：</label>
      <input type="text" name="q" value="<?= h($q) ?>" placeholder="文件名/登錄帳號/姓名..." style="width: 280px;">
      &nbsp;&nbsp;

      <label>狀態：</label>
      <select name="status">
        <option value="">全部</option>
        <?php
          $statuses = ["SENT","PICKED","RECEIVED","PROXY_RECEIVED","COMPLETED"];
          foreach ($statuses as $st) {
              $sel = ($status === $st) ? "selected" : "";
              echo "<option value=\"".h($st)."\" {$sel}>".h($st)."</option>";
          }
        ?>
      </select>

      &nbsp;&nbsp;
      <label>地區：</label>
      <select name="site">
        <option value="">全部</option>
        <option value="林口" <?= ($site==="林口")?"selected":""; ?>>林口</option>
        <option value="中壢" <?= ($site==="中壢")?"selected":""; ?>>中壢</option>
      </select>
    </div>

    <div style="margin-top:8px;">
      <label>送件日期：</label>
      <input type="date" name="date_from" value="<?= h($date_from) ?>">
      ~
      <input type="date" name="date_to" value="<?= h($date_to) ?>">
      &nbsp;&nbsp;
      <button type="submit">查詢</button>
      <a href="files_list.php" style="margin-left:8px;">清除</a>
    </div>
  </form>

  <p class="small">顯示最近 100 筆（可依條件縮小範圍）｜總筆數：<?= count($rows) ?></p>

  <table>
    <thead>
      <tr>
        <th>ID</th>
        <th>文件名稱</th>
        <th>送件人</th>
        <th>取件人</th>
        <th>簽收人</th>
        <th>最終簽收人</th>
        <th>送件地區</th>
        <th>目的地區</th>
        <th>簽收地區</th>
        <th>送件時間</th>
        <th>取件時間</th>
        <th>簽收時間</th>
        <th>最終簽收時間</th>
        <th>狀態</th>
      </tr>
    </thead>
    <tbody>
      <?php if (count($rows) === 0): ?>
        <tr><td colspan="16">查無資料</td></tr>
      <?php else: ?>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td><?= (int)$r["id"] ?></td>
            <td>
              <?= h($r["doc_name"]) ?><br>
              <span class="small">
                類型:<?= h($r["doc_type"]) ?><?= ($r["doc_type"]==="其他" && $r["doc_type_other"]) ? "（".h($r["doc_type_other"])."）":"" ?>
              </span>
            </td>
            <td><?= h($r["sender_user_no"]." ".$r["sender_name"]) ?></td>
            <td>
              <?= h(($r["picker_user_no"]??"")." ".($r["picker_name"]??"")) ?><br>
              <span class="small">
                <?= h($r["route"] ?? "") ?><?= (($r["route"]??"")==="其他" && $r["route_other"]) ? "（".h($r["route_other"])."）" : "" ?>
              </span>
            </td>
            <td><?= h(($r["received_by_user_no"]??"")." ".($r["received_by_name"]??"")) ?></td>
            <td><?= h(($r["final_receiver_user_no"]??"")." ".($r["final_receiver_name"]??"")) ?></td>
            <td><?= h($r["sender_site"]) ?></td>
            <td><?= h($r["dest_site"]) ?></td>
            <td><?= h($r["receive_site"]) ?></td>
            <td><?= h($r["send_time"]) ?></td>
            <td><?= h($r["pick_time"]) ?></td>
            <td><?= h($r["receive_time"]) ?></td>
            <td><?= h($r["final_receive_time"]) ?></td> 
            <td><?= h($statusDisplayMap[$r["status"]] ?? $r["status"]) ?></td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
</div>
</div>

</body>
</html>
