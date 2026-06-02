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

if (!isset($_SESSION["user"])) {
    echo json_encode([
        "ok" => false,
        "message" => "尚未登入"
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$user = $_SESSION["user"];
$permission = (int)($user["permission_level"] ?? 1);

function json_out(array $data): void {
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function normalize_site(string $site): string {
    $site = trim($site);
    if ($site === "中壢") return "中壢";
    if ($site === "林口") return "林口";
    return $site;
}

function get_users(PDO $pdo, int $permission): array {
    if ($permission === 1) {
        $stmt = $pdo->prepare("
            SELECT user_no, name, site, is_active
            FROM user_data
            WHERE is_active = 1
            ORDER BY id DESC
        ");
        $stmt->execute();
    } else {
        $stmt = $pdo->prepare("
            SELECT user_no, name, site, is_active
            FROM user_data
            ORDER BY id DESC
        ");
        $stmt->execute();
    }

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function get_user_map(array $users): array {
    $map = [];

    foreach ($users as $u) {
        $map[(string)$u["user_no"]] = $u;
    }

    return $map;
}

function get_pick_list(PDO $pdo): array {
    $stmt = $pdo->prepare("
        SELECT
            id,
            doc_name,
            doc_type,
            intended_receiver_name,
            intended_receiver_user_no,
            dest_site,
            send_time,
            sender_name,
            sender_user_no,
            sender_site
        FROM files_transfer
        WHERE status = 'SENT'
        ORDER BY id DESC
    ");
    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function get_receive_list(PDO $pdo): array {
    $stmt = $pdo->prepare("
        SELECT
            id,
            doc_name,
            doc_type,
            intended_receiver_name,
            intended_receiver_user_no,
            dest_site,
            pick_time,
            picker_name,
            picker_user_no
        FROM files_transfer
        WHERE status = 'PICKED'
        ORDER BY id DESC
    ");
    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function get_final_list(PDO $pdo, array $user, int $permission): array {
    if ($permission === 2) {
        $stmt = $pdo->prepare("
            SELECT
                id,
                doc_name,
                doc_type,
                final_receiver_name,
                final_receiver_user_no,
                receive_time,
                received_by_name,
                received_by_user_no
            FROM files_transfer
            WHERE status = 'PROXY_RECEIVED'
              AND is_proxy = 1
              AND final_receive_time IS NULL
            ORDER BY id DESC
        ");
        $stmt->execute();
    } else {
        $stmt = $pdo->prepare("
            SELECT
                id,
                doc_name,
                doc_type,
                final_receiver_name,
                final_receiver_user_no,
                receive_time,
                received_by_name,
                received_by_user_no
            FROM files_transfer
            WHERE status = 'PROXY_RECEIVED'
              AND is_proxy = 1
              AND final_receive_time IS NULL
              AND final_receiver_user_no = ?
            ORDER BY id DESC
        ");
        $stmt->execute([(string)$user["user_no"]]);
    }

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

if ($_SERVER["REQUEST_METHOD"] === "GET") {
    $users = get_users($pdo, $permission);

    json_out([
        "ok" => true,
        "user" => $user,
        "users" => $users,
        "pickList" => get_pick_list($pdo),
        "receiveList" => get_receive_list($pdo),
        "finalList" => get_final_list($pdo, $user, $permission),
        "now" => date("Y-m-d\TH:i")
    ]);
}

$data = json_decode(file_get_contents("php://input"), true);

if (!is_array($data)) {
    json_out([
        "ok" => false,
        "message" => "資料格式錯誤"
    ]);
}

$action = (string)($data["action"] ?? "");
$users = get_users($pdo, $permission);
$userMap = get_user_map($users);

if ($action === "send") {
    $senderUserNo = (string)$user["user_no"];
    $senderName = (string)$user["name"];

    $senderSiteChoice = trim((string)($data["sender_site_choice"] ?? ""));
    $senderSiteOther = trim((string)($data["sender_site_other"] ?? ""));
    $senderSite = $senderSiteChoice;

    if (!in_array($senderSiteChoice, ["林口", "中壢", "其他"], true)) {
        json_out(["ok" => false, "message" => "請選擇正確的送件地區"]);
    }

    if ($senderSiteChoice === "其他") {
        if ($senderSiteOther === "") {
            json_out(["ok" => false, "message" => "送件地區選「其他」時請填寫內容"]);
        }

        $senderSite = $senderSiteOther;
    }

    $docName = trim((string)($data["doc_name"] ?? ""));
    $docType = trim((string)($data["doc_type"] ?? ""));
    $docTypeOther = trim((string)($data["doc_type_other"] ?? ""));

    $intendedNo = trim((string)($data["intended_receiver_user_no"] ?? ""));
    $destSiteChoice = trim((string)($data["dest_site_choice"] ?? ""));
    $destSiteOther = trim((string)($data["dest_site_other"] ?? ""));
    $destSite = $destSiteChoice;

    $sendRemark = trim((string)($data["send_remark"] ?? ""));
    $sendTime = trim((string)($data["send_time"] ?? ""));

    if ($docName === "") {
        json_out(["ok" => false, "message" => "請輸入文件名稱"]);
    }

    if (!in_array($docType, ["信件", "包裹", "其他"], true)) {
        json_out(["ok" => false, "message" => "請選擇正確的文件類型"]);
    }

    if ($docType === "其他" && $docTypeOther === "") {
        json_out(["ok" => false, "message" => "文件類型選「其他」時請填寫內容"]);
    }

    if ($intendedNo === "" || !isset($userMap[$intendedNo])) {
        json_out(["ok" => false, "message" => "請選擇有效的簽收人"]);
    }

    if (!in_array($destSiteChoice, ["林口", "中壢", "其他"], true)) {
        json_out(["ok" => false, "message" => "請選擇正確的目的地區"]);
    }

    if ($destSiteChoice === "其他") {
        if ($destSiteOther === "") {
            json_out(["ok" => false, "message" => "目的地區選「其他」時請填寫內容"]);
        }

        $destSite = $destSiteOther;
    }

    $intendedName = (string)$userMap[$intendedNo]["name"];

    $sendTimeExpr = "NOW()";
    $sendTimeParam = null;

    if ($sendTime !== "") {
        $sendTimeExpr = "?";
        $sendTimeParam = str_replace("T", " ", $sendTime) . ":00";
    }

    $sql = "
        INSERT INTO files_transfer
        (
            sender_user_no,
            sender_name,
            sender_site,
            doc_name,
            doc_type,
            doc_type_other,
            intended_receiver_user_no,
            intended_receiver_name,
            dest_site,
            send_time,
            send_remark,
            status
        )
        VALUES
        (?, ?, ?, ?, ?, ?, ?, ?, ?, {$sendTimeExpr}, ?, 'SENT')
    ";

    $bind = [
        $senderUserNo,
        $senderName,
        $senderSite,
        $docName,
        $docType,
        ($docType === "其他") ? $docTypeOther : null,
        $intendedNo,
        $intendedName,
        $destSite
    ];

    if ($sendTimeExpr === "?") {
        $bind[] = $sendTimeParam;
    }

    $bind[] = ($sendRemark === "") ? null : $sendRemark;

    $stmt = $pdo->prepare($sql);
    $stmt->execute($bind);

    json_out([
        "ok" => true,
        "message" => "送件完成"
    ]);
}

if ($action === "pick") {
    $pickerUserNo = (string)$user["user_no"];
    $pickerName = (string)$user["name"];

    $route = trim((string)($data["route"] ?? ""));
    $routeOther = trim((string)($data["route_other"] ?? ""));
    $pickTime = trim((string)($data["pick_time"] ?? ""));
    $pickRemark = trim((string)($data["pick_remark"] ?? ""));
    $ids = $data["pick_ids"] ?? [];

    if (!is_array($ids) || count($ids) === 0) {
        json_out(["ok" => false, "message" => "請勾選要取件的文件"]);
    }

    if (!in_array($route, ["林口->中壢", "中壢->林口", "其他"], true)) {
        json_out(["ok" => false, "message" => "請選擇正確的來去地點"]);
    }

    if ($route === "其他" && $routeOther === "") {
        json_out(["ok" => false, "message" => "來去地點選「其他」時請填寫內容"]);
    }

    $pickTimeExpr = "NOW()";
    $pickTimeParam = null;

    if ($pickTime !== "") {
        $pickTimeExpr = "?";
        $pickTimeParam = str_replace("T", " ", $pickTime) . ":00";
    }

    $allowedSenderSite = null;

    if ($route === "林口->中壢") {
        $allowedSenderSite = "林口";
    } elseif ($route === "中壢->林口") {
        $allowedSenderSite = "中壢";
    }

    $ids = array_values(array_filter(array_map("intval", $ids), function ($id) {
        return $id > 0;
    }));

    if (count($ids) === 0) {
        json_out(["ok" => false, "message" => "文件 ID 不正確"]);
    }

    $placeholders = implode(",", array_fill(0, count($ids), "?"));

    $sql = "
        UPDATE files_transfer
        SET
            picker_user_no = ?,
            picker_name = ?,
            route = ?,
            route_other = ?,
            pick_time = {$pickTimeExpr},
            pick_remark = ?,
            status = 'PICKED'
        WHERE id IN ({$placeholders})
          AND status = 'SENT'
          AND pick_time IS NULL
    ";

    if ($allowedSenderSite !== null) {
        $sql .= " AND sender_site = ?";
    }

    $bind = [
        $pickerUserNo,
        $pickerName,
        $route,
        ($route === "其他") ? $routeOther : null
    ];

    if ($pickTimeExpr === "?") {
        $bind[] = $pickTimeParam;
    }

    $bind[] = ($pickRemark === "") ? null : $pickRemark;

    foreach ($ids as $id) {
        $bind[] = $id;
    }

    if ($allowedSenderSite !== null) {
        $bind[] = $allowedSenderSite;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($bind);

    $affected = $stmt->rowCount();

    if ($affected <= 0) {
        json_out([
            "ok" => false,
            "message" => "取件失敗：可能已被取件、狀態不符，或路徑不符合送件地區"
        ]);
    }

    json_out([
        "ok" => true,
        "message" => "取件完成，已更新 {$affected} 筆"
    ]);
}

if ($action === "receive") {
    $id = (int)($data["receive_id"] ?? 0);

    if ($id <= 0) {
        json_out(["ok" => false, "message" => "請選擇要簽收的文件"]);
    }

    $receivedByUserNo = (string)$user["user_no"];
    $receivedByName = (string)$user["name"];

    $receiveSiteChoice = trim((string)($data["receive_site_choice"] ?? ""));
    $receiveSiteOther = trim((string)($data["receive_site_other"] ?? ""));
    $receiveSite = $receiveSiteChoice;

    if (!in_array($receiveSiteChoice, ["林口", "中壢", "其他"], true)) {
        json_out(["ok" => false, "message" => "請選擇正確的簽收地區"]);
    }

    if ($receiveSiteChoice === "其他") {
        if ($receiveSiteOther === "") {
            json_out(["ok" => false, "message" => "簽收地區選「其他」時請填寫內容"]);
        }

        $receiveSite = $receiveSiteOther;
    }

    if ($receiveSiteChoice !== "其他") {
        $chk = $pdo->prepare("
            SELECT dest_site
            FROM files_transfer
            WHERE id = ?
            LIMIT 1
        ");
        $chk->execute([$id]);

        $destSite = (string)($chk->fetchColumn() ?? "");

        if ($destSite === "") {
            json_out(["ok" => false, "message" => "簽收失敗：找不到該文件"]);
        }

        if (normalize_site($destSite) !== normalize_site($receiveSiteChoice)) {
            json_out([
                "ok" => false,
                "message" => "簽收失敗：此文件的目的地區為「{$destSite}」，不符合你選的簽收地區"
            ]);
        }
    }

    $receiveTime = trim((string)($data["receive_time"] ?? ""));
    $receiveRemark = trim((string)($data["receive_remark"] ?? ""));

    $isProxy = !empty($data["is_proxy"]) ? 1 : 0;

    $finalNo = trim((string)($data["final_receiver_user_no"] ?? ""));
    $finalName = "";

    if ($isProxy === 1) {
        if ($finalNo === "" || !isset($userMap[$finalNo])) {
            json_out(["ok" => false, "message" => "代收時請選擇有效的最終簽收人"]);
        }

        $finalName = (string)$userMap[$finalNo]["name"];
    } else {
        $finalNo = "";
        $finalName = "";
    }

    $receiveTimeExpr = "NOW()";
    $receiveTimeParam = null;

    if ($receiveTime !== "") {
        $receiveTimeExpr = "?";
        $receiveTimeParam = str_replace("T", " ", $receiveTime) . ":00";
    }

    $newStatus = ($isProxy === 1) ? "PROXY_RECEIVED" : "RECEIVED";

    $sql = "
        UPDATE files_transfer
        SET
            received_by_user_no = ?,
            received_by_name = ?,
            receive_site = ?,
            receive_time = {$receiveTimeExpr},
            receive_remark = ?,
            is_proxy = ?,
            final_receiver_user_no = ?,
            final_receiver_name = ?,
            status = ?
        WHERE id = ?
          AND status = 'PICKED'
    ";

    $bind = [
        $receivedByUserNo,
        $receivedByName,
        $receiveSite
    ];

    if ($receiveTimeExpr === "?") {
        $bind[] = $receiveTimeParam;
    }

    $bind[] = ($receiveRemark === "") ? null : $receiveRemark;
    $bind[] = $isProxy;
    $bind[] = ($isProxy === 1) ? $finalNo : null;
    $bind[] = ($isProxy === 1) ? $finalName : null;
    $bind[] = $newStatus;
    $bind[] = $id;

    $stmt = $pdo->prepare($sql);
    $stmt->execute($bind);

    if ($stmt->rowCount() <= 0) {
        json_out([
            "ok" => false,
            "message" => "簽收失敗：可能尚未取件或狀態不符"
        ]);
    }

    json_out([
        "ok" => true,
        "message" => $isProxy ? "簽收完成，代收中" : "簽收完成，已結案"
    ]);
}

if ($action === "final_receive") {
    $id = (int)($data["final_id"] ?? 0);

    if ($id <= 0) {
        json_out(["ok" => false, "message" => "請選擇要最終簽收的文件"]);
    }

    $meNo = (string)$user["user_no"];
    $isAdmin = ($permission === 2) ? 1 : 0;

    $stmt = $pdo->prepare("
        UPDATE files_transfer
        SET
            final_receive_time = NOW(),
            status = 'COMPLETED'
        WHERE id = ?
          AND status = 'PROXY_RECEIVED'
          AND is_proxy = 1
          AND final_receive_time IS NULL
          AND (final_receiver_user_no = ? OR ?)
    ");

    $stmt->execute([
        $id,
        $meNo,
        $isAdmin
    ]);

    if ($stmt->rowCount() <= 0) {
        json_out([
            "ok" => false,
            "message" => "最終簽收失敗：你不是最終簽收人或狀態不符"
        ]);
    }

    json_out([
        "ok" => true,
        "message" => "最終簽收完成，已結案"
    ]);
}

json_out([
    "ok" => false,
    "message" => "未知操作"
]);