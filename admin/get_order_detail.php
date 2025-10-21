<?php
require_once '../includes/config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_role'] !== 'admin') {
    redirect('../login.php');
}

if (isset($_GET['order_id']) && is_numeric($_GET['order_id'])) {
    $order_id = intval($_GET['order_id']);
    
    // Get order details
    $stmt = $conn->prepare("SELECT * FROM orders WHERE id = ?");
    $stmt->bind_param("i", $order_id);
    $stmt->execute();
    $order = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if ($order) {
        // Get order files
        $stmt = $conn->prepare("SELECT * FROM order_files WHERE order_id = ? ORDER BY uploaded_at ASC");
        $stmt->bind_param("i", $order_id);
        $stmt->execute();
        $files_result = $stmt->get_result();
        $stmt->close();
        
        $files = [];
        while ($file = $files_result->fetch_assoc()) {
            $files[] = $file;
        }
        
        echo json_encode([
            'success' => true,
            'order' => $order,
            'files' => $files
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Order not found']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid order ID']);
}
?>