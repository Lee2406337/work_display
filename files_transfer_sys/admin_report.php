<?php
require_once __DIR__ . "/admin_init.php";
require_admin(2);

date_default_timezone_set('Asia/Taipei');
$today = date('Y-m-d');
$todayTime = date('Y-m-d H:i:s');

$titleMain = "文件往來系統";

// 狀態顯示轉譯
$statusDisplayMap = [
  "SENT"           => "進行中",
  "PICKED"         => "進行中",
  "RECEIVED"       => "完成",
  "PROXY_RECEIVED" => "代收中",
  "COMPLETED"      => "完成",
  "CANCELED"       => "取消",
  "ERROR"          => "錯誤",
];

// 權限等級轉譯
$levels = [
  1 => '一般用戶',
  2 => '管理者',
];

// 把登錄帳號、姓名組合成一個欄位
function format_person(?string $no, ?string $name): string {
  $no = trim((string)$no);
  $name = trim((string)$name);
  if ($no === "" && $name === "") return "";
  if ($no !== "" && $name !== "") return $no . " " . $name;
  return $no . $name;
}

// 取得 table 是否有某些欄位不存在
function get_table_columns(PDO $pdo, string $table): array {
  $cols = [];
  $stmt = $pdo->prepare("SHOW COLUMNS FROM `$table`");
  $stmt->execute();
  foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $cols[] = (string)$r["Field"];
  }
  return $cols;
}

$report = (string)($_GET["report"] ?? "files");  // files | users
$format = (string)($_GET["format"] ?? "");       // excel | csv | print
$doExport = ($format === "excel" || $format === "csv" || $format === "print");

// files_list 選日期篩選
$dateFrom = trim((string)($_GET["date_from"] ?? ""));
$dateTo   = trim((string)($_GET["date_to"] ?? ""));

function normalize_date_ymd(string $s): string {
  $s = trim($s);
  if ($s === "") return "";
  if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $s)) return "";
  $dt = DateTime::createFromFormat('Y-m-d', $s);
  if (!$dt) return "";
  return $dt->format('Y-m-d');
}

$dateFrom = normalize_date_ymd($dateFrom);
$dateTo   = normalize_date_ymd($dateTo);


// 1. 文件紀錄資料
function fetch_files_rows(PDO $pdo, array $statusDisplayMap, string $dateFrom = "", string $dateTo = ""): array {
  $where = [];
  $params = [];

  // 依送件時間篩選
  if ($dateFrom !== "") {
    $where[] = "send_time >= :from_dt";
    $params[":from_dt"] = $dateFrom . " 00:00:00";
  }
  if ($dateTo !== "") {
    $toNext = (new DateTime($dateTo))->modify('+1 day')->format('Y-m-d');
    $where[] = "send_time < :to_dt";
    $params[":to_dt"] = $toNext . " 00:00:00";
  }

  $sql = "SELECT
            id,
            doc_name, doc_type, doc_type_other,
            sender_user_no, sender_name, sender_site,
            intended_receiver_user_no, intended_receiver_name,
            picker_user_no, picker_name,
            received_by_user_no, received_by_name,
            final_receiver_user_no, final_receiver_name,
            send_time, pick_time, receive_time, final_receive_time,
            dest_site,
            receive_site,
            route, route_other,
            is_proxy,
            status
          FROM `files_transfer`
          ";
  if (!empty($where)) {
    $sql .= " WHERE " . implode(" AND ", $where);
  }
  $sql .= " ORDER BY id DESC";

  $stmt = $pdo->prepare($sql);
  $stmt->execute($params);

  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

  $out = [];
  foreach ($rows as $r) {
    $docType = (string)$r["doc_type"];
    if ($docType === "其他") {
      $docType = $docType . " - " . (string)($r["doc_type_other"] ?? "");
    }

    $route = (string)($r["route"] ?? "");
    if ($route === "其他") {
      $route = $route . " - " . (string)($r["route_other"] ?? "");
    }

    $sender = format_person($r["sender_user_no"] ?? "", $r["sender_name"] ?? "");
    $picker = format_person($r["picker_user_no"] ?? "", $r["picker_name"] ?? "");
    $receiver = format_person($r["received_by_user_no"] ?? "", $r["received_by_name"] ?? "");
    $finalReceiver = format_person($r["final_receiver_user_no"] ?? "", $r["final_receiver_name"] ?? "");

    $isProxy = (int)($r["is_proxy"] ?? 0);
    $proxyPerson = $isProxy ? $receiver : "";

    $statusRaw = (string)($r["status"] ?? "");
    $statusText = $statusDisplayMap[$statusRaw] ?? $statusRaw;

    $out[] = [
      "ID" => (int)$r["id"],
      "文件名稱" => (string)($r["doc_name"] ?? ""),
      "文件類型" => $docType,
      "送件人" => $sender,
      "取件人" => $picker,
      "簽收人" => $receiver,
      "最終簽收人" => $finalReceiver,
      "送件時間" => (string)($r["send_time"] ?? ""),
      "取件時間" => (string)($r["pick_time"] ?? ""),
      "簽收時間" => (string)($r["receive_time"] ?? ""),
      "最終簽收時間" => (string)($r["final_receive_time"] ?? ""),
      "送件地區" => (string)($r["sender_site"] ?? ""),
      "簽收地區" => (string)($r["receive_site"] ?? ""),
      "路徑" => $route,
      "是否代收" => $isProxy ? "是" : "否",
      "代收人" => $proxyPerson,
      "狀態" => $statusText,
    ];
  }

  return $out;
}

// 2. 人員資料
function fetch_users_rows(PDO $pdo): array {
  global $levels;

  $cols = get_table_columns($pdo, "user_data");
  $hasCreatedAt  = in_array("created_at", $cols, true);
  $hasDisabledAt = in_array("deactivate_at", $cols, true);

  $select = "id, user_no, name, site, permission_level";
  if ($hasCreatedAt)  $select .= ", created_at";
  if ($hasDisabledAt) $select .= ", deactivate_at";

  $sql = "SELECT {$select} FROM `user_data` ORDER BY id DESC";
  $stmt = $pdo->prepare($sql);
  $stmt->execute();
  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

  $out = [];
  foreach ($rows as $r) {

    $lv = (int)($r["permission_level"] ?? 0);
    $lvText = $levels[$lv] ?? (string)($r["permission_level"] ?? "");

    $dt = (string)($r["deactivate_at"] ?? NULL);
    $dtText = ($dt === "" || $dt === NULL) ? "/" : $dt;

    $out[] = [
      "ID" => (int)$r["id"],
      "登錄帳號" => (string)($r["user_no"] ?? ""),
      "姓名" => (string)($r["name"] ?? ""),
      "地區" => (string)($r["site"] ?? ""),
      "帳號類型" => $lvText,
      "帳號創建時間" => (string)($r["created_at"] ?? ""),
      "帳號停用時間" => $dtText,
    ];
  }

  return $out;
}

// 輸出 CSV
function output_csv(string $filename, array $rows): void {
  header("Content-Type: text/csv; charset=UTF-8");
  header("Content-Disposition: attachment; filename=\"" . $filename . "\"");

  // BOM for Excel (UTF-8)
  echo "\xEF\xBB\xBF";

  $fp = fopen("php://output", "w");
  if (!$fp) exit;

  if (!empty($rows)) {
    // header
    fputcsv($fp, array_keys($rows[0]));
    foreach ($rows as $r) {
      fputcsv($fp, array_values($r));
    }
  }
  fclose($fp);
  exit;
}

// 輸出 XLSX
function output_xlsx(string $filename, string $titleMain, string $subTitle, array $rows): void {
  $autoload = __DIR__ . "/vendor/autoload.php";
  if (!file_exists($autoload)) {
    http_response_code(500);
    echo "找不到 autoload: " . h($autoload);
    exit;
  }
  require_once $autoload;

  $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
  $sheet = $spreadsheet->getActiveSheet();
  $sheet->setTitle("Report");

  $colCount = !empty($rows) ? count($rows[0]) : 1;

  // 欄號轉字母
  $colLetter = function(int $colIndex): string {
    return \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex);
  };

  // 主標題 + 副標題，合併儲存格
  $lastCol = $colLetter($colCount);

  $sheet->setCellValue("A1", $titleMain);
  $sheet->mergeCells("A1:{$lastCol}1");

  $sheet->setCellValue("A2", $subTitle);
  $sheet->mergeCells("A2:{$lastCol}2");

  $startRow = 4;

  if (!empty($rows)) {
    $headers = array_keys($rows[0]);

    // 表頭
    $c = 1;
    foreach ($headers as $h) {
      $cell = $colLetter($c) . $startRow;
      $sheet->setCellValue($cell, (string)$h);
      $c++;
    }

    // 內容，強制字串
    $rIndex = $startRow + 1;
    foreach ($rows as $r) {
      $c = 1;
      foreach ($headers as $h) {
        $cell = $colLetter($c) . $rIndex;
        $sheet->setCellValueExplicit(
          $cell,
          (string)($r[$h] ?? ""),
          \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING
        );
        $c++;
      }
      $rIndex++;
    }

    // 自動欄寬
    for ($i = 1; $i <= $colCount; $i++) {
      $sheet->getColumnDimension($colLetter($i))->setAutoSize(true);
    }
  } else {
    $sheet->setCellValue("A{$startRow}", "無資料");
  }

  // 輸出
  header("Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet");
  header("Content-Disposition: attachment; filename=\"" . $filename . "\"");
  header("Cache-Control: max-age=0");

  $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
  $writer->save("php://output");
  exit;
}

// 輸出 Print
function output_print_html(string $titleMain, string $subTitle, array $rows): void {
  $colCount = !empty($rows) ? count($rows[0]) : 1;

  ?>
  <!doctype html>
  <html lang="zh-Hant">
  <head>
    <meta charset="utf-8">
    <title><?= h($subTitle) ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
      @page { size: A4 landscape; margin: 10mm; }
      body{
        font-family: system-ui,-apple-system,"Segoe UI",Roboto,"Noto Sans TC",Arial;
        color:#111827;
        margin:0;
        padding:0;
      }
      .header{
        margin: 0 0 10px 0;
      }
      .header h1{
        font-size:18px;
        margin:0 0 4px 0;
      }
      .header .sub{
        font-size:13px;
        margin:0;
        color:#374151;
      }
      table{
        width:100%;
        border-collapse:collapse;
        table-layout: fixed;
        word-break: break-word;
      }
      th, td{
        border:1px solid #cbd5e1;
        padding:6px;
        font-size:11px;
        vertical-align:top;
      }
      th{
        background:#f1f5f9;
        font-weight:700;
      }
      .muted{ color:#6b7280; }
      .no-print{ display:none; }
    </style>
  </head>
  <body onload="window.print()">
    <div class="header">
      <h1><?= h($titleMain) ?></h1>
      <p class="sub"><?= h($subTitle) ?></p>
    </div>

    <table>
      <thead>
        <tr>
          <?php if (!empty($rows)): ?>
            <?php foreach (array_keys($rows[0]) as $k): ?>
              <th><?= h((string)$k) ?></th>
            <?php endforeach; ?>
          <?php else: ?>
            <th>無資料</th>
          <?php endif; ?>
        </tr>
      </thead>
      <tbody>
      <?php if (!empty($rows)): ?>
        <?php foreach ($rows as $r): ?>
          <tr>
            <?php foreach ($r as $v): ?>
              <td><?= h((string)$v) ?></td>
            <?php endforeach; ?>
          </tr>
        <?php endforeach; ?>
      <?php else: ?>
        <tr><td>無資料</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </body>
  </html>
  <?php
  exit;
}

// 執行匯出
if ($doExport) {
  if ($report === "users") {
    $rows = fetch_users_rows($pdo);
    $sub = "人員資料 ($todayTime)";
    if ($format === "csv") {
      output_csv("users_data_{$today}.csv", $rows);
    } elseif ($format === "excel") {
      output_xlsx("users_data_{$today}.xlsx", $titleMain, $sub, $rows);
    } else { // print
      output_print_html($titleMain, $sub, $rows);
    }
  } else {
    $rows = fetch_files_rows($pdo, $statusDisplayMap, $dateFrom, $dateTo);
    $sub = "文件資料紀錄 ($todayTime)";
    if ($format === "csv") {
      output_csv("files_data_{$today}.csv", $rows);
    } elseif ($format === "excel") {
      output_xlsx("files_data_{$today}.xlsx", $titleMain, $sub, $rows);
    } else { // print
      output_print_html($titleMain, $sub, $rows);
    }
  }
}

// UI
?>
<!doctype html>
<html lang="zh-Hant">
<head>
  <meta charset="utf-8">
  <title>資料匯檔案出</title>
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
      cursor:pointer;
    }
    .btn:hover{ background:rgba(255,255,255,0.16); }
    .muted{ color:#9ca3af; }
    .grid{
      display:grid;
      grid-template-columns:repeat(2, minmax(0, 1fr));
      gap:14px;
      margin-top:14px;
    }
    @media (max-width:720px){
      .grid{ grid-template-columns:1fr; }
    }
    .box{
      border:1px solid rgba(255,255,255,0.10);
      border-radius:14px;
      padding:14px;
      background:rgba(255,255,255,0.04);
    }
    .title{
      font-size:16px;
      font-weight:800;
      margin:0 0 6px 0;
    }
    .actions{
      display:flex;
      gap:10px;
      flex-wrap:wrap;
      margin-top:10px;
    }
    .pill{
      display:inline-block;
      padding:4px 10px;
      border-radius:999px;
      border:1px solid rgba(255,255,255,0.16);
      color:#cbd5e1;
      font-size:12px;
    }
    .filter-row {
      margin-bottom: 10px;
    }

    .export-row {
      display: flex;
      gap: 10px;
    }
  </style>
</head>
<body>
<div class="wrap">
  <div class="card">
    <div class="top">
      <div>
        <h1 style="margin:0 0 6px 0;">📊 資料匯檔案出</h1>
        <div class="muted">表頭：<?= h($titleMain) ?></div>
      </div>
      <div style="display:flex; gap:10px; flex-wrap:wrap;">
        <a class="btn" href="admin_dashboard.php">回後台</a>
      </div>
    </div>

    <div class="grid">
      <div class="box">
        <div class="title">1. 匯出所有文件資料紀錄</div>
        <div class="muted">欄位包含：ID / 文件名稱 / 文件類型 / 送件人、取件、簽收、最終簽收人 / 送件、取件、簽收、最終簽收時間 / 送件、簽收地區 / 路徑 / 是否代收 / 代收人 / 狀態</div>
        
        <div class="actions">
          <form class="actions" method="get" action="admin_report.php">
            <input type="hidden" name="report" value="files">
            <div class="filter-row">
              <label class="muted" style="display:flex; align-items:center; gap:8px;">
                篩選輸出期間:
                <input type="date" name="date_from" value="<?= h($dateFrom) ?>">
                <span>~</span>
                <input type="date" name="date_to" value="<?= h($dateTo) ?>">
              </label>
            </div>
            <div class="export-row">
              <button class="btn" type="submit" name="format" value="excel">Excel</button>
              <button class="btn" type="submit" name="format" value="csv">CSV</button>
              <button class="btn" type="submit" name="format" value="print" formtarget="_blank">Print</button>
            </div>
          </form>
        </div>
        <div style="margin-top:10px;">
          <span class="pill">檔名參考：files_list_<?= $today ?>.xlsx / .csv</span>
        </div>
      </div>

      <div class="box">
        <div class="title">2. 匯出人員資料</div>
        <div class="muted">欄位包含：ID / 登錄帳號 / 姓名 / 地區 / 帳號類型 / 帳號創建時間 / 帳號停用時間</div>
        <div class="actions">
          <a class="btn" href="admin_report.php?report=users&format=excel">Excel</a>
          <a class="btn" href="admin_report.php?report=users&format=csv">CSV</a>
          <a class="btn" href="admin_report.php?report=users&format=print" target="_blank">Print（A4橫向）</a>
        </div>
        <div style="margin-top:10px;">
          <span class="pill">檔名參考：users_list_<?= $today ?>.xlsx / .csv</span>
        </div>
      </div>
    </div>

  </div>
</div>
</body>
</html>