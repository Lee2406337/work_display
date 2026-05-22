<?php
$host = "localhost";
$port = 3306;
$db   = "webp2026_finalproject";
$user = "webp2026_user";
$pass = "G7@kP9#xL2!vQ5&bZ3~mHwebp2026";
$charset = "utf8mb4";

$dsn = "mysql:host={$host};port={$port};dbname={$db};charset={$charset}";

try {
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
} catch (PDOException $e) {
    error_log("DB connect error: " . $e->getMessage());
    exit("DB 連線失敗");
}