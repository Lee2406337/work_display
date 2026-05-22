<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

date_default_timezone_set('Asia/Taipei');

require_once __DIR__ . "/db.php";

// PHP版本補丁
if (!function_exists('str_starts_with')) {
    function str_starts_with(string $haystack, string $needle): bool {
        return $needle === '' || strncmp($haystack, $needle, strlen($needle)) === 0;
    }
}
if (!function_exists('str_contains')) {
    function str_contains(string $haystack, string $needle): bool {
        return $needle === '' || strpos($haystack, $needle) !== false;
    }
}

function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

function require_login(): void {
    if (!isset($_SESSION["user"])) {
        $next = $_SERVER["REQUEST_URI"] ?? "admin_dashboard.php";
        header("Location: admin.php?next=" . urlencode($next));
        exit;
    }
}

function require_admin(int $minLevel = 2): void {
    require_login();
    $lv = (int)($_SESSION["user"]["permission_level"] ?? 1);
    if ($lv < $minLevel) {
        http_response_code(403);
        echo "<h1>403 Forbidden</h1><p>權限不足。</p>";
        exit;
    }
}

function csrf_token(): string {
    if (empty($_SESSION["_csrf"])) {
        $_SESSION["_csrf"] = bin2hex(random_bytes(16));
    }
    return $_SESSION["_csrf"];
}

function verify_csrf(): void {
    $t = (string)($_POST["_csrf"] ?? "");
    if ($t === "" || !hash_equals((string)($_SESSION["_csrf"] ?? ""), $t)) {
        http_response_code(400);
        exit("CSRF 驗證失敗，請重新操作。");
    }
}

function redirect_to(string $path, array $qs = []): void {
    $url = $path;
    if ($qs) $url .= "?" . http_build_query($qs);
    header("Location: " . $url);
    exit;
}

function flash_get(string $key): string {
    $v = (string)($_SESSION["_flash"][$key] ?? "");
    unset($_SESSION["_flash"][$key]);
    return $v;
}

function flash_set(string $key, string $val): void {
    $_SESSION["_flash"][$key] = $val;
}