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
    $stats = ['bills' => 2500, 'users' => 1200, 'hassles' => 0];
    
    $conn = getDbConnection();
    if (!$conn) {
        return $stats; // Return default values if connection fails
    }
    
    // Try to get real data
    try {
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
    
    closeDbConnection($conn);
    return $stats;
}

?>