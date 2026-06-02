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

function dt_to_sql(?string $value): ?string {
    $value = trim((string)$value);

    if ($value === "") {
        return null;
    }

    return str_replace("T", " ", $value) . ":00";
}

function sql_to_dt_local(?string $value): string {
    $value = trim((string)$value);

    if ($value === "" || $value === "0000-00-00 00:00:00") {
        return "";
    }

    return substr(str_replace(" ", "T", $value), 0, 16);
}

function get_admin_users(PDO $pdo): array {
    $stmt = $pdo->prepare("
        SELECT user_no, name, site
        FROM user_data
        ORDER BY site, user_no
    ");

    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function get_user_map(array $users): array {
    $map = [];

    foreach ($users as $u) {
        $map[(string)$u["user_no"]] = $u;
    }

    return $map;
}

function get_person_name(array $userMap, string $userNo): string {
    return isset($userMap[$userNo]) ? (string)$userMap[$userNo]["name"] : "";
}

if (!isset($_SESSION["user"])) {
    json_out([
        "ok" => false,
        "message" => "尚未登入"
    ]);
}

if ((int)($_SESSION["user"]["permission_level"] ?? 1) < 2) {
    json_out([
        "ok" => false,
        "message" => "權限不足"
    ]);
}

$allowedStatus = [
    "SENT",
    "PICKED",
    "RECEIVED",
    "PROXY_RECEIVED",
    "COMPLETED",
    "CANCELED",
    "ERROR"
];

$statusDisplayMap = [
    "SENT" => "進行中",
    "PICKED" => "進行中",
    "RECEIVED" => "完成",
    "PROXY_RECEIVED" => "完成",
    "COMPLETED" => "完成",
    "CANCELED" => "取消",
    "ERROR" => "錯誤",
];

if ($_SERVER["REQUEST_METHOD"] === "GET") {
    $id = (int)($_GET["id"] ?? 0);

    if ($id > 0) {
        $stmt = $pdo->prepare("
            SELECT *
            FROM files_transfer
            WHERE id = ?
            LIMIT 1
        ");

        $stmt->execute([$id]);

        $file = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$file) {
            json_out([
                "ok" => false,
                "message" => "找不到文件資料"
            ]);
        }

        $file["send_time"] = sql_to_dt_local($file["send_time"] ?? "");
        $file["pick_time"] = sql_to_dt_local($file["pick_time"] ?? "");
        $file["receive_time"] = sql_to_dt_local($file["receive_time"] ?? "");
        $file["final_receive_time"] = sql_to_dt_local($file["final_receive_time"] ?? "");

        json_out([
            "ok" => true,
            "file" => $file,
            "users" => get_admin_users($pdo),
            "allowedStatus" => $allowedStatus
        ]);
    }

    $q = trim((string)($_GET["q"] ?? ""));
    $status = trim((string)($_GET["status"] ?? ""));
    $site = trim((string)($_GET["site"] ?? ""));
    $dateFrom = trim((string)($_GET["date_from"] ?? ""));
    $dateTo = trim((string)($_GET["date_to"] ?? ""));

    $where = [];
    $params = [];

    if ($q !== "") {
        $where[] = "(
            doc_name LIKE :kw_doc
            OR sender_user_no LIKE :kw_sender_no
            OR sender_name LIKE :kw_sender_name
            OR intended_receiver_user_no LIKE :kw_intended_no
            OR intended_receiver_name LIKE :kw_intended_name
            OR picker_user_no LIKE :kw_picker_no
            OR picker_name LIKE :kw_picker_name
            OR received_by_user_no LIKE :kw_receive_no
            OR received_by_name LIKE :kw_receive_name
            OR final_receiver_user_no LIKE :kw_final_no
            OR final_receiver_name LIKE :kw_final_name
        )";

        $kw = "%{$q}%";
        $params[":kw_doc"] = $kw;
        $params[":kw_sender_no"] = $kw;
        $params[":kw_sender_name"] = $kw;
        $params[":kw_intended_no"] = $kw;
        $params[":kw_intended_name"] = $kw;
        $params[":kw_picker_no"] = $kw;
        $params[":kw_picker_name"] = $kw;
        $params[":kw_receive_no"] = $kw;
        $params[":kw_receive_name"] = $kw;
        $params[":kw_final_no"] = $kw;
        $params[":kw_final_name"] = $kw;
    }

    if ($status !== "") {
        if (!in_array($status, $allowedStatus, true)) {
            json_out([
                "ok" => false,
                "message" => "狀態篩選不正確"
            ]);
        }

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

    try {
        $stmt = $pdo->prepare($sql);

        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }

        $stmt->execute();

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
    } catch (Throwable $e) {
        json_out([
            "ok" => false,
            "message" => "查詢失敗：" . $e->getMessage()
        ]);
    }
}

$data = json_decode(file_get_contents("php://input"), true);

if (!is_array($data)) {
    json_out([
        "ok" => false,
        "message" => "資料格式錯誤"
    ]);
}

$action = (string)($data["action"] ?? "");
$id = (int)($data["id"] ?? 0);

if ($action === "cancel") {
    if ($id <= 0) {
        json_out([
            "ok" => false,
            "message" => "ID 不正確"
        ]);
    }

    $stmt = $pdo->prepare("
        UPDATE files_transfer
        SET status = 'CANCELED'
        WHERE id = ?
    ");

    $stmt->execute([$id]);

    json_out([
        "ok" => true,
        "message" => "已取消文件"
    ]);
}

if ($action === "delete") {
    if ($id <= 0) {
        json_out([
            "ok" => false,
            "message" => "ID 不正確"
        ]);
    }

    $stmt = $pdo->prepare("
        DELETE FROM files_transfer
        WHERE id = ?
    ");

    $stmt->execute([$id]);

    json_out([
        "ok" => true,
        "message" => "已刪除文件"
    ]);
}

if ($action === "add" || $action === "update") {
    $users = get_admin_users($pdo);
    $userMap = get_user_map($users);

    $docName = trim((string)($data["doc_name"] ?? ""));
    $docType = trim((string)($data["doc_type"] ?? "信件"));
    $docTypeOther = trim((string)($data["doc_type_other"] ?? ""));

    $senderUserNo = trim((string)($data["sender_user_no"] ?? ""));
    $intendedReceiverUserNo = trim((string)($data["intended_receiver_user_no"] ?? ""));
    $pickerUserNo = trim((string)($data["picker_user_no"] ?? ""));
    $receivedByUserNo = trim((string)($data["received_by_user_no"] ?? ""));

    $senderSite = trim((string)($data["sender_site"] ?? ""));
    $destSite = trim((string)($data["dest_site"] ?? ""));
    $receiveSite = trim((string)($data["receive_site"] ?? ""));

    $route = trim((string)($data["route"] ?? ""));
    $routeOther = trim((string)($data["route_other"] ?? ""));

    $sendTime = dt_to_sql($data["send_time"] ?? "");
    $pickTime = dt_to_sql($data["pick_time"] ?? "");
    $receiveTime = dt_to_sql($data["receive_time"] ?? "");

    $isProxy = !empty($data["is_proxy"]) ? 1 : 0;
    $finalReceiverUserNo = trim((string)($data["final_receiver_user_no"] ?? ""));
    $finalReceiveTime = dt_to_sql($data["final_receive_time"] ?? "");

    $status = trim((string)($data["status"] ?? "SENT"));

    if ($docName === "") {
        json_out([
            "ok" => false,
            "message" => "請輸入文件名稱"
        ]);
    }

    if (!in_array($docType, ["信件", "包裹", "其他"], true)) {
        json_out([
            "ok" => false,
            "message" => "文件類型不正確"
        ]);
    }

    if ($docType === "其他" && $docTypeOther === "") {
        json_out([
            "ok" => false,
            "message" => "文件類型選「其他」時，請填寫其他類型"
        ]);
    }

    if ($senderUserNo === "" || !isset($userMap[$senderUserNo])) {
        json_out([
            "ok" => false,
            "message" => "請選擇有效的送件人"
        ]);
    }

    if ($intendedReceiverUserNo === "" || !isset($userMap[$intendedReceiverUserNo])) {
        json_out([
            "ok" => false,
            "message" => "請選擇有效的預計簽收人"
        ]);
    }

    if ($senderSite === "") {
        json_out([
            "ok" => false,
            "message" => "請輸入送件地區"
        ]);
    }

    if ($destSite === "") {
        json_out([
            "ok" => false,
            "message" => "請輸入目的地區"
        ]);
    }

    if (!in_array($status, $allowedStatus, true)) {
        json_out([
            "ok" => false,
            "message" => "狀態不正確"
        ]);
    }

    $senderName = get_person_name($userMap, $senderUserNo);
    $intendedReceiverName = get_person_name($userMap, $intendedReceiverUserNo);
    $pickerName = $pickerUserNo !== "" ? get_person_name($userMap, $pickerUserNo) : null;
    $receivedByName = $receivedByUserNo !== "" ? get_person_name($userMap, $receivedByUserNo) : null;

    if ($pickerUserNo !== "" && !isset($userMap[$pickerUserNo])) {
        json_out([
            "ok" => false,
            "message" => "取件人不存在"
        ]);
    }

    if ($receivedByUserNo !== "" && !isset($userMap[$receivedByUserNo])) {
        json_out([
            "ok" => false,
            "message" => "簽收人不存在"
        ]);
    }

    $finalReceiverName = null;

    if ($isProxy === 1) {
        if ($finalReceiverUserNo === "" || !isset($userMap[$finalReceiverUserNo])) {
            json_out([
                "ok" => false,
                "message" => "代收時請選擇有效的最終簽收人"
            ]);
        }

        $finalReceiverName = get_person_name($userMap, $finalReceiverUserNo);
    } else {
        $finalReceiverUserNo = null;
        $finalReceiveTime = null;
    }

    if ($action === "add") {
        $customId = trim((string)($data["custom_id"] ?? ""));

        if ($customId !== "" && !ctype_digit($customId)) {
            json_out([
                "ok" => false,
                "message" => "自訂 ID 必須是數字"
            ]);
        }

        if ($customId !== "") {
            $check = $pdo->prepare("
                SELECT COUNT(*)
                FROM files_transfer
                WHERE id = ?
            ");

            $check->execute([(int)$customId]);

            if ((int)$check->fetchColumn() > 0) {
                json_out([
                    "ok" => false,
                    "message" => "此 ID 已存在"
                ]);
            }

            $stmt = $pdo->prepare("
                INSERT INTO files_transfer
                (
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
                    dest_site,
                    receive_site,
                    route,
                    route_other,
                    send_time,
                    pick_time,
                    receive_time,
                    is_proxy,
                    final_receiver_user_no,
                    final_receiver_name,
                    final_receive_time,
                    status
                )
                VALUES
                (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                (int)$customId,
                $docName,
                $docType,
                ($docType === "其他") ? $docTypeOther : null,
                $senderUserNo,
                $senderName,
                $senderSite,
                $intendedReceiverUserNo,
                $intendedReceiverName,
                $pickerUserNo !== "" ? $pickerUserNo : null,
                $pickerName,
                $receivedByUserNo !== "" ? $receivedByUserNo : null,
                $receivedByName,
                $destSite,
                $receiveSite !== "" ? $receiveSite : null,
                $route !== "" ? $route : null,
                $route === "其他" ? $routeOther : null,
                $sendTime,
                $pickTime,
                $receiveTime,
                $isProxy,
                $finalReceiverUserNo,
                $finalReceiverName,
                $finalReceiveTime,
                $status
            ]);
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO files_transfer
                (
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
                    dest_site,
                    receive_site,
                    route,
                    route_other,
                    send_time,
                    pick_time,
                    receive_time,
                    is_proxy,
                    final_receiver_user_no,
                    final_receiver_name,
                    final_receive_time,
                    status
                )
                VALUES
                (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $docName,
                $docType,
                ($docType === "其他") ? $docTypeOther : null,
                $senderUserNo,
                $senderName,
                $senderSite,
                $intendedReceiverUserNo,
                $intendedReceiverName,
                $pickerUserNo !== "" ? $pickerUserNo : null,
                $pickerName,
                $receivedByUserNo !== "" ? $receivedByUserNo : null,
                $receivedByName,
                $destSite,
                $receiveSite !== "" ? $receiveSite : null,
                $route !== "" ? $route : null,
                $route === "其他" ? $routeOther : null,
                $sendTime,
                $pickTime,
                $receiveTime,
                $isProxy,
                $finalReceiverUserNo,
                $finalReceiverName,
                $finalReceiveTime,
                $status
            ]);
        }

        json_out([
            "ok" => true,
            "message" => "已新增文件資料"
        ]);
    }

    if ($action === "update") {
        if ($id <= 0) {
            json_out([
                "ok" => false,
                "message" => "ID 不正確"
            ]);
        }

        $stmt = $pdo->prepare("
            UPDATE files_transfer
            SET
                doc_name = ?,
                doc_type = ?,
                doc_type_other = ?,
                sender_user_no = ?,
                sender_name = ?,
                sender_site = ?,
                intended_receiver_user_no = ?,
                intended_receiver_name = ?,
                picker_user_no = ?,
                picker_name = ?,
                received_by_user_no = ?,
                received_by_name = ?,
                dest_site = ?,
                receive_site = ?,
                route = ?,
                route_other = ?,
                send_time = ?,
                pick_time = ?,
                receive_time = ?,
                is_proxy = ?,
                final_receiver_user_no = ?,
                final_receiver_name = ?,
                final_receive_time = ?,
                status = ?
            WHERE id = ?
        ");

        $stmt->execute([
            $docName,
            $docType,
            ($docType === "其他") ? $docTypeOther : null,
            $senderUserNo,
            $senderName,
            $senderSite,
            $intendedReceiverUserNo,
            $intendedReceiverName,
            $pickerUserNo !== "" ? $pickerUserNo : null,
            $pickerName,
            $receivedByUserNo !== "" ? $receivedByUserNo : null,
            $receivedByName,
            $destSite,
            $receiveSite !== "" ? $receiveSite : null,
            $route !== "" ? $route : null,
            $route === "其他" ? $routeOther : null,
            $sendTime,
            $pickTime,
            $receiveTime,
            $isProxy,
            $finalReceiverUserNo,
            $finalReceiverName,
            $finalReceiveTime,
            $status,
            $id
        ]);

        json_out([
            "ok" => true,
            "message" => "已更新文件資料"
        ]);
    }
}

json_out([
    "ok" => false,
    "message" => "未知操作"
]);