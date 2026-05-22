<?php
$host = ",,,,";
$port = ,,,,;
$db   = ",,,,";
$user = ",,,,";
$pass = ",,,,";
$charset = ",,,,";

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
