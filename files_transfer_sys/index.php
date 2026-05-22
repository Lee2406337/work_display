<?php
require_once __DIR__ . "/init.php";

if (isset($_SESSION["user"])) {
    header("Location: dashboard.php");
    exit;
}

header("Location: login.php");
exit;