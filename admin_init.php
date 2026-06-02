<?php
declare(strict_types=1);

require_once __DIR__ . "/api/init.php";

if (!function_exists("require_admin")) {
    function require_admin(int $minLevel = 2): void {
        if (!isset($_SESSION["user"])) {
            header("Location: http://localhost:5173/login");
            exit;
        }

        $level = (int)($_SESSION["user"]["permission_level"] ?? 1);

        if ($level < $minLevel) {
            http_response_code(403);
            echo "<h1>403 Forbidden</h1>";
            echo "<p>權限不足。</p>";
            exit;
        }
    }
}

if (!function_exists("flash_get")) {
    function flash_get(string $key): string {
        $value = (string)($_SESSION["_flash"][$key] ?? "");
        unset($_SESSION["_flash"][$key]);
        return $value;
    }
}

if (!function_exists("flash_set")) {
    function flash_set(string $key, string $value): void {
        $_SESSION["_flash"][$key] = $value;
    }
}