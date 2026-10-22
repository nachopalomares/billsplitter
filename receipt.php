<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions.php';

$conn = getDbConnection();
$bill = visibleBill($conn, (int)($_GET['id'] ?? 0));
closeDbConnection($conn);
$name = basename($bill['image_path'] ?? '');
$path = UPLOAD_DIR . $name;
if ($name === '' || !is_file($path)) {
    http_response_code(404);
    exit('Receipt not found.');
}
$mime = (new finfo(FILEINFO_MIME_TYPE))->file($path);
if (!in_array($mime, ['image/jpeg', 'image/png'], true)) {
    http_response_code(404);
    exit('Receipt not found.');
}
header('Content-Type: ' . $mime);
header('Content-Disposition: inline; filename="receipt.' . ($mime === 'image/png' ? 'png' : 'jpg') . '"');
readfile($path);
