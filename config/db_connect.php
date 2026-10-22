<?php
require_once 'config.php';

function getDbConnection() {
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    try {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
        $conn->set_charset('utf8mb4');
    } catch (mysqli_sql_exception $error) {
        error_log('Database connection failed: ' . $error->getMessage());
        throw new RuntimeException('Database unavailable. Check the local database configuration.');
    }
    return $conn;
}

function closeDbConnection($conn) {
    $conn->close();
}
