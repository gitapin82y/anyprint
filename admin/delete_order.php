<?php
require_once '../includes/config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_role'] !== 'admin') {
    redirect('../login.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['order_id'])) {
    $order_id = intval($_POST['order_id']);
    
    // Get order files to delete physical files
    $stmt = $conn->prepare("SELECT file_path FROM order_files WHERE order_id = ?");
    $stmt->bind_param("i", $order_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();
    
    // Delete physical files
    while ($file = $result->fetch_assoc()) {
        if (file_exists('../' . $file['file_path'])) {
            unlink('../' . $file['file_path']);
        }
    }
    
    // Delete order folder
    $upload_dir = '../uploads/' . $order_id . '/';
    if (file_exists($upload_dir)) {
        rmdir($upload_dir);
    }
    
    // Delete order from database (CASCADE will delete order_files and payment_logs)
    $stmt = $conn->prepare("DELETE FROM orders WHERE id = ?");
    $stmt->bind_param("i", $order_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Order deleted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete order']);
    }
    
    $stmt->close();
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
}
?>