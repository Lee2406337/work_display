<?php
require_once __DIR__ . "/init.php";
require_login();


// 權限等級:1/2
// 如 session 還沒存 permission_level，預設 1
$permission = (int)($_SESSION["user"]["permission_level"] ?? 1);

$q = trim($_GET['q'] ?? '');

$siteFilter = trim($_GET['site'] ?? '');      // '', '林口', '中壢'
$statusFilter = trim($_GET['status'] ?? '');  // '', 'active', 'inactive'

// 權限規則 level = 1不可看帳號狀態
$where = [];
$params = [];

// 隱藏administrator
$where[] = "user_no != :hide_admin";
$params[":hide_admin"] = "administrator";

if ($permission === 1) {
  $where[] = "is_active = 1";
}

if ($q !== "") {
  $where[] = "(user_no LIKE :kw1 OR name LIKE :kw2)";
  $params[":kw1"] = "%{$q}%";
  $params[":kw2"] = "%{$q}%";
}

//  區篩選
if ($siteFilter !== '' && in_array($siteFilter, ['林口', '中壢'], true)) {
  $where[] = "site = :site";
  $params[':site'] = $siteFilter;
}

// level=2 啟用狀態篩選
if ($permission === 2) {
  if ($statusFilter === 'active') {
    $where[] = "is_active = 1";
  } elseif ($statusFilter === 'inactive') {
    $where[] = "is_active = 0";
  }
}

$sql = "SELECT user_no, name, site, is_active, created_at, deactivate_at, id
        FROM user_data";
if ($where) {
  $sql .= " WHERE " . implode(" AND ", $where);
}

$sql .= " ORDER BY id DESC";


$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();
?>
<!doctype html>
<html lang="zh-Hant">
<head>
  <meta charset="utf-8">
  <title>使用者資料查詢</title>
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
  button:hover, input[type="submit"]:hover{ filter: brightness(1.05); }

  /*  header-button  */
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

  .main-box{ 
    margin-top: 0; 
  }

  .center{
    min-height: 100vh;
    display: flex;
    justify-content: center; /* 水平置中 */
  }
  .status-active { color: #2e7d32; font-weight: 500; }   /* 綠 */
  .status-inactive { color: #c62828; font-weight: 500; } /* 紅 */
</style>
</head>
<body>
  <h1 class="page-title"><span>使用者資料查詢</span></h1>
<div class="box header-box">


  <p><a class="nav-btn" href="dashboard.php">回首頁</a></p>
</div>

<div class="box main-box">
<form method="get" action="users_list.php" style="margin: 16px 0;">
    <label>
      搜尋：
      <input type="text" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="輸入登錄帳號或姓名">
    </label>
    <button type="submit">搜尋</button>
    <form method="get" style="margin: 8px 0;">
      <!-- 地區篩選 -->
      <label style="margin-left:10px;">地區：</label>
      <select name="site" onchange="this.form.submit()">
        <option value="">全部</option>
        <option value="林口" <?= ($siteFilter==='林口')?'selected':'' ?>>林口</option>
        <option value="中壢" <?= ($siteFilter==='中壢')?'selected':'' ?>>中壢</option>
      </select>

      <?php if ($permission === 2): ?>
        <!-- 啟用狀態篩選 -->
        <label style="margin-left:10px;">啟用狀態：</label>
        <select name="status" onchange="this.form.submit()">
          <option value="">全部</option>
          <option value="active" <?= ($statusFilter==='active')?'selected':'' ?>>啟用</option>
          <option value="inactive" <?= ($statusFilter==='inactive')?'selected':'' ?>>停用</option>
        </select>
      <?php endif; ?>

      <button type="submit" style="margin-left:10px;">套用</button>
      <a href="users_list.php" style="margin-left:10px;">清除篩選</a>
    </form>
  </form>

  <table border="1" cellpadding="8" cellspacing="0">
    <thead>
      <tr>
        <th>登錄帳號</th>
        <th>姓名</th>
        <th>地區</th>

        <?php if ($permission === 2): ?>
          <th>帳號狀態</th>
          <th>啟用/停用時間</th>
        <?php endif; ?>
      </tr>
    </thead>
    <tbody>
      <?php if (count($rows) === 0): ?>
        <tr>
          <td colspan="<?= ($permission === 3) ? 5 : 3 ?>">查無資料</td>
        </tr>
      <?php else: ?>
        <?php foreach ($rows as $r): ?>
          <?php
            $active = ((int)$r["is_active"] === 1);
            $timeValue = $active ? ($r["created_at"] ?? "") : ($r["deactivate_at"] ?? "");
            $timeText = $active ? ($timeValue) : ($timeValue);
          ?>
          <tr>
            <td><?= htmlspecialchars($r["user_no"]) ?></td>
            <td><?= htmlspecialchars($r["name"]) ?></td>
            <td><?= htmlspecialchars($r["site"]) ?></td>

            <?php if ($permission === 2): ?>
              <td>
                <?php if ($active): ?>
                  <span class="status-active">啟用</span>
                <?php else: ?>
                  <span class="status-inactive">停用</span>
                <?php endif; ?>
              </td>
              <td><?= htmlspecialchars((string)$timeText) ?></td>
            <?php endif; ?>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
</div>
</div>

</body>
</html>
