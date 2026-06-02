<?php
header("Access-Control-Allow-Origin: http://localhost:5173");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Content-Type: application/json; charset=utf-8");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    exit;
}

require_once __DIR__ . "/init.php";

function json_out(array $data): void {
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

if (!isset($_SESSION["user"])) {
    json_out([
        "ok" => false,
        "message" => "尚未登入"
    ]);
}

$statusDisplayMap = [
    "SENT" => "進行中",
    "PICKED" => "進行中",
    "RECEIVED" => "完成",
    "PROXY_RECEIVED" => "完成",
    "COMPLETED" => "完成",
    "CANCELED" => "取消",
    "ERROR" => "錯誤",
];

$q = trim((string)($_GET["q"] ?? ""));
$status = trim((string)($_GET["status"] ?? ""));
$site = trim((string)($_GET["site"] ?? ""));
$dateFrom = trim((string)($_GET["date_from"] ?? ""));
$dateTo = trim((string)($_GET["date_to"] ?? ""));

$where = [];
$params = [];

if ($q !== "") {
    $where[] = "(
        doc_name LIKE :kw
        OR sender_user_no LIKE :kw
        OR sender_name LIKE :kw
        OR intended_receiver_user_no LIKE :kw
        OR intended_receiver_name LIKE :kw
        OR picker_user_no LIKE :kw
        OR picker_name LIKE :kw
        OR received_by_user_no LIKE :kw
        OR received_by_name LIKE :kw
        OR final_receiver_user_no LIKE :kw
        OR final_receiver_name LIKE :kw
    )";

    $params[":kw"] = "%{$q}%";
}

if ($status !== "") {
    $where[] = "status = :status";
    $params[":status"] = $status;
}

if ($site !== "") {
    $where[] = "(sender_site = :site OR dest_site = :site OR receive_site = :site)";
    $params[":site"] = $site;
}

if ($dateFrom !== "") {
    $where[] = "send_time >= :date_from";
    $params[":date_from"] = $dateFrom . " 00:00:00";
}

if ($dateTo !== "") {
    $where[] = "send_time <= :date_to";
    $params[":date_to"] = $dateTo . " 23:59:59";
}

$sql = "
    SELECT
        id,
        doc_name,
        doc_type,
        doc_type_other,
        sender_user_no,
        sender_name,
        sender_site,
        intended_receiver_user_no,
        intended_receiver_name,
        picker_user_no,
        picker_name,
        received_by_user_no,
        received_by_name,
        final_receiver_user_no,
        final_receiver_name,
        dest_site,
        receive_site,
        route,
        route_other,
        is_proxy,
        send_time,
        pick_time,
        receive_time,
        final_receive_time,
        status
    FROM files_transfer
";

if ($where) {
    $sql .= " WHERE " . implode(" AND ", $where);
}

$sql .= " ORDER BY id DESC LIMIT 100";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$data = [];

foreach ($rows as $row) {
    $statusCode = (string)($row["status"] ?? "");

    $data[] = [
        "id" => (int)$row["id"],
        "doc_name" => (string)($row["doc_name"] ?? ""),
        "doc_type" => (string)($row["doc_type"] ?? ""),
        "doc_type_other" => (string)($row["doc_type_other"] ?? ""),

        "sender_user_no" => (string)($row["sender_user_no"] ?? ""),
        "sender_name" => (string)($row["sender_name"] ?? ""),
        "sender_site" => (string)($row["sender_site"] ?? ""),

        "intended_receiver_user_no" => (string)($row["intended_receiver_user_no"] ?? ""),
        "intended_receiver_name" => (string)($row["intended_receiver_name"] ?? ""),

        "picker_user_no" => (string)($row["picker_user_no"] ?? ""),
        "picker_name" => (string)($row["picker_name"] ?? ""),

        "received_by_user_no" => (string)($row["received_by_user_no"] ?? ""),
        "received_by_name" => (string)($row["received_by_name"] ?? ""),

        "final_receiver_user_no" => (string)($row["final_receiver_user_no"] ?? ""),
        "final_receiver_name" => (string)($row["final_receiver_name"] ?? ""),

        "dest_site" => (string)($row["dest_site"] ?? ""),
        "receive_site" => (string)($row["receive_site"] ?? ""),

        "route" => (string)($row["route"] ?? ""),
        "route_other" => (string)($row["route_other"] ?? ""),

        "is_proxy" => (int)($row["is_proxy"] ?? 0),

        "send_time" => (string)($row["send_time"] ?? ""),
        "pick_time" => (string)($row["pick_time"] ?? ""),
        "receive_time" => (string)($row["receive_time"] ?? ""),
        "final_receive_time" => (string)($row["final_receive_time"] ?? ""),

        "status" => $statusCode,
        "status_text" => $statusDisplayMap[$statusCode] ?? $statusCode,
    ];
}

json_out([
    "ok" => true,
    "data" => $data
]);