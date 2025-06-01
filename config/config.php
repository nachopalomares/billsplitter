<?php
// Application configuration
define('APP_NAME', 'Bill Splitter');
define('BASE_URL', 'http://localhost/billsplitter');

// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'bill_splitter');

// Upload configuration
define('UPLOAD_DIR', __DIR__ . '/../uploads/'); // Ruta absoluta del servidor
define('UPLOAD_WEB_PATH', 'uploads/'); // Ruta relativa para el navegador

// Session configuration
ini_set('session.cookie_httponly', 1);
session_start();
?>