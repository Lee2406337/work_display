<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.cookie_httponly', '1');
    // ini_set('session.cookie_secure', '1'); // HTTPS
    ini_set('session.cookie_samesite', 'Lax');

    session_start();
}


date_default_timezone_set('Asia/Taipei');

require_once __DIR__ . "/db.php";

function h(?string $s): string {
    return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8');
}

function require_login(): void {
    if (!isset($_SESSION["user"])) {
        $next = $_SERVER["REQUEST_URI"] ?? "dashboard.php";
        header("Location: login.php?error=" . urlencode("請先登入") . "&next=" . urlencode($next));
        exit;
    }
}

// CSRF token
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