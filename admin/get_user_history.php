<?php
require_once '../includes/config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_role'] !== 'admin') {
    redirect('../login.php');
}

if (isset($_GET['user_id']) && is_numeric($_GET['user_id'])) {
    $user_id = intval($_GET['user_id']);
    
    // Get user stats
    $stmt = $conn->prepare("
        SELECT 
            COUNT(*) as total_orders,
            COALESCE(SUM(total_pages), 0) as total_pages,
            COALESCE(SUM(total_price), 0) as total_spent
        FROM orders
        WHERE user_id = ? AND payment_status = 'success'
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stats = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    // Get order history
    $stmt = $conn->prepare("
        SELECT 
            order_number,
            total_pages,
            total_price,
            DATE_FORMAT(created_at, '%d %b %Y %H:%i') as created_at
        FROM orders
        WHERE user_id = ? AND payment_status = 'success'
        ORDER BY created_at DESC
        LIMIT 50
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();
    
    $history = [];
    while ($row = $result->fetch_assoc()) {
        $history[] = $row;
    }
    
    echo json_encode([
        'success' => true,
        'stats' => $stats,
        'history' => $history
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid user ID']);
}
?>