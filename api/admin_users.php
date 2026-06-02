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

function normalize_site(string $siteChoice, string $siteOther): string {
    $siteChoice = trim($siteChoice);
    $siteOther = trim($siteOther);

    if ($siteChoice === "其他") {
        return $siteOther;
    }

    return $siteChoice;
}

function sql_datetime_or_null(string $value): ?string {
    $value = trim($value);

    if ($value === "") {
        return null;
    }

    return str_replace("T", " ", $value) . ":00";
}

function sql_datetime_or_now(string $value): string {
    $value = trim($value);

    if ($value === "") {
        return date("Y-m-d H:i:s");
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

if ($_SERVER["REQUEST_METHOD"] === "GET") {
    $id = (int)($_GET["id"] ?? 0);

    if ($id > 0) {
        $stmt = $pdo->prepare("
            SELECT
                id,
                user_no,
                name,
                site,
                is_active,
                permission_level,
                created_at,
                deactivate_at,
                COALESCE(login_chance, 5) AS login_chance
            FROM user_data
            WHERE id = ?
            LIMIT 1
        ");

        $stmt->execute([$id]);

        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            json_out([
                "ok" => false,
                "message" => "找不到使用者"
            ]);
        }

        $user["created_at"] = sql_to_dt_local($user["created_at"] ?? "");
        $user["deactivate_at"] = sql_to_dt_local($user["deactivate_at"] ?? "");

        json_out([
            "ok" => true,
            "user" => $user,
            "now" => date("Y-m-d\TH:i")
        ]);
    }

    $q = trim((string)($_GET["q"] ?? ""));
    $site = trim((string)($_GET["site"] ?? ""));
    $isActive = trim((string)($_GET["is_active"] ?? ""));
    $permissionLevel = trim((string)($_GET["permission_level"] ?? ""));

    $where = [];
    $params = [];

    $where[] = "user_no <> :admin_user";
    $params[":admin_user"] = "administrator";

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

    if ($site !== "") {
        $where[] = "site = :site";
        $params[":site"] = $site;
    }

    if ($isActive !== "") {
        if ($isActive !== "0" && $isActive !== "1") {
            json_out([
                "ok" => false,
                "message" => "帳號狀態參數不正確"
            ]);
        }

        $where[] = "is_active = :is_active";
        $params[":is_active"] = (int)$isActive;
    }

    if ($permissionLevel !== "") {
        if ($permissionLevel !== "1" && $permissionLevel !== "2") {
            json_out([
                "ok" => false,
                "message" => "權限等級參數不正確"
            ]);
        }

        $where[] = "permission_level = :permission_level";
        $params[":permission_level"] = (int)$permissionLevel;
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
            deactivate_at,
            COALESCE(login_chance, 5) AS login_chance
        FROM user_data
    ";

    if ($where) {
        $sql .= " WHERE " . implode(" AND ", $where);
    }

    $sql .= " ORDER BY id DESC LIMIT 100";

    try {
        $stmt = $pdo->prepare($sql);

        foreach ($params as $key => $value) {
            if ($key === ":is_active" || $key === ":permission_level") {
                $stmt->bindValue($key, $value, PDO::PARAM_INT);
            } else {
                $stmt->bindValue($key, $value, PDO::PARAM_STR);
            }
        }

        $stmt->execute();

        json_out([
            "ok" => true,
            "data" => $stmt->fetchAll(PDO::FETCH_ASSOC)
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

if ($action === "toggle_active") {
    if ($id <= 0) {
        json_out([
            "ok" => false,
            "message" => "ID 不正確"
        ]);
    }

    $stmt = $pdo->prepare("
        SELECT is_active
        FROM user_data
        WHERE id = ?
        LIMIT 1
    ");

    $stmt->execute([$id]);

    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        json_out([
            "ok" => false,
            "message" => "找不到使用者"
        ]);
    }

    $newActive = ((int)$user["is_active"] === 1) ? 0 : 1;

    $stmt = $pdo->prepare("
        UPDATE user_data
        SET
            is_active = ?,
            deactivate_at = CASE WHEN ? = 0 THEN NOW() ELSE NULL END
        WHERE id = ?
    ");

    $stmt->execute([
        $newActive,
        $newActive,
        $id
    ]);

    json_out([
        "ok" => true,
        "message" => "已更新帳號狀態"
    ]);
}

if ($action === "set_level") {
    if ($id <= 0) {
        json_out([
            "ok" => false,
            "message" => "ID 不正確"
        ]);
    }

    $permissionLevel = (int)($data["permission_level"] ?? 1);

    if ($permissionLevel !== 1 && $permissionLevel !== 2) {
        json_out([
            "ok" => false,
            "message" => "權限等級不正確"
        ]);
    }

    $stmt = $pdo->prepare("
        UPDATE user_data
        SET permission_level = ?
        WHERE id = ?
    ");

    $stmt->execute([
        $permissionLevel,
        $id
    ]);

    json_out([
        "ok" => true,
        "message" => "已更新權限等級"
    ]);
}

if ($action === "unlock_user") {
    if ($id <= 0) {
        json_out([
            "ok" => false,
            "message" => "ID 不正確"
        ]);
    }

    $stmt = $pdo->prepare("
        UPDATE user_data
        SET login_chance = 5
        WHERE id = ?
    ");

    $stmt->execute([$id]);

    json_out([
        "ok" => true,
        "message" => "已解鎖使用者"
    ]);
}

if ($action === "add" || $action === "update") {
    $customId = trim((string)($data["custom_id"] ?? ""));

    $userNo = trim((string)($data["user_no"] ?? ""));
    $name = trim((string)($data["name"] ?? ""));

    $siteChoice = trim((string)($data["site_choice"] ?? "林口"));
    $siteOther = trim((string)($data["site_other"] ?? ""));
    $site = normalize_site($siteChoice, $siteOther);

    $password = (string)($data["password"] ?? "");

    $isActive = (int)($data["is_active"] ?? 1);
    $permissionLevel = (int)($data["permission_level"] ?? 1);

    $createdAt = sql_datetime_or_now((string)($data["created_at"] ?? ""));
    $deactivateAt = sql_datetime_or_null((string)($data["deactivate_at"] ?? ""));

    if ($userNo === "") {
        json_out([
            "ok" => false,
            "message" => "登錄帳號必填"
        ]);
    }

    if ($name === "") {
        json_out([
            "ok" => false,
            "message" => "姓名必填"
        ]);
    }

    if (!preg_match('/^[A-Za-z0-9_-]+$/', $userNo)) {
        json_out([
            "ok" => false,
            "message" => "登錄帳號格式不正確，只允許英數、底線、減號"
        ]);
    }

    if (!in_array($siteChoice, ["林口", "中壢", "其他"], true)) {
        json_out([
            "ok" => false,
            "message" => "地區選項不正確"
        ]);
    }

    if ($siteChoice === "其他" && $site === "") {
        json_out([
            "ok" => false,
            "message" => "地區選擇「其他」時，請填寫其他地區"
        ]);
    }

    if ($isActive !== 0 && $isActive !== 1) {
        json_out([
            "ok" => false,
            "message" => "帳號狀態不正確"
        ]);
    }

    if ($permissionLevel !== 1 && $permissionLevel !== 2) {
        json_out([
            "ok" => false,
            "message" => "權限等級不正確"
        ]);
    }

    if ($action === "add") {
        if ($password === "") {
            json_out([
                "ok" => false,
                "message" => "新增使用者時密碼必填"
            ]);
        }

        if (strlen($password) < 4) {
            json_out([
                "ok" => false,
                "message" => "密碼至少需要 4 碼"
            ]);
        }

        if ($password === $userNo) {
            json_out([
                "ok" => false,
                "message" => "密碼不可與登錄帳號相同"
            ]);
        }

        if ($customId !== "" && !ctype_digit($customId)) {
            json_out([
                "ok" => false,
                "message" => "自訂 ID 必須是數字"
            ]);
        }

        if ($customId !== "") {
            $chkId = $pdo->prepare("
                SELECT COUNT(*)
                FROM user_data
                WHERE id = ?
            ");

            $chkId->execute([(int)$customId]);

            if ((int)$chkId->fetchColumn() > 0) {
                json_out([
                    "ok" => false,
                    "message" => "此 ID 已存在"
                ]);
            }
        }

        $chk = $pdo->prepare("
            SELECT COUNT(*)
            FROM user_data
            WHERE user_no = ?
        ");

        $chk->execute([$userNo]);

        if ((int)$chk->fetchColumn() > 0) {
            json_out([
                "ok" => false,
                "message" => "此登錄帳號已存在"
            ]);
        }

        $passwordHash = password_hash($password, PASSWORD_DEFAULT);

        if ($customId !== "") {
            $stmt = $pdo->prepare("
                INSERT INTO user_data
                (
                    id,
                    user_no,
                    password_hash,
                    name,
                    site,
                    is_active,
                    permission_level,
                    created_at,
                    deactivate_at,
                    login_chance
                )
                VALUES
                (?, ?, ?, ?, ?, ?, ?, ?, ?, 5)
            ");

            $stmt->execute([
                (int)$customId,
                $userNo,
                $passwordHash,
                $name,
                $site,
                $isActive,
                $permissionLevel,
                $createdAt,
                $isActive === 1 ? null : ($deactivateAt ?: date("Y-m-d H:i:s"))
            ]);
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO user_data
                (
                    user_no,
                    password_hash,
                    name,
                    site,
                    is_active,
                    permission_level,
                    created_at,
                    deactivate_at,
                    login_chance
                )
                VALUES
                (?, ?, ?, ?, ?, ?, ?, ?, 5)
            ");

            $stmt->execute([
                $userNo,
                $passwordHash,
                $name,
                $site,
                $isActive,
                $permissionLevel,
                $createdAt,
                $isActive === 1 ? null : ($deactivateAt ?: date("Y-m-d H:i:s"))
            ]);
        }

        json_out([
            "ok" => true,
            "message" => "已新增使用者"
        ]);
    }

    if ($action === "update") {
        if ($id <= 0) {
            json_out([
                "ok" => false,
                "message" => "ID 不正確"
            ]);
        }

        $chk = $pdo->prepare("
            SELECT COUNT(*)
            FROM user_data
            WHERE user_no = ?
              AND id <> ?
        ");

        $chk->execute([
            $userNo,
            $id
        ]);

        if ((int)$chk->fetchColumn() > 0) {
            json_out([
                "ok" => false,
                "message" => "此登錄帳號已被其他使用者使用"
            ]);
        }

        if ($password !== "") {
            if (strlen($password) < 4) {
                json_out([
                    "ok" => false,
                    "message" => "新密碼至少需要 4 碼"
                ]);
            }

            if ($password === $userNo) {
                json_out([
                    "ok" => false,
                    "message" => "新密碼不可與登錄帳號相同"
                ]);
            }

            $passwordHash = password_hash($password, PASSWORD_DEFAULT);

            $stmt = $pdo->prepare("
                UPDATE user_data
                SET
                    user_no = ?,
                    password_hash = ?,
                    name = ?,
                    site = ?,
                    is_active = ?,
                    permission_level = ?,
                    created_at = ?,
                    deactivate_at = ?
                WHERE id = ?
            ");

            $stmt->execute([
                $userNo,
                $passwordHash,
                $name,
                $site,
                $isActive,
                $permissionLevel,
                $createdAt,
                $isActive === 1 ? null : ($deactivateAt ?: date("Y-m-d H:i:s")),
                $id
            ]);
        } else {
            $stmt = $pdo->prepare("
                UPDATE user_data
                SET
                    user_no = ?,
                    name = ?,
                    site = ?,
                    is_active = ?,
                    permission_level = ?,
                    created_at = ?,
                    deactivate_at = ?
                WHERE id = ?
            ");

            $stmt->execute([
                $userNo,
                $name,
                $site,
                $isActive,
                $permissionLevel,
                $createdAt,
                $isActive === 1 ? null : ($deactivateAt ?: date("Y-m-d H:i:s")),
                $id
            ]);
        }

        json_out([
            "ok" => true,
            "message" => "已更新使用者"
        ]);
    }
}

json_out([
    "ok" => false,
    "message" => "未知操作"
]);