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

$user = $_SESSION["user"];
$userNo = (string)($user["user_no"] ?? "");
$permission = (int)($user["permission_level"] ?? 1);

$statusMap = [
    "SENT" => "待取件",
    "PICKED" => "已取件，不能取消",
    "RECEIVED" => "已簽收",
    "PROXY_RECEIVED" => "代收中",
    "COMPLETED" => "已完成",
    "CANCELED" => "已取消",
    "ERROR" => "錯誤",
];

if ($_SERVER["REQUEST_METHOD"] === "GET") {
    $where = [];
    $params = [];

    // 只顯示進行中且可能和抽單有關的文件
    $where[] = "status IN ('SENT', 'PICKED')";

    // 一般使用者只看自己的送件
    if ($permission < 2) {
        $where[] = "sender_user_no = ?";
        $params[] = $userNo;
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
            dest_site,
            picker_user_no,
            picker_name,
            send_time,
            pick_time,
            status
        FROM files_transfer
        WHERE " . implode(" AND ", $where) . "
        ORDER BY id DESC
        LIMIT 100
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $data = [];

    foreach ($rows as $row) {
        $status = (string)($row["status"] ?? "");

        $canCancel =
            $status === "SENT" &&
            empty($row["pick_time"]) &&
            (
                $permission >= 2 ||
                (string)$row["sender_user_no"] === $userNo
            );

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
            "dest_site" => (string)($row["dest_site"] ?? ""),
            "picker_user_no" => (string)($row["picker_user_no"] ?? ""),
            "picker_name" => (string)($row["picker_name"] ?? ""),
            "send_time" => (string)($row["send_time"] ?? ""),
            "pick_time" => (string)($row["pick_time"] ?? ""),
            "status" => $status,
            "status_text" => $statusMap[$status] ?? $status,
            "can_cancel" => $canCancel,
        ];
    }

    json_out([
        "ok" => true,
        "data" => $data
    ]);
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $data = json_decode(file_get_contents("php://input"), true);

    if (!is_array($data)) {
        json_out([
            "ok" => false,
            "message" => "資料格式錯誤"
        ]);
    }

    $id = (int)($data["id"] ?? 0);

    if ($id <= 0) {
        json_out([
            "ok" => false,
            "message" => "文件 ID 不正確"
        ]);
    }

    // 先確認文件是否存在
    $stmt = $pdo->prepare("
        SELECT
            id,
            sender_user_no,
            status,
            pick_time
        FROM files_transfer
        WHERE id = ?
        LIMIT 1
    ");

    $stmt->execute([$id]);
    $file = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$file) {
        json_out([
            "ok" => false,
            "message" => "找不到文件"
        ]);
    }

    // 一般使用者只能取消自己的文件
    if ($permission < 2 && (string)$file["sender_user_no"] !== $userNo) {
        json_out([
            "ok" => false,
            "message" => "你只能取消自己送件的文件"
        ]);
    }

    // 只有尚未被取件的 SENT 可以取消
    if ((string)$file["status"] !== "SENT" || !empty($file["pick_time"])) {
        json_out([
            "ok" => false,
            "message" => "此文件已被取件或狀態不符，無法取消"
        ]);
    }

    $stmt = $pdo->prepare("
        UPDATE files_transfer
        SET status = 'CANCELED'
        WHERE id = ?
          AND status = 'SENT'
          AND pick_time IS NULL
    ");

    $stmt->execute([$id]);

    if ($stmt->rowCount() <= 0) {
        json_out([
            "ok" => false,
            "message" => "取消失敗，文件可能已被取件"
        ]);
    }

    json_out([
        "ok" => true,
        "message" => "抽單成功，文件已取消"
    ]);
}

json_out([
    "ok" => false,
    "message" => "不支援的請求方法"
]);