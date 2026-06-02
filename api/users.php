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

$currentUser = $_SESSION["user"];
$permission = (int)($currentUser["permission_level"] ?? 1);

$q = trim((string)($_GET["q"] ?? ""));
$site = trim((string)($_GET["site"] ?? ""));
$isActive = trim((string)($_GET["is_active"] ?? ""));

$where = [];
$params = [];

// 不顯示系統預設管理帳號
$where[] = "user_no <> :admin_user";
$params[":admin_user"] = "administrator";

// 一般使用者只看啟用帳號
if ($permission < 2) {
    $where[] = "is_active = 1";
}

// 關鍵字搜尋：登錄帳號 / 姓名 / 地區
// 注意：不要重複使用同一個 :kw，因為 PDO emulate prepares 關閉時可能會失敗
if ($q !== "") {
    $where[] = "(
        user_no LIKE :kw_user_no
        OR name LIKE :kw_name
        OR site LIKE :kw_site
    )";

    $kw = "%{$q}%";
    $params[":kw_user_no"] = $kw;
    $params[":kw_name"] = $kw;
    $params[":kw_site"] = $kw;
}

// 地區篩選
if ($site !== "") {
    $where[] = "site = :site";
    $params[":site"] = $site;
}

// 管理者才允許篩選啟用 / 停用
if ($permission >= 2 && $isActive !== "") {
    if ($isActive !== "0" && $isActive !== "1") {
        json_out([
            "ok" => false,
            "message" => "帳號狀態參數不正確"
        ]);
    }

    $where[] = "is_active = :is_active";
    $params[":is_active"] = (int)$isActive;
}

$sql = "
    SELECT
        id,
        user_no,
        name,
        site,
        is_active,
        permission_level,
        created_at,
        deactivate_at
    FROM user_data
";

if ($where) {
    $sql .= " WHERE " . implode(" AND ", $where);
}

$sql .= " ORDER BY id DESC LIMIT 100";

try {
    $stmt = $pdo->prepare($sql);

    foreach ($params as $key => $value) {
        if ($key === ":is_active") {
            $stmt->bindValue($key, $value, PDO::PARAM_INT);
        } else {
            $stmt->bindValue($key, $value, PDO::PARAM_STR);
        }
    }

    $stmt->execute();

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $data = [];

    foreach ($rows as $row) {
        $permissionLevel = (int)($row["permission_level"] ?? 1);
        $active = (int)($row["is_active"] ?? 0);

        $data[] = [
            "id" => (int)$row["id"],
            "user_no" => (string)($row["user_no"] ?? ""),
            "name" => (string)($row["name"] ?? ""),
            "site" => (string)($row["site"] ?? ""),
            "is_active" => $active,
            "is_active_text" => $active === 1 ? "啟用" : "停用",
            "permission_level" => $permissionLevel,
            "permission_text" => $permissionLevel === 2 ? "管理者" : "一般用戶",
            "created_at" => (string)($row["created_at"] ?? ""),
            "deactivate_at" => (string)($row["deactivate_at"] ?? ""),
        ];
    }

    json_out([
        "ok" => true,
        "current_permission" => $permission,
        "data" => $data
    ]);

} catch (Throwable $e) {
    json_out([
        "ok" => false,
        "message" => "查詢失敗：" . $e->getMessage()
    ]);
}