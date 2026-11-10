<?php
require_once __DIR__ . '/config.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    $conn->set_charset('utf8mb4');
    $conn->query("SET time_zone = '+06:00'");
} catch (mysqli_sql_exception $e) {
    http_response_code(500);
    error_log('DB connection failed: ' . $e->getMessage());
    die('Database connection failed. Check config/config.php and that the "upohar" database is imported.');
}
