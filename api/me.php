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

echo json_encode([
    "ok" => true,
    "user" => $_SESSION["user"]
], JSON_UNESCAPED_UNICODE);