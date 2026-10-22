<?php
define('APP_NAME', 'Bill Splitter');
define('BASE_URL', rtrim(getenv('BASE_URL') ?: 'http://localhost:8000', '/'));

// Database configuration
define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
define('DB_PORT', (int)(getenv('DB_PORT') ?: 3306));
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');
define('DB_NAME', getenv('DB_NAME') ?: 'bill_splitter');

// Upload configuration
// Keep receipt files outside the public document root.
define('UPLOAD_DIR', rtrim(getenv('UPLOAD_DIR') ?: dirname(__DIR__, 2) . '/billsplitter-storage/receipts', '/') . '/');
define('MAX_RECEIPT_SIZE', 5 * 1024 * 1024);

// Session configuration
ini_set('session.cookie_httponly', 1);
ini_set('session.use_strict_mode', 1);
ini_set('session.cookie_samesite', 'Lax');
ini_set('session.cookie_secure', parse_url(BASE_URL, PHP_URL_SCHEME) === 'https' ? '1' : '0');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$_SESSION['csrf_token'] ??= bin2hex(random_bytes(32));

header('Referrer-Policy: no-referrer');
header('X-Content-Type-Options: nosniff');
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $token = $_POST['csrf_token'] ?? null;
    if (!is_string($token) || !hash_equals($_SESSION['csrf_token'], $token)) {
        http_response_code(403);
        if (str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'The form expired. Reload the page and try again.']);
        } else {
            echo 'The form expired. Reload the page and try again.';
        }
        exit;
    }
}
