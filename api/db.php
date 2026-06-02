<?php
$host = "sql111.infinityfree.com";
$port = 3306;
$db   = "if0_42032195_webp2026";
$user = "if0_42032195";
$pass = "f6Zaxfxmussb";
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