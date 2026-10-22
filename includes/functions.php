<?php

require_once __DIR__ . '/../config/db_connect.php';

/**
 * Check if user is logged in
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

/**
 * Redirect if user is not logged in
 */
function requireLogin() {
    if (!isLoggedIn()) {
        if (str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')) {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Please log in again.']);
            exit;
        }
        header("Location: login.php");
        exit;
    }
}

/**
 * Sanitize user input
 */
function sanitizeInput($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

/**
 * Format currency
 */
function formatCurrency($amount) {
    return '$' . number_format($amount, 2);
}

/**
 * Get user info by ID
 */
function getUserById($userId) {
    $conn = getDbConnection();
    
    $stmt = $conn->prepare("SELECT user_id, username, email, created_at, last_login FROM users WHERE user_id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        $stmt->close();
        closeDbConnection($conn);
        return $user;
    }
    
    $stmt->close();
    closeDbConnection($conn);
    return null;
}

/**
 * Fetch hero statistics from the database.
 * @return array ['bills'=>int, 'users'=>int, 'hassles'=>int]
 */
function getHeroStats() {
    // Default values in case the query fails
    $stats = ['bills' => null, 'users' => null];
    
    // Try to get real data
    try {
        $conn = getDbConnection();
        $billsResult = $conn->query("SELECT COUNT(*) as count FROM bills");
        if ($billsResult) {
            $stats['bills'] = $billsResult->fetch_assoc()['count'];
        }
        
        $usersResult = $conn->query("SELECT COUNT(*) as count FROM users");
        if ($usersResult) {
            $stats['users'] = $usersResult->fetch_assoc()['count'];
        }
    } catch (Exception $e) {
        // If there's an error, we'll return the default values
    }
    
    if (isset($conn)) {
        closeDbConnection($conn);
    }
    return $stats;
}

function csrfField() {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') . '">';
}

function postString($key) {
    return is_string($_POST[$key] ?? null) ? $_POST[$key] : '';
}

function moneyToCents($value) {
    if ((!is_string($value) && !is_int($value)) || !preg_match('/^\d{1,8}(?:\.\d{1,2})?$/D', (string)$value)) {
        throw new InvalidArgumentException('Enter a non-negative amount with at most two decimal places.');
    }
    $parts = explode('.', (string)$value);
    return (int)$parts[0] * 100 + (int)str_pad($parts[1] ?? '', 2, '0');
}

function calculateParticipantAmounts($itemsMap, $participants, $assignments) {
    $amounts = [];
    foreach ($participants as $participant) {
        $amounts[$participant['participant_id']] = ['name' => $participant['name'], 'amount_cents' => 0];
    }
    $byItem = [];
    foreach ($assignments as $assignment) {
        if (isset($itemsMap[$assignment['item_id']], $amounts[$assignment['participant_id']])) {
            $byItem[$assignment['item_id']][$assignment['participant_id']] = true;
        }
    }
    foreach ($byItem as $itemId => $assigned) {
        $ids = array_keys($assigned);
        sort($ids, SORT_NUMERIC);
        $cents = moneyToCents($itemsMap[$itemId]['price']);
        $share = intdiv($cents, count($ids));
        $remainder = $cents % count($ids);
        // Assign spare cents by participant ID so every item reconciles exactly.
        foreach ($ids as $position => $id) {
            $amounts[$id]['amount_cents'] += $share + ($position < $remainder ? 1 : 0);
        }
    }
    foreach ($amounts as &$amount) {
        $amount['amount'] = $amount['amount_cents'] / 100;
    }
    unset($amount);
    return $amounts;
}

function ensureShareToken($conn, $bill) {
    if (empty($bill['share_token'])) {
        $token = bin2hex(random_bytes(32));
        $stmt = $conn->prepare('UPDATE bills SET share_token = ? WHERE bill_id = ? AND user_id = ? AND share_token IS NULL');
        $stmt->bind_param('sii', $token, $bill['bill_id'], $bill['user_id']);
        $stmt->execute();
        $stmt->close();
        $stmt = $conn->prepare('SELECT share_token FROM bills WHERE bill_id = ? AND user_id = ?');
        $stmt->bind_param('ii', $bill['bill_id'], $bill['user_id']);
        $stmt->execute();
        $bill['share_token'] = $stmt->get_result()->fetch_assoc()['share_token'];
        $stmt->close();
    }
    return $bill;
}

function visibleBill($conn, $billId) {
    $stmt = $conn->prepare('SELECT * FROM bills WHERE bill_id = ?');
    $stmt->bind_param('i', $billId);
    $stmt->execute();
    $bill = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $owner = $bill && isLoggedIn() && (int)$bill['user_id'] === (int)$_SESSION['user_id'];
    $token = $_GET['token'] ?? '';
    $shared = $bill && is_string($token) && preg_match('/^[a-f0-9]{64}$/D', $token)
        && !empty($bill['share_token']) && hash_equals($bill['share_token'], $token);
    if (!$owner && !$shared) {
        http_response_code(404);
        exit('Bill not found.');
    }
    header('Cache-Control: private, no-store');
    return $owner ? ensureShareToken($conn, $bill) : $bill;
}

function receiptUrl($bill) {
    return BASE_URL . '/receipt.php?id=' . (int)$bill['bill_id']
        . '&token=' . urlencode($bill['share_token'] ?? '');
}
